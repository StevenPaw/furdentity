<?php

namespace App\Api;

use Override;
use App\Api\Support\JwtService;
use App\Model\Profile;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use Throwable;

/**
 * Internal API that feeds the own Vue frontend. Every request must carry a
 * valid access token: "Authorization: Bearer <jwt>". Obtain one from
 * POST /api/v1/auth/login.
 *
 *   GET /api/v1/internal/me
 *   GET /api/v1/internal/profiles
 */
class InternalApiController extends ApiController
{
    private static array $url_handlers = [
        'me' => 'currentMember',
        'profiles' => 'profiles',
    ];

    private static array $allowed_actions = [
        'currentMember',
        'profiles',
    ];

    private ?Member $authenticatedMember = null;

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

        $member = Member::get()->byID((int) $payload->sub);

        if (!$member instanceof Member) {
            $this->error('Invalid or expired token', 401);
        }

        $this->authenticatedMember = $member;
        Security::setCurrentUser($member);
    }

    public function currentMember(): HTTPResponse
    {
        $member = $this->authenticatedMember;

        return $this->jsonResponse([
            'id' => (int) $member->ID,
            'email' => (string) $member->Email,
            'firstName' => (string) $member->FirstName,
            'surname' => (string) $member->Surname,
        ]);
    }

    public function profiles(): HTTPResponse
    {
        $data = [];

        foreach (Profile::get() as $profile) {
            $data[] = $profile->toApiData();
        }

        return $this->jsonResponse(['data' => $data]);
    }
}
