<?php

namespace App\Api;

use Override;
use App\Api\Support\JwtService;
use App\Api\Support\ProfileImageException;
use App\Api\Support\ProfileImageStore;
use App\Model\ProfileLink;
use App\Model\User;
use App\Model\UserSession;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\HTTPResponse;
use Throwable;

/**
 * Internal API that feeds the own Vue frontend. Every request must carry a
 * valid session token – sent automatically as the httpOnly
 * "furdentity_session" cookie (see {@see ApiController::setAuthCookie()}),
 * never a header the frontend has to manage itself. Obtain one from the
 * passwordless login flow (POST /api/v1/auth/request-link + /confirm).
 * {@see self::init()} re-issues it with a fresh expiry on every request, so
 * there's no separate refresh step – simply using the site keeps it alive.
 *
 * Authenticates against {@see User} (the app's own user model), never
 * against SilverStripe Member/Security – those are reserved for CMS admins.
 *
 *   GET    /api/v1/internal/me
 *   PATCH  /api/v1/internal/me    { "title"?: "...", "bio"?: "...", "species"?: "...",
 *                                   "mainColor"?: "#rrggbb", "secondaryColor"?: "#rrggbb",
 *                                   "avatarShape"?: "circle"|"rounded-square"|"square"|"hexagon",
 *                                   "visibility"?: "public"|"unlisted"|"hidden" } –
 *                                 Handle is deliberately not editable here; once set at
 *                                 registration only a CMS admin can change it. An empty
 *                                 "secondaryColor" clears it, switching the card to solid-color
 *                                 mode instead of a gradient. "visibility" gates the public
 *                                 profile endpoint (see {@see PublicApiController::profile()})
 *                                 and the homepage's random-profiles carousel.
 *   DELETE /api/v1/internal/me    { "handle": "..." } – must match exactly, deletes the account
 *   POST   /api/v1/internal/me/avatar      { "image": "data:image/jpeg;base64,..." } – replaces
 *                                          the avatar shown on the profile card; already cropped
 *                                          to 1:1 client-side, resized server-side (see
 *                                          {@see \App\Api\Support\ProfileImageStore}).
 *   POST   /api/v1/internal/me/background  Same shape, for the card's background image
 *                                          (cropped to 9:4 client-side).
 *   POST   /api/v1/internal/logout
 *   GET    /api/v1/internal/sessions
 *   DELETE /api/v1/internal/sessions/$ID
 *   POST   /api/v1/internal/sessions/logout-all
 *   GET    /api/v1/internal/links
 *   POST   /api/v1/internal/links           { "url": "...", "title"?: "...", "platform"?: "...",
 *                                            "placement"?: "below"|"card" } – "card" places the
 *                                            link in one of the 3 slots shown on the card face
 *                                            itself, capped at 3, independent of the "below" list.
 *   PATCH  /api/v1/internal/links/$ID       { "url"?: "...", "title"?: "...", "platform"?: "..." }
 *   DELETE /api/v1/internal/links/$ID
 *   POST   /api/v1/internal/links/reorder   { "order": [linkId, linkId, ...] }
 */
class InternalApiController extends ApiController
{
    private static array $url_handlers = [
        'me/avatar' => 'uploadAvatar',
        'me/background' => 'uploadBackground',
        'me' => 'currentUser',
        'logout' => 'logout',
        'sessions/logout-all' => 'logoutAllSessions',
        'sessions/$ID!' => 'revokeSession',
        'sessions' => 'sessions',
        'links/reorder' => 'reorderLinks',
        'links/$ID!' => 'linkItem',
        'links' => 'links',
    ];

    private static array $allowed_actions = [
        'currentUser',
        'uploadAvatar',
        'uploadBackground',
        'logout',
        'sessions',
        'revokeSession',
        'logoutAllSessions',
        'links',
        'linkItem',
        'reorderLinks',
    ];

    private ?User $authenticatedUser = null;

    private ?UserSession $currentSession = null;

