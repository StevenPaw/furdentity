<?php

namespace App\Api\Support;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Security\Member;
use stdClass;
use UnexpectedValueException;

/**
 * Issues and validates the JWTs used by the internal API.
 *
 * The signing key comes from the JWT_SIGNING_KEY environment variable.
 * Token lifetimes and the issuer are configurable via app/_config/api.yml.
 */
class JwtService
{
    use Injectable;
    use Configurable;

    private const string ALGORITHM = 'HS256';

    private static int $access_token_ttl = 900;

    private static int $refresh_token_ttl = 1209600;

    private static string $issuer = 'furdentity';

    /**
     * @return array{token: string, refreshToken: string, expiresIn: int, tokenType: string}
     */
    public function issueTokenPair(Member $member): array
    {
        $accessTtl = (int) $this->config()->get('access_token_ttl');

        return [
            'token' => $this->issue($member, 'access', $accessTtl),
            'refreshToken' => $this->issue($member, 'refresh', (int) $this->config()->get('refresh_token_ttl')),
            'expiresIn' => $accessTtl,
            'tokenType' => 'Bearer',
        ];
    }

    /**
     * Decode and validate a token, ensuring it is of the expected type
     * ("access" or "refresh").
     */
    public function decode(string $jwt, string $expectedType): stdClass
    {
        try {
            $payload = JWT::decode($jwt, new Key($this->signingKey(), self::ALGORITHM));
        } catch (ExpiredException $e) {
            throw new UnexpectedValueException('Token has expired', 0, $e);
        }

        if (($payload->type ?? null) !== $expectedType) {
            throw new UnexpectedValueException('Unexpected token type');
        }

        return $payload;
    }

    private function issue(Member $member, string $type, int $ttl): string
    {
        $now = time();

        return JWT::encode([
            'iss' => (string) $this->config()->get('issuer'),
            'sub' => (string) $member->ID,
            'type' => $type,
            'iat' => $now,
            'exp' => $now + $ttl,
            'email' => $type === 'access' ? $member->Email : null,
        ], $this->signingKey(), self::ALGORITHM);
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
