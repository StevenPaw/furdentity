<?php

namespace App\Admin;

use App\Model\Profile;
use SilverStripe\Admin\ModelAdmin;

/**
 * CMS section for the example {@see Profile} model. Replace together with the
 * model once the real furry-identity models exist.
 */
class ProfileAdmin extends ModelAdmin
{
    private static string $url_segment = 'profiles';

    private static string $menu_title = 'Profile';

    private static string $menu_icon_class = 'font-icon-torso';

    private static array $managed_models = [
        Profile::class,
    ];
}
