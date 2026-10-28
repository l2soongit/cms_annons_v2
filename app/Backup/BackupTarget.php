<?php

namespace App\Backup;

use Illuminate\Support\Carbon;

/** Куди копіювати бекап поза сервером */
interface BackupTarget
{
    public function upload(string $localFile): void;

    /** Видалити копії, старші за дату; повертає кількість видалених */
    public function prune(Carbon $before): int;
}
