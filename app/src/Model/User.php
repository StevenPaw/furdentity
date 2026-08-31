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
 */
class User extends DataObject
{
    private static string $table_name = 'AppUser';
    private static string $singular_name = 'User';
    private static string $plural_name = 'Users';

    private static array $db = [
        'Email' => 'Varchar(255)',
        'Title' => 'Varchar(255)',
        'Handle' => 'Varchar(255)',
        'Bio' => 'Text',
    ];

    private static array $has_many = [
        'Sessions' => UserSession::class,
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
     * @return array{id: int, title: string, handle: string, bio: string}
     */
    public function toApiData(): array
    {
        return [
            'id' => (int) $this->ID,
            'title' => (string) $this->Title,
            'handle' => (string) $this->Handle,
            'bio' => (string) $this->Bio,
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
