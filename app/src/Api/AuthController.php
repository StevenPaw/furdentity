<?php

namespace App\Api;

use App\Api\Support\JwtService;
use App\Model\User;
use App\Model\UserSession;
use DateTime;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTPResponse;
use Throwable;

/**
 * Passwordless login for the internal API: a user requests a login link,
 * we email a one-time confirmation code, and confirming it exchanges the
 * code for a JWT pair tied to a new {@see UserSession}. The tokens
 * themselves never touch the response body – they're set as httpOnly
 * cookies (see {@see ApiController::setAuthCookies()}), so the frontend
 * never has direct access to them at all.
 *
 *   POST /api/v1/auth/request-link { "email": "..." }
 *   POST /api/v1/auth/confirm      { "sid": 1, "code": "..." }
 *   POST /api/v1/auth/refresh      (refresh token read from its cookie)
 */
class AuthController extends ApiController
{
    private const string HANDLE_PATTERN = '/^[a-z0-9_-]{3,32}$/';

    // How long a just-rotated-away-from refresh token is still accepted
    // (see refresh()) – covers two tabs racing a refresh with the same
    // about-to-be-consumed token, without meaningfully weakening security:
    // a stolen token only ever benefits from one rotation's worth of grace.
    private const int REFRESH_GRACE_SECONDS = 10;

    private static int $login_code_ttl = 900;

    private static array $allowed_actions = [
        'requestLink',
        'confirm',
        'refresh',
    ];

    private static array $url_handlers = [
        'request-link' => 'requestLink',
    ];

    public function requestLink(): HTTPResponse
    {
        if (!$this->getRequest()->isPOST()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->jsonBody();
        $email = trim((string) ($body['email'] ?? ''));
        $title = trim((string) ($body['title'] ?? ''));
        $handle = strtolower(trim((string) ($body['handle'] ?? '')));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid email address is required', 422);
        }

        // Handle/title are only meaningful for a brand-new account (see
        // confirm() – an existing user's handle is never changed here), but
        // we validate them up front so the registration form gets an
        // immediate, useful error instead of a silently-ignored value.
        if ($title !== '' && mb_strlen($title) > User::TITLE_MAX_LENGTH) {
            $this->error('title must be ' . User::TITLE_MAX_LENGTH . ' characters or fewer', 422);
        }

        if ($handle !== '') {
            if (!preg_match(self::HANDLE_PATTERN, $handle)) {
                $this->error(
                    'Handle must be 3-32 characters, using only lowercase letters, numbers, "-" or "_"',
                    422
                );
            }

            if (User::get()->filter('Handle', $handle)->exists()) {
                $this->error('That handle is already taken', 409);
            }
        }

        // Always respond the same way whether or not the address is known,
        // and create the user on first login, so this can't be used to probe
        // which emails are registered.
        $user = User::get()->filter('Email', $email)->first();

        if (!$user instanceof User) {
            $user = User::create();
            $user->Email = $email;
            $user->write();
        }

        $code = bin2hex(random_bytes(24));

        $session = UserSession::create();
        $session->UserID = $user->ID;
        $session->CodeHash = hash('sha256', $code);
        $session->CodeExpires = $this->inSeconds((int) $this->config()->get('login_code_ttl'));
        $session->UserAgent = substr((string) $this->getRequest()->getHeader('User-Agent'), 0, 512);
        $session->IPAddress = (string) $this->getRequest()->getIP();
        $session->PendingTitle = $title;
        $session->PendingHandle = $handle;
        $session->write();

        $this->sendLoginEmail($user, $session, $code);

        return $this->jsonResponse([
            'message' => 'If that email address exists, a login link has been sent.',
        ]);
    }