    #[Override]
    protected function init(): void
    {
        parent::init();

        $token = (string) Cookie::get(self::COOKIE_SESSION);

        if ($token === '') {
            $this->error('Missing session token', 401);
        }

        try {
            $payload = JwtService::create()->decode($token);
        } catch (Throwable) {
            // Unlike the old access/refresh split, there's no rotation here
            // – an invalid token is unambiguously dead, never just "another
            // tab already moved past it", so it's always safe to clear it.
            $this->clearAuthCookie();
            $this->error('Invalid or expired session', 401);
        }

        $session = UserSession::get()->byID((int) $payload->sid);

        if (!$session instanceof UserSession || !$session->Confirmed || $session->RevokedAt) {
            $this->clearAuthCookie();
            $this->error('Invalid or expired session', 401);
        }

        $user = $session->User();

        if (!$user->exists()) {
            $this->clearAuthCookie();
            $this->error('Invalid or expired session', 401);
        }

        $this->authenticatedUser = $user;
        $this->currentSession = $session;

        // Sliding expiration: every authenticated request keeps the session
        // alive for another full session_token_ttl, and LastUsedAt reflects
        // actual usage for the "Sessions" list – this is the entire
        // "stay logged in" mechanism, no separate /auth/refresh needed.
        $session->LastUsedAt = date('Y-m-d H:i:s');
        $session->write();

        $jwtService = JwtService::create();
        $this->setAuthCookie($jwtService->issue($user, $session), $jwtService->sessionTokenTtl());
    }

    public function currentUser(): HTTPResponse
    {
        $method = $this->getRequest()->httpMethod();

        if ($method === 'PATCH') {
            return $this->updateCurrentUser();
        }

        if ($method === 'DELETE') {
            return $this->deleteCurrentUser();
        }

        return $this->jsonResponse($this->authenticatedUser->toOwnApiData());
    }

    /**
     * Deliberately only accepts title/bio/species/flagLeft/flagRight –
     * Handle is permanent once set at registration and from then on only
     * editable by a CMS admin.
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

            if (mb_strlen($title) > User::TITLE_MAX_LENGTH) {
                $this->error('title must be ' . User::TITLE_MAX_LENGTH . ' characters or fewer', 422);
            }

            $user->Title = $title;
        }

        if (array_key_exists('bio', $body)) {
            $user->Bio = (string) $body['bio'];
        }

        if (array_key_exists('species', $body)) {
            $user->Species = (string) $body['species'];
        }

        // Empty string clears the slot – the frontend's flag picker sends
        // this for "remove flag" rather than a separate DELETE-style call.
        if (array_key_exists('flagLeft', $body)) {
            $user->FlagLeft = $this->validatedFlagKey($body['flagLeft']);
        }

        if (array_key_exists('flagRight', $body)) {
            $user->FlagRight = $this->validatedFlagKey($body['flagRight']);
        }

        // Empty "secondaryColor" clears it, meaning "solid color mode" –
        // mirrors the flag fields' "empty string clears the slot" convention.
        if (array_key_exists('mainColor', $body)) {
            $user->CardMainColor = $this->validatedColor($body['mainColor']);
        }

        if (array_key_exists('secondaryColor', $body)) {
            $user->CardSecondaryColor = $this->validatedColor($body['secondaryColor']);
        }

        if (array_key_exists('avatarShape', $body)) {
            $user->AvatarShape = $this->validatedAvatarShape($body['avatarShape']);
        }

        if (array_key_exists('visibility', $body)) {
            $user->Visibility = $this->validatedVisibility($body['visibility']);
        }

        $user->write();

        return $this->jsonResponse($user->toOwnApiData());
    }

    private function validatedFlagKey(mixed $value): string
    {
        $key = trim((string) $value);

        if (mb_strlen($key) > User::FLAG_MAX_LENGTH) {
            $this->error('flag key must be ' . User::FLAG_MAX_LENGTH . ' characters or fewer', 422);
        }

        return $key;
    }

    private function validatedColor(mixed $value): string
    {
        $color = trim((string) $value);

        if (mb_strlen($color) > User::CARD_COLOR_MAX_LENGTH) {
            $this->error('color must be ' . User::CARD_COLOR_MAX_LENGTH . ' characters or fewer', 422);
        }

        return $color;
    }

    private function validatedAvatarShape(mixed $value): string
    {
        $shape = trim((string) $value);

        if (!in_array($shape, User::AVATAR_SHAPES, true)) {
            $this->error('avatarShape must be one of: ' . implode(', ', User::AVATAR_SHAPES), 422);
        }

        return $shape;
    }

    private function validatedVisibility(mixed $value): string
    {
        $visibility = trim((string) $value);

        if (!in_array($visibility, User::VISIBILITIES, true)) {
            $this->error('visibility must be one of: ' . implode(', ', User::VISIBILITIES), 422);
        }

        return $visibility;
    }

    /**
     * Permanently deletes the authenticated user's account, including their
     * sessions and profile links. Requires the exact handle in the request
     * body as a confirmation, mirroring the "type your handle to confirm"
     * check the frontend already does before this is ever called.
     */
    private function deleteCurrentUser(): HTTPResponse
    {
        $user = $this->authenticatedUser;
        $handle = trim((string) ($this->jsonBody()['handle'] ?? ''));

        if ($handle === '' || $handle !== $user->Handle) {
            $this->error('handle confirmation does not match', 422);
        }

        foreach ($user->ProfileLinks() as $link) {
            $link->delete();
        }

        foreach ($user->Sessions() as $session) {
            $session->delete();
        }

        $user->delete();
        $this->clearAuthCookie();

        return $this->jsonResponse(['deleted' => true]);
    }

