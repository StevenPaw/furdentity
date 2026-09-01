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
 *   GET /api/v1/public/profiles
 *   GET /api/v1/public/profile/$Handle  – single profile, $Handle passed as $ID
 */
class PublicApiController extends ApiController
{
    private static array $allowed_actions = [
        'ping',
        'profiles',
        'profile',
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

        foreach (User::get() as $user) {
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

        return $this->jsonResponse($user->toApiData());
    }

    private function withCors(HTTPResponse $response): HTTPResponse
    {
        return $response
            ->addHeader('Access-Control-Allow-Origin', '*')
            ->addHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->addHeader('Access-Control-Allow-Headers', 'X-Api-Key, Content-Type');
    }
}
