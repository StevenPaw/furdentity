<?php

namespace App\Model;

use Override;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * A login session for a {@see User}, created by the passwordless email-link
 * flow.
 *
 * Starts out unconfirmed, holding only the hash of the one-time confirmation
 * code emailed to the user. Once the user follows the link, the session is
 * marked confirmed and a single long-lived session token (see
 * {@see \App\Api\Support\JwtService}) is issued for it, carrying this
 * session's ID – that's what lets a user see their active sessions ("this
 * device", "that phone") and revoke one or all of them ("log out
 * everywhere"). The token is silently re-issued (same session, fresh
 * expiry) on every authenticated request – see
 * {@see \App\Api\InternalApiController::init()} – so "stay logged in" really
 * means "stay logged in as long as you use the site at least once within the
 * token's lifetime", not a hard cutoff.
 *
 * PendingTitle/PendingHandle carry the display name and handle a brand-new
 * user chose on the registration form. They're applied to the User once
 * (and only once – see {@see \App\Api\AuthController::confirm()}) when the
 * link is confirmed; an existing user's Handle is never touched by them.
 *
 * Only ever read/written by our own controller code, never exposed via the
 * CMS or a public API endpoint.
 *
 * @property string $CodeHash
 * @property string $CodeExpires
 * @property bool $Confirmed
 * @property string $UserAgent
 * @property string $IPAddress
 * @property string $LastUsedAt
 * @property ?string $RevokedAt
 * @property string $PendingTitle
 * @property string $PendingHandle
 * @property int $UserID
 * @method User User()
 */
class UserSession extends DataObject
{
    private static string $table_name = 'UserSession';
    private static string $singular_name = 'Session';
    private static string $plural_name = 'Sessions';

    private static array $db = [
        'CodeHash' => 'Varchar(64)',
        'CodeExpires' => 'Datetime',
        'Confirmed' => 'Boolean(0)',
        'UserAgent' => 'Varchar(512)',
        'IPAddress' => 'Varchar(64)',
        'LastUsedAt' => 'Datetime',
        'RevokedAt' => 'Datetime',
        'PendingTitle' => 'Varchar(255)',
        'PendingHandle' => 'Varchar(64)',
    ];

    private static array $has_one = [
        'User' => User::class,
    ];

    private static array $indexes = [
        'UserID' => true,
    ];

    private static array $summary_fields = [
        'Status' => 'Status',
        'UserAgent' => 'User Agent',
        'IPAddress' => 'IP Address',
        'LastUsedAt' => 'Last used',
        'Created' => 'Created',
    ];

    /**
     * "pending" until the one-time login link is confirmed, "accepted" from
     * then on (until revoked). Not a stored column – Confirmed/RevokedAt are
     * the source of truth (see the class doc comment for why).
     */
    public function getStatus(): string
    {
        if ($this->RevokedAt) {
            return 'revoked';
        }

        return $this->Confirmed ? 'accepted' : 'pending';
    }

    /**
     * Only RevokedAt is left editable, letting a CMS admin manually revoke a
     * session; every other field is either a secret hash or only meaningful
     * as set by the login flow itself, never by hand.
     */
    #[Override]
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        foreach ([
            'CodeHash',
            'CodeExpires',
            'Confirmed',
            'UserAgent',
            'IPAddress',
            'LastUsedAt',
            'PendingTitle',
            'PendingHandle',
            'UserID',
        ] as $name) {
            $fields->dataFieldByName($name)?->setReadonly(true);
        }

        return $fields;
    }

    /**
     * Read-only in the CMS other than manually revoking (see
     * {@see self::getCMSFields()}) or deleting a session outright – sessions
     * are otherwise only ever created/edited by our own controller code via
     * the login flow, never through the CMS or a public API endpoint.
     */
    #[Override]
    public function canView($member = null)
    {
        return Permission::checkMember($member ?: Security::getCurrentUser(), 'CMS_ACCESS_App\Admin\UserAdmin');
    }

    #[Override]
    public function canEdit($member = null)
    {
        return $this->canView($member);
    }

    #[Override]
    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    #[Override]
    public function canDelete($member = null)
    {
        return $this->canView($member);
    }
}
