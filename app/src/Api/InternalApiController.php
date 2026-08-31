<?php

namespace App\Api;

use Override;
use App\Api\Support\JwtService;
use App\Model\User;
use App\Model\UserSession;
use SilverStripe\Control\HTTPResponse;
use Throwable;

/**
 * Internal API that feeds the own Vue frontend. Every request must carry a
 * valid access token: "Authorization: Bearer <jwt>". Obtain one from the
 * passwordless login flow (POST /api/v1/auth/request-link + /confirm).
 *
 * Authenticates against {@see User} (the app's own user model), never
 * against SilverStripe Member/Security – those are reserved for CMS admins.
 *
 *   GET    /api/v1/internal/me
 *   PATCH  /api/v1/internal/me    { "title"?: "...", "bio"?: "..." } – Handle
 *                                 is deliberately not editable here; once set
 *                                 at registration only a CMS admin can change it.
 *   GET    /api/v1/internal/profiles
 *   GET    /api/v1/internal/sessions
 *   DELETE /api/v1/internal/sessions/$ID
 *   POST   /api/v1/internal/sessions/logout-all
 */
class InternalApiController extends ApiController
{
    private static array $url_handlers = [
        'me' => 'currentUser',
        'profiles' => 'profiles',
        'sessions/logout-all' => 'logoutAllSessions',
        'sessions/$ID!' => 'revokeSession',
        'sessions' => 'sessions',
    ];

    private static array $allowed_actions = [
        'currentUser',
        'profiles',
        'sessions',
        'revokeSession',
        'logoutAllSessions',
    ];

    private ?User $authenticatedUser = null;

    private ?UserSession $currentSession = null;

    #[Override]
    protected function init(): void
    {
        parent::init();

        $header = (string) $this->getRequest()->getHeader('Authorization');

        if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
            $this->error('Missing bearer token', 401);
        }

        try {
            $payload = JwtService::create()->decode($matches[1], 'access');
        } catch (Throwable) {
            $this->error('Invalid or expired token', 401);
        }

        $session = UserSession::get()->byID((int) $payload->sid);

        if (!$session instanceof UserSession || !$session->Confirmed || $session->RevokedAt) {
            $this->error('Invalid or expired token', 401);
        }

        $user = $session->User();

        if (!$user->exists()) {
            $this->error('Invalid or expired token', 401);
        }

        $this->authenticatedUser = $user;
        $this->currentSession = $session;
    }

    public function currentUser(): HTTPResponse
    {
        if ($this->getRequest()->httpMethod() === 'PATCH') {
            return $this->updateCurrentUser();
        }

        return $this->jsonResponse($this->authenticatedUser->toOwnApiData());
    }

    /**
     * Deliberately only accepts title/bio – Handle is permanent once set at
     * registration and from then on only editable by a CMS admin.
     */
    private function updateCurrentUser(): HTTPResponse
    {
        $body = $this->jsonBody();
        $user = $this->authenticatedUser;

        if (array_key_exists('title', $body)) {
            $title = trim((string) $body['title']);

            if ($title === '') {
                $this->error('title cannot be empty', 422);
            }

            $user->Title = $title;
        }

        if (array_key_exists('bio', $body)) {
            $user->Bio = (string) $body['bio'];
        }

        $user->write();

        return $this->jsonResponse($user->toOwnApiData());
    }

    public function profiles(): HTTPResponse
    {
        $data = [];

        foreach (User::get() as $user) {
            $data[] = $user->toApiData();
        }

        return $this->jsonResponse(['data' => $data]);
    }

    public function sessions(): HTTPResponse
    {
        $data = [];

        foreach ($this->authenticatedUser->Sessions()->filter(['Confirmed' => true, 'RevokedAt' => null]) as $session) {
            $data[] = [
                'id' => (int) $session->ID,
                'userAgent' => (string) $session->UserAgent,
                'ipAddress' => (string) $session->IPAddress,
                'createdAt' => (string) $session->Created,
                'lastUsedAt' => (string) $session->LastUsedAt,
                'current' => (int) $session->ID === (int) $this->currentSession->ID,
            ];
        }

        return $this->jsonResponse(['data' => $data]);
    }

    public function revokeSession(): HTTPResponse
    {
        if (!$this->getRequest()->isDELETE()) {
            $this->error('Method not allowed', 405);
        }

        $id = (int) $this->getRequest()->param('ID');
        $session = $this->authenticatedUser->Sessions()->byID($id);

        if (!$session instanceof UserSession) {
            $this->error('Session not found', 404);
        }

        $session->RevokedAt = date('Y-m-d H:i:s');
        $session->write();

        return $this->jsonResponse(['revoked' => true]);
    }

    public function logoutAllSessions(): HTTPResponse
    {
        if (!$this->getRequest()->isPOST()) {
            $this->error('Method not allowed', 405);
        }

        foreach ($this->authenticatedUser->Sessions()->filter('RevokedAt', null) as $session) {
            $session->RevokedAt = date('Y-m-d H:i:s');
            $session->write();
        }

        return $this->jsonResponse(['revoked' => true]);
    }
}
