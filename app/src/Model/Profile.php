<?php

namespace App\Model;

use Override;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * Example DataObject so the CMS and both APIs have real data to work with.
 *
 * This is scaffolding – replace it with the real furry-identity models.
 *
 * @property string $Title
 * @property string $Handle
 * @property string $Bio
 * @property bool $IsPublic
 */
class Profile extends DataObject
{
    private static string $table_name = 'Profile';

    private static string $singular_name = 'Profil';

    private static string $plural_name = 'Profile';

    private static array $db = [
        'Title' => 'Varchar(255)',
        'Handle' => 'Varchar(255)',
        'Bio' => 'Text',
        'IsPublic' => 'Boolean(0)',
    ];

    private static array $indexes = [
        'Handle' => true,
    ];

    private static array $summary_fields = [
        'Title' => 'Name',
        'Handle' => 'Handle',
        'IsPublic.Nice' => 'Öffentlich',
    ];

    private static string $default_sort = 'Title ASC';

    #[Override]
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->dataFieldByName('Bio')?->setRows(6);

        return $fields;
    }

    /**
     * Shape returned by the APIs.
     *
     * @return array{id: int, title: string, handle: string, bio: string, isPublic: bool}
     */
    public function toApiData(): array
    {
        return [
            'id' => (int) $this->ID,
            'title' => (string) $this->Title,
            'handle' => (string) $this->Handle,
            'bio' => (string) $this->Bio,
            'isPublic' => (bool) $this->IsPublic,
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
        return Permission::checkMember($member ?: Security::getCurrentUser(), 'CMS_ACCESS_App\Admin\ProfileAdmin');
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
