<?php

namespace App\Model;

use Override;
use SilverStripe\ORM\DataObject;

/**
 * A single link a {@see User} has added to their public profile (e.g. their
 * Instagram or a personal website). The Platform is an icon key from the
 * frontend's platform registry (frontend/src/utils/socialPlatforms.js) –
 * auto-detected from the URL there, but freely overridable by the user, so
 * it's stored as a plain string rather than validated against a fixed list.
 *
 * @property string $URL
 * @property string $Title
 * @property string $Platform
 * @property string $Placement
 * @property int $SortOrder
 * @property int $UserID
 * @method User User()
 */
class ProfileLink extends DataObject
{
    private static string $table_name = 'ProfileLink';
    private static string $singular_name = 'Profile Link';
    private static string $plural_name = 'Profile Links';

    /**
     * 'below' – the unlimited list shown under the card (the default).
     * 'card' – one of up to 3 slots shown directly on the card face itself,
     * managed completely independently of the 'below' list (see
     * {@see \App\Api\InternalApiController::createLink()} for the 3-slot cap).
     */
    public const PLACEMENT_BELOW = 'below';
    public const PLACEMENT_CARD = 'card';

    private static array $db = [
        'URL' => 'Varchar(500)',
        'Title' => 'Varchar(255)',
        'Platform' => 'Varchar(50)',
        'Placement' => 'Varchar(10)',
        'SortOrder' => 'Int',
    ];

    private static array $has_one = [
        'User' => User::class,
    ];

    private static array $indexes = [
        'UserID' => true,
    ];

    private static string $default_sort = 'SortOrder ASC';

    /**
     * @return array{id: int, url: string, title: string, platform: string, placement: string, sortOrder: int}
     */
    public function toApiData(): array
    {
        return [
            'id' => (int) $this->ID,
            'url' => (string) $this->URL,
            'title' => (string) $this->Title,
            'platform' => (string) $this->Platform,
            'placement' => (string) ($this->Placement ?: self::PLACEMENT_BELOW),
            'sortOrder' => (int) $this->SortOrder,
        ];
    }

    #[Override]
    public function canView($member = null)
    {
        return true;
    }

    #[Override]
    public function canEdit($member = null)
    {
        return false;
    }

    #[Override]
    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    #[Override]
    public function canDelete($member = null)
    {
        return false;
    }
}