    public function confirm(): HTTPResponse
    {
        if (!$this->getRequest()->isPOST()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->jsonBody();
        $sessionId = (int) ($body['sid'] ?? 0);
        $code = trim((string) ($body['code'] ?? ''));

        if ($sessionId <= 0 || $code === '') {
            $this->error('sid and code are required', 422);
        }

        $session = UserSession::get()->byID($sessionId);

        if (
            !$session instanceof UserSession
            || $session->Confirmed
            || $session->RevokedAt
            || !hash_equals($session->CodeHash, hash('sha256', $code))
            || strtotime((string) $session->CodeExpires) < time()
        ) {
            $this->error('Invalid or expired login link', 401);
        }

        $user = $session->User();

        if (!$user->exists()) {
            $this->error('Invalid or expired login link', 401);
        }

        $session->Confirmed = true;
        $this->claimPendingHandle($user, $session);

        return $this->jsonResponse($this->rotateAndIssue($user, $session), 201);
    }

    public function refresh(): HTTPResponse
    {
        if (!$this->getRequest()->isPOST()) {
            $this->error('Method not allowed', 405);
        }

        $token = (string) Cookie::get(self::COOKIE_REFRESH);

        if ($token === '') {
            $this->error('Missing refresh token', 401);
        }

        try {
            $payload = JwtService::create()->decode($token, 'refresh');
        } catch (Throwable) {
            $this->error('Invalid or expired refresh token', 401);
        }

        $session = UserSession::get()->byID((int) $payload->sid);
        $jti = (string) ($payload->jti ?? '');
        $jtiHash = hash('sha256', $jti);

        $isCurrent = $session instanceof UserSession && hash_equals((string) $session->RefreshTokenHash, $jtiHash);
        $isRecentPrevious = $session instanceof UserSession
            && (string) $session->PreviousRefreshTokenHash !== ''
            && hash_equals((string) $session->PreviousRefreshTokenHash, $jtiHash)
            && strtotime((string) $session->PreviousRefreshTokenGraceExpires) > time();

        if (
            !$session instanceof UserSession
            || !$session->Confirmed
            || $session->RevokedAt
            || (!$isCurrent && !$isRecentPrevious)
        ) {
            $this->error('Invalid or expired refresh token', 401);
        }

        $user = $session->User();

        if (!$user->exists()) {
            $this->error('Invalid or expired refresh token', 401);
        }

        return $this->jsonResponse($this->rotateAndIssue($user, $session));
    }

    /**
     * Applies the handle/title chosen at registration – but only the first
     * time, and only if the handle hasn't been claimed by someone else in
     * the meantime. Once a user has a Handle, this never touches it again;
     * from then on only a CMS admin can change it.
     */
    private function claimPendingHandle(User $user, UserSession $session): void
    {
        if ((string) $user->Handle !== '' || $session->PendingHandle === '') {
            return;
        }

        if (User::get()->filter('Handle', $session->PendingHandle)->exclude('ID', $user->ID)->exists()) {
            return;
        }

        $user->Handle = $session->PendingHandle;
        $user->Title = $session->PendingTitle !== '' ? $session->PendingTitle : $session->PendingHandle;
        $user->write();
    }

    /**
     * Rotates the session's refresh-token hash and issues a fresh token
     * pair for it, set as httpOnly cookies (see {@see ApiController}). Used
     * both when a login link is confirmed and on every subsequent refresh,
     * so a stolen refresh token stops working once the legitimate client
     * rotates past it – modulo the short grace window in refresh() for
     * same-browser, multi-tab races.
     *
     * @return array{handle: string}
     */
    private function rotateAndIssue(User $user, UserSession $session): array
    {
        $jti = bin2hex(random_bytes(32));

        $session->PreviousRefreshTokenHash = $session->RefreshTokenHash;
        $session->PreviousRefreshTokenGraceExpires = $this->inSeconds(self::REFRESH_GRACE_SECONDS);
        $session->RefreshTokenHash = hash('sha256', $jti);
        $session->LastUsedAt = date('Y-m-d H:i:s');
        $session->write();

        $jwtService = JwtService::create();
        $pair = $jwtService->issueTokenPair($user, $session, $jti);
        $this->setAuthCookies($pair['token'], $pair['refreshToken'], $pair['expiresIn'], $jwtService->refreshTokenTtl());

        return ['handle' => (string) $user->Handle];
    }

    private function sendLoginEmail(User $user, UserSession $session, string $code): void
    {
        $link = Director::absoluteURL('/login/confirm') . '?' . http_build_query([
            'sid' => $session->ID,
            'code' => $code,
        ]);

        Email::create()
            ->setTo($user->Email)
            ->setSubject(_t(self::class . '.EMAIL_SUBJECT', 'Your Furdentity login link'))
            ->setBody(sprintf(
                '<p>%s</p><p><a href="%s">%s</a></p><p>%s</p>',
                _t(self::class . '.EMAIL_INTRO', 'Click the link below to log in:'),
                htmlspecialchars($link, ENT_QUOTES),
                _t(self::class . '.EMAIL_CTA', 'Log in to Furdentity'),
                _t(self::class . '.EMAIL_EXPIRY', 'This link expires in 15 minutes and can only be used once.')
            ))
            ->send();
    }

    private function inSeconds(int $seconds): string
    {
        return (new DateTime())->modify("+{$seconds} seconds")->format('Y-m-d H:i:s');
    }
}
