<?php

namespace App\Model;

use Override;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * An app user, identified by email only – there is no password. Login happens
 * via a one-time confirmation link sent by email (see {@see UserSession}).
 * Deliberately not a {@see Member} – Members are reserved for CMS backend
 * admins and never created through the public registration flow. Since every
 * user has exactly one profile, the profile fields live directly on this
 * record.
 *
 * @property string $Email
 * @property string $Title
 * @property string $Handle
 * @property string $Bio
 * @property string $Species
 */
class User extends DataObject
{
    // Keeps the display name to what fits in 2 lines on the profile card
    // face (see .card-title's line-clamp in ProfileCard.scss).
    public const int TITLE_MAX_LENGTH = 30;

    private static string $table_name = 'AppUser';
    private static string $singular_name = 'User';
    private static string $plural_name = 'Users';

    private static array $db = [
        'Email' => 'Varchar(255)',
        'Title' => 'Varchar(255)',
        'Handle' => 'Varchar(255)',
        'Bio' => 'Text',
        'Species' => 'Varchar(255)',
    ];

    private static array $has_many = [
        'Sessions' => UserSession::class,
        'ProfileLinks' => ProfileLink::class,
    ];

    private static array $indexes = [
        'Email' => true,
        'Handle' => true,
    ];

    private static array $summary_fields = [
        'Email' => 'Email',
        'Title' => 'Name',
        'Handle' => 'Handle',
    ];

    private static string $default_sort = 'Email ASC';

    #[Override]
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->dataFieldByName('Bio')?->setRows(6);

        return $fields;
    }

    #[Override]
    public function summaryFields(): array
    {
        $fields = parent::summaryFields();
        $fields['Email'] = _t(__CLASS__ . '.SummaryEmail', 'Email');
        $fields['Title'] = _t(__CLASS__ . '.SummaryTitle', 'Name');
        $fields['Handle'] = _t(__CLASS__ . '.SummaryHandle', 'Handle');

        return $fields;
    }

    /**
     * Shape returned by the profile-facing APIs (public + internal listing).
     * Deliberately excludes Email. Every user's profile is public.
     *
     * @return array{id: int, title: string, handle: string, bio: string, species: string, links: array}
     */
    public function toApiData(): array
    {
        return [
            'id' => (int) $this->ID,
            'title' => (string) $this->Title,
            'handle' => (string) $this->Handle,
            'bio' => (string) $this->Bio,
            'species' => (string) $this->Species,
            'links' => array_map(
                static fn (ProfileLink $link): array => $link->toApiData(),
                iterator_to_array($this->ProfileLinks())
            ),
        ];
    }

    /**
     * Shape returned by /internal/me – the user's own data, including Email.
     *
     * @return array{id: int, email: string, title: string, handle: string, bio: string}
     */
    public function toOwnApiData(): array
    {
        return [...$this->toApiData(), 'email' => (string) $this->Email];
    }

    #[Override]
    public function canView($member = null)
    {
        return true;
    }

    #[Override]
    public function canEdit($member = null)
    {
        return Permission::checkMember($member ?: Security::getCurrentUser(), 'CMS_ACCESS_App\Admin\UserAdmin');
    }

    #[Override]
    public function canCreate($member = null, $context = [])
    {
        return $this->canEdit($member);
    }

    #[Override]
    public function canDelete($member = null)
    {
        return $this->canEdit($member);
    }
}
