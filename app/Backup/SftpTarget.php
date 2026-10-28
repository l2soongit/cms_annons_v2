<?php

namespace App\Backup;

use Illuminate\Support\Carbon;
use phpseclib4\Crypt\PublicKeyLoader;
use phpseclib4\Net\SFTP;
use RuntimeException;

/**
 * Копія бекапу поза сервером (ТЗ 4): SFTP на окреме сховище (Hetzner Storage Box, VPS тощо).
 * Чистий PHP (phpseclib) — працює без розширень ftp/ssh2 на віртуальному хостингу.
 */
class SftpTarget implements BackupTarget
{
    private ?SFTP $sftp = null;

    public function __construct(private readonly array $config) {}

    public function upload(string $localFile): void
    {
        $remote = $this->path(basename($localFile));
        $this->client()->put($remote, $localFile, SFTP::SOURCE_LOCAL_FILE);

        if ($this->client()->filesize($remote) !== filesize($localFile)) {
            throw new RuntimeException("Backup upload is incomplete: {$remote}");
        }
    }

    public function prune(Carbon $before): int
    {
        $deleted = 0;
        foreach ($this->client()->rawlist($this->path('')) as $name => $file) {
            if (preg_match('/^db-.+\.sql\.gz$/', (string) $name) && ($file['mtime'] ?? PHP_INT_MAX) < $before->getTimestamp()) {
                $this->client()->delete($this->path((string) $name), false);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function client(): SFTP
    {
        if ($this->sftp) {
            return $this->sftp;
        }

        $sftp = new SFTP($this->config['host'], (int) ($this->config['port'] ?? 22), 20);
        $secret = filled($this->config['key'] ?? null)
            ? PublicKeyLoader::loadPrivateKey((string) file_get_contents($this->config['key']), $this->config['password'] ?: null)
            : (string) $this->config['password'];

        if (! $sftp->login($this->config['username'], $secret)) {
            throw new RuntimeException('SFTP login failed for backup storage');
        }

        if (! $sftp->is_dir($this->path(''))) {
            $sftp->mkdir($this->path(''), -1, true);
        }

        return $this->sftp = $sftp;
    }

    private function path(string $file): string
    {
        return rtrim($this->config['root'] ?: '.', '/').'/'.$file;
    }
}
