<?php

namespace App\Model;

use Override;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Director;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
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
 * @property string $CardMainColor
 * @property string $CardSecondaryColor
 * @property string $AvatarShape
 * @property string $Visibility
 */
class User extends DataObject
{
    // Keeps the display name to what fits in 2 lines on the profile card
    // face (see .card-title's line-clamp in ProfileCard.scss).
    public const int TITLE_MAX_LENGTH = 30;

    private static string $table_name = 'AppUser';
    private static string $singular_name = 'User';
    private static string $plural_name = 'Users';

    // Kept as an opaque, un-validated string (like ProfileLink::$Platform) –
    // the canonical list of valid keys lives entirely in the frontend's flag
    // registry (frontend/src/utils/flags.js), same "trust the client's enum,
    // just cap the length" approach as social platform keys.
    public const int FLAG_MAX_LENGTH = 32;

    // Kept as opaque, un-validated strings too – just capped to fit a
    // '#rrggbb' hex color (or empty for "no secondary color set", i.e. solid
    // color mode instead of a gradient). The frontend's <input type="color">
    // is the only producer of these values.
    public const int CARD_COLOR_MAX_LENGTH = 9;

    // The only valid AvatarShape values – anything else sent by a client is
    // rejected outright rather than merely length-capped (unlike the opaque
    // flag/color strings above), since this drives a fixed set of CSS rules
    // on both the profile card and the crop overlay.
    public const string AVATAR_SHAPE_CIRCLE = 'circle';
    public const string AVATAR_SHAPE_ROUNDED_SQUARE = 'rounded-square';
    public const string AVATAR_SHAPE_SQUARE = 'square';
    public const string AVATAR_SHAPE_HEXAGON = 'hexagon';
    public const array AVATAR_SHAPES = [
        self::AVATAR_SHAPE_CIRCLE,
        self::AVATAR_SHAPE_ROUNDED_SQUARE,
        self::AVATAR_SHAPE_SQUARE,
        self::AVATAR_SHAPE_HEXAGON,
    ];

    // "public" – shown on the card face, the public profile endpoint, AND
    // eligible for the homepage's random-profiles carousel.
    // "unlisted" – same as public except never picked for that carousel;
    // still fully viewable by anyone with the direct /id/{handle} link.
    // "hidden" – the public profile endpoint refuses it outright (see
    // PublicApiController::profile()); only the owner, viewing their own
    // profile while authenticated, ever sees it.
    public const string VISIBILITY_PUBLIC = 'public';
    public const string VISIBILITY_UNLISTED = 'unlisted';
    public const string VISIBILITY_HIDDEN = 'hidden';
    public const array VISIBILITIES = [
        self::VISIBILITY_PUBLIC,
        self::VISIBILITY_UNLISTED,
        self::VISIBILITY_HIDDEN,
    ];

    private static array $db = [
        'Email' => 'Varchar(255)',
        'Title' => 'Varchar(255)',
        'Handle' => 'Varchar(255)',
        'Bio' => 'Text',
        'Species' => 'Varchar(255)',
        'FlagLeft' => 'Varchar(32)',
        'FlagRight' => 'Varchar(32)',
        'CardMainColor' => 'Varchar(9)',
        'CardSecondaryColor' => 'Varchar(9)',
        'AvatarShape' => 'Varchar(20)',
        'Visibility' => 'Varchar(20)',
    ];

    private static array $has_one = [
        'AvatarImage' => Image::class,
        'BackgroundImage' => Image::class,
        // JPEG sibling of AvatarImage, written alongside it by
        // ProfileImageStore::store() for a later API consumer that needs a
        // JPEG rather than the PNG the profile itself renders. Background
        // has no PNG variant, so it needs no JPEG sibling either.
        'AvatarImageJpeg' => Image::class,
    ];

    private static array $owns = [
        'AvatarImage',
        'BackgroundImage',
        'AvatarImageJpeg',
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
        'Title' => 'Name',
        'Handle' => 'Handle',
        'Email' => 'Email',
    ];

    private static string $default_sort = 'Email ASC';

    /**
     * Backfills Visibility for rows written before that column existed –
     * without this, PublicApiController's `filter('Visibility', 'public')`
     * queries would silently exclude every pre-existing user (toApiData()'s
     * `?: self::VISIBILITY_PUBLIC` fallback only helps once a row has
     * already been loaded, not for a WHERE clause matching against it).
     */
    #[Override]
    public function requireDefaultRecords(): void
    {
        parent::requireDefaultRecords();

        DB::prepared_query(
            'UPDATE "AppUser" SET "Visibility" = ? WHERE "Visibility" IS NULL OR "Visibility" = \'\'',
            [self::VISIBILITY_PUBLIC]
        );
    }

