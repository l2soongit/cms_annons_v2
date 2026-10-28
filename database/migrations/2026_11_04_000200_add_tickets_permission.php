<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Дозвіл на тікети підтримки; модератор отримує його одразу (підтримка — його робота за ТЗ) */
    public function up(): void
    {
        Permissions::sync();
        Role::where('name', 'moderator')->where('guard_name', 'web')->first()?->givePermissionTo('tickets.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        //
    }
};
