<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Дозволи порталу (RBAC). Ролі — набори цих дозволів; нові ролі адмін створює в адмінці.
 * Роль admin має всі дозволи завжди (Gate::before), тож доступ до керування ролями не втратити.
 */
final class Permissions
{
    /** Ключ => назва в адмінці */
    public const LIST = [
        'admin.access' => 'Вхід в адмін-панель',
        'servers.moderate' => 'Модерація серверів (схвалення, відхилення, блокування)',
        'reviews.moderate' => 'Модерація відгуків',
        'reports.moderate' => 'Розгляд скарг',
        'votes.moderate' => 'Підозрілі голоси й анулювання',
        'users.ban' => 'Попередження й блокування користувачів',
        'users.manage' => 'Редагування користувачів і їхніх ролей',
        'logs.view' => 'Журнал дій модераторів',
        'games.manage' => 'Ігри, поля й оформлення сторінок',
        'settings.manage' => 'Налаштування порталу й головної',
        'billing.manage' => 'Тарифи, промокоди, платежі й фінансовий звіт',
        'tickets.manage' => 'Тікети підтримки: відповіді й закриття',
        'roles.manage' => 'Ролі та дозволи',
    ];

    /** Ролі, без яких портал не працює: їх не можна перейменувати чи видалити */
    public const CORE_ROLES = ['player', 'owner', 'moderator', 'admin'];

    /** Дозволи ролей за замовчуванням (admin має всі через Gate::before) */
    private const DEFAULTS = [
        'moderator' => ['admin.access', 'servers.moderate', 'reviews.moderate', 'reports.moderate', 'votes.moderate', 'users.ban', 'logs.view', 'tickets.manage'],
    ];

    /** Створити дозволи й ролі; наявним ролям дозволи за замовчуванням додаються лише при першому створенні */
    public static function sync(): void
    {
        foreach (array_keys(self::LIST) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::CORE_ROLES as $name) {
            $role = Role::findOrCreate($name, 'web');
            if ($role->wasRecentlyCreated || $role->permissions()->doesntExist()) {
                $role->givePermissionTo(self::DEFAULTS[$name] ?? []);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
