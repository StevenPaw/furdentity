<?php

namespace App\Admin;

use App\Model\User;
use SilverStripe\Admin\ModelAdmin;

/**
 * CMS section for managing app {@see User}s. Separate from the CMS admin
 * accounts (SilverStripe Member) – this only manages the app's own users.
 */
class UserAdmin extends ModelAdmin
{
    private static string $url_segment = 'users';

    private static string $menu_title = 'Users';

    private static string $menu_icon_class = 'font-icon-torso';

    private static array $managed_models = [
        User::class,
    ];
}
