<?php

namespace App\Api;

use App\Api\Support\JwtService;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberAuthenticator\MemberAuthenticator;
use Throwable;

/**
 * Login / refresh endpoints for the internal API.
 *
 *   POST /api/v1/auth/login    { "email": "...", "password": "..." }
 *   POST /api/v1/auth/refresh  { "refreshToken": "..." }
 */
class AuthController extends ApiController
{
    private static array $allowed_actions = [
        'login',
        'refresh',
    ];

    public function login(): HTTPResponse
    {
        if (!$this->getRequest()->isPOST()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->jsonBody();
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->error('Email and password are required', 422);
        }

        $result = ValidationResult::create();
        /** @var MemberAuthenticator $authenticator */
        $authenticator = Injector::inst()->get(MemberAuthenticator::class);
        $member = $authenticator->authenticate(
            ['Email' => $email, 'Password' => $password],
            $this->getRequest(),
            $result
        );

        if (!$member instanceof Member || !$result->isValid()) {
            $this->error('Invalid credentials', 401);
        }

        return $this->jsonResponse(JwtService::create()->issueTokenPair($member));
    }

    public function refresh(): HTTPResponse
    {
        if (!$this->getRequest()->isPOST()) {
            $this->error('Method not allowed', 405);
        }

        $token = trim((string) ($this->jsonBody()['refreshToken'] ?? ''));

        if ($token === '') {
            $this->error('refreshToken is required', 422);
        }

        try {
            $payload = JwtService::create()->decode($token, 'refresh');
        } catch (Throwable) {
            $this->error('Invalid or expired refresh token', 401);
        }

        $member = Member::get()->byID((int) $payload->sub);

        if (!$member instanceof Member) {
            $this->error('Invalid or expired refresh token', 401);
        }

        return $this->jsonResponse(JwtService::create()->issueTokenPair($member));
    }
}
