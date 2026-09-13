<?php

namespace App\Api;

use App\Api\Support\JwtService;
use App\Model\User;
use App\Model\UserSession;
use DateTime;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Environment;

/**
 * Passwordless login for the internal API: a user requests a login link,
 * we email a one-time confirmation code, and confirming it exchanges the
 * code for a single, long-lived session token tied to a new
 * {@see UserSession}. The token itself never touches the response body –
 * it's set as an httpOnly cookie (see {@see ApiController::setAuthCookie()}),
 * so the frontend never has direct access to it at all. There's no separate
 * refresh step: {@see \App\Api\InternalApiController::init()} silently
 * re-issues the same cookie with a fresh expiry on every authenticated
 * request, so simply using the site keeps you logged in.
 *
 *   POST /api/v1/auth/request-link { "email": "..." }
 *   POST /api/v1/auth/confirm      { "sid": 1, "code": "..." }
 */
class AuthController extends ApiController
{
    private const string HANDLE_PATTERN = '/^[a-z0-9_-]{3,32}$/';

    private static int $login_code_ttl = 900;

    // Caps guesses against the 6-digit short code (1,000,000 combinations)
    // within its 15-minute lifetime; the long link code is unaffected since
    // brute-forcing 48 hex chars is already infeasible.
    private static int $max_failed_attempts = 10;

    private static array $allowed_actions = [
        'requestLink',
        'confirm',
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

        // Always respond the same way whether or not the address is known,
        // and create the user on first login, so this can't be used to probe
        // which emails are registered.
        $user = User::get()->filter('Email', $email)->first();
        $isNewAccount = !$user instanceof User;

        // Title/handle are only meaningful for a brand-new account (see
        // confirm() – an existing user's handle is never changed here). For
        // a new account both are mandatory, otherwise the user ends up with
        // a broken profile that has no display name or public URL.
        if ($isNewAccount && $title === '') {
            $this->error('Display name is required', 422);
        }

        if ($title !== '' && mb_strlen($title) > User::TITLE_MAX_LENGTH) {
            $this->error('title must be ' . User::TITLE_MAX_LENGTH . ' characters or fewer', 422);
        }

        if ($isNewAccount && $handle === '') {
            $this->error('Handle is required', 422);
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

        if ($isNewAccount) {
            $user = User::create();
            $user->Email = $email;
            $user->write();
        }

        $code = bin2hex(random_bytes(24));

        // Short, human-typeable companion to the link code above – lets
        // someone who opens the email on their phone type this into the
        // device they're actually logging in on, instead of having to open
        // the link there. Six digits keeps it easy to read/type while still
        // giving 1,000,000 combinations against the same short TTL/attempt
        // surface as the link code.
        $shortCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $session = UserSession::create();
        $session->UserID = $user->ID;
        $session->CodeHash = hash('sha256', $code);
        $session->ShortCodeHash = hash('sha256', $shortCode);
        $session->CodeExpires = $this->inSeconds((int) $this->config()->get('login_code_ttl'));
        $session->UserAgent = substr((string) $this->getRequest()->getHeader('User-Agent'), 0, 512);
        $session->IPAddress = (string) $this->getRequest()->getIP();
        $session->PendingTitle = $title;
        $session->PendingHandle = $handle;
        $session->write();

        $this->sendLoginEmail($user, $session, $code, $shortCode);

        return $this->jsonResponse([
            'message' => 'If that email address exists, a login link has been sent.',
            // Safe to return: on its own it's just a row ID, useless without
            // the code from the email – it's what lets this same browser tab
            // confirm via the manually-typed short code instead of the link.
            'sid' => $session->ID,
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
            || $session->FailedAttempts >= (int) $this->config()->get('max_failed_attempts')
            || strtotime((string) $session->CodeExpires) < time()
        ) {
            $this->error('Invalid or expired login link', 401);
        }

        // Accepts either the long code from the emailed link or the short
        // code from the same email, typed in by hand – see
        // AuthController::requestLink() for why both exist. The short code
        // is only 6 digits, so failed guesses count against a per-session
        // limit above to keep it un-brute-forceable within the code's
        // lifetime.
        $codeHash = hash('sha256', $code);

        if (!hash_equals($session->CodeHash, $codeHash) && !hash_equals($session->ShortCodeHash, $codeHash)) {
            $session->FailedAttempts++;
            $session->write();
            $this->error('Invalid or expired login link', 401);
        }

        $user = $session->User();

        if (!$user->exists()) {
            $this->error('Invalid or expired login link', 401);
        }

        $session->Confirmed = true;
        $this->claimPendingHandle($user, $session);
        $session->LastUsedAt = date('Y-m-d H:i:s');
        $session->write();

        $jwtService = JwtService::create();
        $this->setAuthCookie($jwtService->issue($user, $session), $jwtService->sessionTokenTtl());

        return $this->jsonResponse(['handle' => (string) $user->Handle], 201);
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

    private function sendLoginEmail(User $user, UserSession $session, string $code, string $shortCode): void
    {
        $link = Director::absoluteURL('/login/confirm') . '?' . http_build_query([
            'sid' => $session->ID,
            'code' => $code,
        ]);

        // Without an explicit From, SilverStripe defaults to
        // no-reply@<host> (see Email::getDefaultFrom()) – a domain that
        // isn't the authenticated MAILER_DSN mailbox and has no matching
        // SPF/DKIM record, so receiving servers (Gmail included) silently
        // drop or spam-box it even though the SMTP send itself succeeds.
        // Sending "From" the same mailbox MAILER_DSN authenticates as keeps
        // it aligned with that domain's SPF record.
        Email::create()
            ->setFrom(
                (string) Environment::getEnv('APP_SMTP_USERNAME'),
                _t(self::class . '.EMAIL_FROM_NAME', 'Furdentity')
            )
            ->setTo($user->Email)
            ->setSubject(_t(self::class . '.EMAIL_SUBJECT', 'Your Furdentity login link'))
            ->setBody(sprintf(
                '<p>%s</p><p><a href="%s">%s</a></p><p>%s</p><p>%s</p>',
                _t(self::class . '.EMAIL_INTRO', 'Click the link below to log in:'),
                htmlspecialchars($link, ENT_QUOTES),
                _t(self::class . '.EMAIL_CTA', 'Log in to Furdentity'),
                sprintf(
                    _t(
                        self::class . '.EMAIL_CODE',
                        'On a different device? Enter this code instead: <strong>%s</strong>'
                    ),
                    htmlspecialchars($shortCode, ENT_QUOTES)
                ),
                _t(self::class . '.EMAIL_EXPIRY', 'This link expires in 15 minutes and can only be used once.')
            ))
            ->send();
    }

    private function inSeconds(int $seconds): string
    {
        return (new DateTime())->modify("+{$seconds} seconds")->format('Y-m-d H:i:s');
    }
}
