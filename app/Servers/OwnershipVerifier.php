<?php

namespace App\Servers;

use App\Models\Server;
use App\Monitoring\DriverRegistry;
use App\Support\PublicHost;
use Illuminate\Support\Facades\Http;

/**
 * Підтвердження власності сервера одним із трьох способів:
 * код у назві/MOTD сервера, DNS TXT-запис або файл на сайті.
 */
class OwnershipVerifier
{
    public function __construct(private DriverRegistry $drivers) {}

    public function code(Server $server): string
    {
        return 'graj-'.$server->verify_token;
    }

    /**
     * @return string|null спосіб, яким вдалося підтвердити, або null
     */
    public function verify(Server $server): ?string
    {
        $method = match (true) {
            $this->viaServerName($server) => 'motd',
            $this->viaDns($server) => 'dns',
            $this->viaWebsite($server) => 'file',
            default => null,
        };

        if ($method) {
            $server->forceFill(['verified_at' => now()])->saveQuietly();
        }

        return $method;
    }

    private function viaServerName(Server $server): bool
    {
        if (! PublicHost::isPublic($server->host)) {
            return false;
        }

        $port = $server->port ?: $server->game->default_port ?: 0;
        $result = $this->drivers->for($server)->query($server->host, (int) $port, 3);

        return $result->online && $result->name && str_contains($result->name, $this->code($server));
    }

    private function viaDns(Server $server): bool
    {
        if (filter_var($server->host, FILTER_VALIDATE_IP)) {
            return false;
        }

        $records = @dns_get_record($server->host, DNS_TXT) ?: [];

        return collect($records)->contains(fn ($r) => str_contains($r['txt'] ?? '', $this->code($server)));
    }

    private function viaWebsite(Server $server): bool
    {
        if (! PublicHost::isPublicUrl($server->website)) {
            return false;
        }

        try {
            $body = Http::timeout(5)->withoutRedirecting()->get(rtrim($server->website, '/').'/graj-verify.txt')->body();

            return str_contains($body, $this->code($server));
        } catch (\Throwable) {
            return false;
        }
    }
}