    public function uploadAvatar(): HTTPResponse
    {
        return $this->uploadProfileImage(ProfileImageStore::SLOT_AVATAR);
    }

    public function uploadBackground(): HTTPResponse
    {
        return $this->uploadProfileImage(ProfileImageStore::SLOT_BACKGROUND);
    }

    /**
     * The frontend crops client-side (1:1 for the avatar, 9:4 for the
     * background) and sends the result as a base64 data URL – simplest
     * shape given every other write on this API is already plain JSON.
     */
    private function uploadProfileImage(string $slot): HTTPResponse
    {
        if (!$this->getRequest()->isPOST()) {
            $this->error('Method not allowed', 405);
        }

        $user = $this->authenticatedUser;

        if ($user->Handle === '') {
            $this->error('Account needs a handle before uploading images', 422);
        }

        $dataUrl = (string) ($this->jsonBody()['image'] ?? '');

        // Base64 inflates size by ~33% – 15MB of encoded text comfortably
        // covers any client-side crop output while still rejecting an
        // obviously abusive payload before it's ever decoded.
        if (strlen($dataUrl) > 15 * 1024 * 1024) {
            $this->error('Image too large', 413);
        }

        if (!preg_match('#^data:image/(?:jpe?g|png|webp);base64,([a-zA-Z0-9+/]+=*)$#', $dataUrl, $matches)) {
            $this->error('image must be a base64-encoded image/jpeg, image/png or image/webp data URL', 422);
        }

        $binary = base64_decode($matches[1], true);

        if ($binary === false || $binary === '') {
            $this->error('Invalid image data', 422);
        }

        try {
            ProfileImageStore::store($user, $slot, $binary);
        } catch (ProfileImageException $e) {
            $this->error($e->getMessage(), 422);
        }

        return $this->jsonResponse($user->toOwnApiData());
    }

    /**
     * Revokes the current session and clears the auth cookies. The frontend
     * can't clear the httpOnly session cookie itself, so a plain
     * client-side "log out" is no longer possible – this is the only way.
     */
    public function logout(): HTTPResponse
    {
        if (!$this->getRequest()->isPOST()) {
            $this->error('Method not allowed', 405);
        }

        $this->currentSession->RevokedAt = date('Y-m-d H:i:s');
        $this->currentSession->write();
        $this->clearAuthCookie();

        return $this->jsonResponse(['loggedOut' => true]);
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

        // Revoking the device you're currently on should actually log you
        // out of it, not just leave a now-useless-server-side-but-still-
        // cookied browser sitting there until the session token expires.
        if ((int) $session->ID === (int) $this->currentSession->ID) {
            $this->clearAuthCookie();
        }

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

        $this->clearAuthCookie();

        return $this->jsonResponse(['revoked' => true]);
    }

