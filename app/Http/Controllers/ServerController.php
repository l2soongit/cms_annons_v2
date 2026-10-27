<?php

namespace App\Http\Controllers;

use App\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\View\View;

class ServerController extends Controller
{
    public function show(Server $server): View
    {
        abort_unless($server->status === ServerStatus::Published, 404);

        $server->load(['game', 'fieldValues.field']);

        return view('servers.show', compact('server'));
    }
}
