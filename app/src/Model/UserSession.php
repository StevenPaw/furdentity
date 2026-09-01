<?php

namespace App\Model;

use Override;
use SilverStripe\ORM\DataObject;

/**
 * A login session for a {@see User}, created by the passwordless email-link
 * flow.
 *
 * Starts out unconfirmed, holding only the hash of the one-time confirmation
 * code emailed to the user. Once the user follows the link, the session is
 * marked confirmed and holds the hash of the current refresh token, which
 * ties subsequent /auth/refresh calls to this exact session – that's what
 * lets a user see their active sessions ("this device", "that phone") and
 * revoke one or all of them ("log out everywhere").
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
 * @property string $RefreshTokenHash
 * @property string $PreviousRefreshTokenHash
 * @property string $PreviousRefreshTokenGraceExpires
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
        'RefreshTokenHash' => 'Varchar(64)',
        // Lets a refresh token that was *just* rotated away from still be
        // accepted for a short grace window (see AuthController::refresh()).
        // Needed because two browser tabs can each independently notice an
        // expired access token and race their own /auth/refresh call with
        // the same (about-to-be-consumed) refresh token; without this, the
        // tab that loses the race gets a hard 401 for a token its own
        // browser session just legitimately used, and is logged out even
        // though the session itself is still perfectly valid.
        'PreviousRefreshTokenHash' => 'Varchar(64)',
        'PreviousRefreshTokenGraceExpires' => 'Datetime',
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

    #[Override]
    public function canView($member = null)
    {
        return false;
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
