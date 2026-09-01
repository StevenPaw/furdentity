<?php

namespace App\Api\Support;

use App\Model\User;
use App\Model\UserSession;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use stdClass;
use UnexpectedValueException;

/**
 * Issues and validates the single JWT used by the internal API to identify a
 * login session – one token per session, no separate refresh token and no
 * rotation. It's re-issued with a fresh expiry on every authenticated
 * request (see {@see \App\Api\InternalApiController::init()}), which is what
 * makes "stay logged in" work without the client ever doing anything
 * special: as long as the site is used at least once within
 * $session_token_ttl, the session simply never goes stale.
 *
 * The signing key comes from the JWT_SIGNING_KEY environment variable.
 * Token lifetime and the issuer are configurable via app/_config/api.yml.
 */
class JwtService
{
    use Injectable;
    use Configurable;

    private const string ALGORITHM = 'HS256';

    private static int $session_token_ttl = 2592000;

    private static string $issuer = 'furdentity';

    public function issue(User $user, UserSession $session): string
    {
        $now = time();

        $payload = [
            'iss' => (string) $this->config()->get('issuer'),
            'sub' => (string) $user->ID,
            'sid' => (int) $session->ID,
            'iat' => $now,
            'exp' => $now + $this->sessionTokenTtl(),
            'email' => (string) $user->Email,
        ];

        return JWT::encode($payload, $this->signingKey(), self::ALGORITHM);
    }

    public function sessionTokenTtl(): int
    {
        return (int) $this->config()->get('session_token_ttl');
    }

    public function decode(string $jwt): stdClass
    {
        try {
            return JWT::decode($jwt, new Key($this->signingKey(), self::ALGORITHM));
        } catch (ExpiredException $e) {
            throw new UnexpectedValueException('Token has expired', 0, $e);
        }
    }

    private function signingKey(): string
    {
        $key = Environment::getEnv('JWT_SIGNING_KEY');

        if (!is_string($key) || $key === '') {
            throw new RuntimeException('JWT_SIGNING_KEY environment variable is not set');
        }

        return $key;
    }
}