    #[Override]
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->dataFieldByName('Bio')?->setRows(6);

        if ($this->Handle !== '' && $this->Handle !== null) {
            $profileUrl = Director::absoluteURL('/id/' . rawurlencode($this->Handle));
            $fields->addFieldToTab('Root.Main', LiteralField::create(
                'ProfileLinkPreview',
                sprintf(
                    '<div class="field"><a href="%1$s" target="_blank" rel="noopener noreferrer">%1$s</a></div>',
                    htmlspecialchars($profileUrl, ENT_QUOTES)
                )
            ), 'Email');
        }

        $fields->addFieldToTab('Root.Main', UploadField::create('AvatarImage', 'Avatar')
            ->setFolderName('avatars')
            ->setAllowedFileCategories('image'));
        $fields->addFieldToTab('Root.Main', UploadField::create('BackgroundImage', 'Background image')
            ->setFolderName('backgrounds')
            ->setAllowedFileCategories('image'));

        $fields->removeByName(['ProfileLinks', 'Sessions']);

        $fields->addFieldToTab('Root.ProfileLinks', GridField::create(
            'ProfileLinks',
            'Profile Links',
            $this->ProfileLinks(),
            GridFieldConfig_RecordEditor::create()
        ));

        $sessionsConfig = GridFieldConfig_RecordEditor::create();
        // Sessions only ever come from the login flow, never created by hand.
        $sessionsConfig->removeComponentsByType(GridFieldAddNewButton::class);
        $fields->addFieldToTab('Root.Sessions', GridField::create(
            'Sessions',
            'Sessions',
            $this->Sessions(),
            $sessionsConfig
        ));

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
     * Deliberately excludes Email. Callers are responsible for their own
     * visibility gate before handing this out – see
     * {@see \App\Api\PublicApiController::profile()}, which refuses to call
     * this at all for a {@see self::VISIBILITY_HIDDEN} profile.
     *
     * @return array{id: int, title: string, handle: string, bio: string, species: string, avatarUrl: ?string, backgroundUrl: ?string, flagLeft: ?string, flagRight: ?string, mainColor: ?string, secondaryColor: ?string, avatarShape: string, visibility: string, links: array}
     */
    public function toApiData(): array
    {
        return [
            'id' => (int) $this->ID,
            'title' => (string) $this->Title,
            'handle' => (string) $this->Handle,
            'bio' => (string) $this->Bio,
            'species' => (string) $this->Species,
            'avatarUrl' => $this->avatarUrl(),
            'backgroundUrl' => $this->backgroundUrl(),
            'flagLeft' => (string) $this->FlagLeft ?: null,
            'flagRight' => (string) $this->FlagRight ?: null,
            'mainColor' => (string) $this->CardMainColor ?: null,
            'secondaryColor' => (string) $this->CardSecondaryColor ?: null,
            'avatarShape' => (string) $this->AvatarShape ?: self::AVATAR_SHAPE_CIRCLE,
            'visibility' => (string) $this->Visibility ?: self::VISIBILITY_PUBLIC,
            'links' => array_map(
                static fn (ProfileLink $link): array => $link->toApiData(),
                iterator_to_array($this->ProfileLinks())
            ),
        ];
    }

    /**
     * Both the profile card's own self-service crop/upload flow (see
     * {@see \App\Api\Support\ProfileImageStore}) and a CMS admin's
     * {@see \SilverStripe\AssetAdmin\Forms\UploadField} on
     * {@see self::getCMSFields()} write to this same relation, so whichever
     * one was used last is simply what's here.
     */
    public function avatarUrl(): ?string
    {
        return self::cacheBustedUrl($this->AvatarImage());
    }

    public function backgroundUrl(): ?string
    {
        return self::cacheBustedUrl($this->BackgroundImage());
    }

    /**
     * Not currently surfaced via toApiData()/toOwnApiData() – kept for a
     * future API consumer that needs a JPEG rather than the PNG the profile
     * itself renders.
     */
    public function avatarJpegUrl(): ?string
    {
        return self::cacheBustedUrl($this->AvatarImageJpeg());
    }

    /**
     * The image lives at a fixed, filename-stable path (see
     * {@see \App\Api\Support\ProfileImageStore}), so a re-upload keeps the
     * exact same URL – without a cache-buster, a browser or CDN that already
     * cached the old content would never see the new one.
     */
    private static function cacheBustedUrl(Image $file): ?string
    {
        if (!$file->exists()) {
            return null;
        }

        return $file->getAbsoluteURL() . '?v=' . $file->Version;
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
