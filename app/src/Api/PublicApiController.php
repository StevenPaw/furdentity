<?php

namespace App\Api;

use Override;
use App\Model\User;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Environment;

/**
 * Public, read-only API. Intended for third-party consumers and later gated
 * by an API key sent in the "X-Api-Key" header.
 *
 * The allowed keys are configured via the API_PUBLIC_KEYS environment variable
 * (comma separated). While that variable is empty the API stays open, which is
 * convenient for local development.
 *
 *   GET /api/v1/public/ping
 *   GET /api/v1/public/profiles              – every User::VISIBILITY_PUBLIC profile
 *   GET /api/v1/public/profile/$Handle        – single profile, $Handle passed as $ID;
 *                                              404 if the handle doesn't exist, 403 if
 *                                              it exists but is User::VISIBILITY_HIDDEN
 *   GET /api/v1/public/randomProfiles?limit=N – up to $limit random
 *                                              User::VISIBILITY_PUBLIC profiles, for the
 *                                              homepage carousel (unlisted/hidden profiles
 *                                              never appear here, by design)
 */
class PublicApiController extends ApiController
{
    // Homepage carousel default/ceiling – matches the number the frontend
    // asks for (see ProfileCarousel.vue), the ceiling just guards against an
    // arbitrarily large `limit` query param.
    private const int RANDOM_PROFILES_DEFAULT_LIMIT = 16;
    private const int RANDOM_PROFILES_MAX_LIMIT = 50;

    private static array $allowed_actions = [
        'ping',
        'profiles',
        'profile',
        'randomProfiles',
    ];

    #[Override]
    public function handleRequest(HTTPRequest $request): HTTPResponse
    {
        // CORS pre-flight – answer before any routing/auth happens.
        if ($request->httpMethod() === 'OPTIONS') {
            $this->setRequest($request);
            return $this->withCors(HTTPResponse::create('', 204));
        }

        return $this->withCors(parent::handleRequest($request));
    }

    #[Override]
    protected function init(): void
    {
        parent::init();

        $configured = array_filter(array_map(
            trim(...),
            explode(',', (string) Environment::getEnv('API_PUBLIC_KEYS'))
        ));

        if ($configured === []) {
            return;
        }

        $provided = trim((string) $this->getRequest()->getHeader('X-Api-Key'));

        if ($provided === '' || !in_array($provided, $configured, true)) {
            $this->error('Missing or invalid API key', 401);
        }
    }

    public function ping(): HTTPResponse
    {
        return $this->jsonResponse(['status' => 'ok']);
    }

    public function profiles(): HTTPResponse
    {
        $data = [];

        foreach (User::get()->filter('Visibility', User::VISIBILITY_PUBLIC) as $user) {
            $data[] = $user->toApiData();
        }

        return $this->jsonResponse(['data' => $data]);
    }

    public function profile(): HTTPResponse
    {
        $handle = (string) $this->getRequest()->param('ID');
        $user = User::get()->filter('Handle', $handle)->first();

        if (!$user instanceof User) {
            $this->error('Profile not found', 404);
        }

        // Unlisted behaves exactly like public here – it's only excluded
        // from profiles()/randomProfiles(), never from a direct link.
        if ($user->Visibility === User::VISIBILITY_HIDDEN) {
            $this->error('This profile is private', 403);
        }

        return $this->jsonResponse($user->toApiData());
    }

    /**
     * Backs the homepage's "recently joined" carousel. Deliberately a
     * separate PHP-side shuffle rather than an ORDER BY RAND() – this app's
     * user count is small enough that fetching every public profile and
     * shuffling in memory is simpler and stays portable across DB backends.
     */
    public function randomProfiles(): HTTPResponse
    {
        $limit = (int) $this->getRequest()->getVar('limit');

        if ($limit <= 0) {
            $limit = self::RANDOM_PROFILES_DEFAULT_LIMIT;
        }

        $limit = min($limit, self::RANDOM_PROFILES_MAX_LIMIT);

        $users = iterator_to_array(User::get()->filter('Visibility', User::VISIBILITY_PUBLIC), false);
        shuffle($users);

        $data = array_map(
            static fn (User $user): array => $user->toApiData(),
            array_slice($users, 0, $limit)
        );

        return $this->jsonResponse(['data' => $data]);
    }

    private function withCors(HTTPResponse $response): HTTPResponse
    {
        return $response
            ->addHeader('Access-Control-Allow-Origin', '*')
            ->addHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->addHeader('Access-Control-Allow-Headers', 'X-Api-Key, Content-Type');
    }
}