    public function links(): HTTPResponse
    {
        if ($this->getRequest()->isPOST()) {
            return $this->createLink();
        }

        return $this->jsonResponse(['data' => $this->linksData()]);
    }

    private function createLink(): HTTPResponse
    {
        $body = $this->jsonBody();
        $url = trim((string) ($body['url'] ?? ''));

        if ($url === '') {
            $this->error('url is required', 422);
        }

        $placement = trim((string) ($body['placement'] ?? '')) ?: ProfileLink::PLACEMENT_BELOW;

        if (!in_array($placement, [ProfileLink::PLACEMENT_BELOW, ProfileLink::PLACEMENT_CARD], true)) {
            $this->error('Invalid placement', 422);
        }

        if ($placement === ProfileLink::PLACEMENT_CARD) {
            $cardLinkCount = $this->authenticatedUser->ProfileLinks()
                ->filter('Placement', ProfileLink::PLACEMENT_CARD)
                ->count();

            if ($cardLinkCount >= 3) {
                $this->error('Only 3 links can be placed on the card', 422);
            }
        }

        $nextSort = (int) $this->authenticatedUser->ProfileLinks()->max('SortOrder') + 1;

        $link = ProfileLink::create();
        $link->URL = $url;
        $link->Title = trim((string) ($body['title'] ?? ''));
        $link->Platform = trim((string) ($body['platform'] ?? '')) ?: 'website';
        $link->Placement = $placement;
        $link->SortOrder = $nextSort;
        $link->UserID = $this->authenticatedUser->ID;
        $link->write();

        return $this->jsonResponse($link->toApiData(), 201);
    }

    /**
     * Handles both PATCH (update) and DELETE for a single link. Looking it
     * up via the user's own ProfileLinks() relation list (rather than
     * ProfileLink::get()->byID()) means a link ID belonging to a different
     * user simply isn't found here – no separate ownership check needed.
     */
    public function linkItem(): HTTPResponse
    {
        $id = (int) $this->getRequest()->param('ID');
        $link = $this->authenticatedUser->ProfileLinks()->byID($id);

        if (!$link instanceof ProfileLink) {
            $this->error('Link not found', 404);
        }

        if ($this->getRequest()->isDELETE()) {
            $link->delete();

            return $this->jsonResponse(['deleted' => true]);
        }

        if ($this->getRequest()->httpMethod() !== 'PATCH') {
            $this->error('Method not allowed', 405);
        }

        $body = $this->jsonBody();

        if (array_key_exists('url', $body)) {
            $url = trim((string) $body['url']);

            if ($url === '') {
                $this->error('url cannot be empty', 422);
            }

            $link->URL = $url;
        }

        if (array_key_exists('title', $body)) {
            $link->Title = (string) $body['title'];
        }

        if (array_key_exists('platform', $body)) {
            $link->Platform = (string) $body['platform'];
        }

        $link->write();

        return $this->jsonResponse($link->toApiData());
    }

    public function reorderLinks(): HTTPResponse
    {
        if (!$this->getRequest()->isPOST()) {
            $this->error('Method not allowed', 405);
        }

        $order = $this->jsonBody()['order'] ?? null;

        if (!is_array($order)) {
            $this->error('order must be an array of link IDs', 422);
        }

        $links = $this->authenticatedUser->ProfileLinks();

        foreach ($order as $index => $id) {
            $link = $links->byID((int) $id);

            if ($link instanceof ProfileLink) {
                $link->SortOrder = $index + 1;
                $link->write();
            }
        }

        return $this->jsonResponse(['data' => $this->linksData()]);
    }

    /**
     * @return array<int, array{id: int, url: string, title: string, platform: string, sortOrder: int}>
     */
    private function linksData(): array
    {
        $data = [];

        foreach ($this->authenticatedUser->ProfileLinks() as $link) {
            $data[] = $link->toApiData();
        }

        return $data;
    }
}
