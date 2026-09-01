<?php

namespace App\Api;

use Override;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use Throwable;

/**
 * Shared behaviour for the JSON APIs: body parsing, JSON responses and a
 * consistent error envelope. Concrete controllers add their own auth in init().
 */
abstract class ApiController extends Controller
{
    // httpOnly – never readable by JS, only ever sent back to our own API.
    // A single long-lived token (see JwtService) rather than an access +
    // refresh pair: no rotation means no "which copy of the token is
    // current" ambiguity between tabs, which is what previously made
    // clearing this cookie safe in some failure cases and unsafe in others.
    // Simpler, and there's now exactly one way to read this cookie's
    // presence: it's either a valid session or it isn't.
    protected const string COOKIE_SESSION = 'furdentity_session';
    // Deliberately NOT httpOnly – carries no secret, just lets the frontend
    // know a session exists without being able to read the actual token.
    protected const string COOKIE_AUTH_FLAG = 'furdentity_auth';

    /**
     * Sets both auth cookies after a successful login/confirm, or after any
     * authenticated request silently re-issues the token (see
     * {@see \App\Api\InternalApiController::init()}). $ttlSeconds is
     * converted to days since {@see Cookie::set()} expects its $expiry in
     * days. SameSite=Strict + Secure since this is a same-origin SPA that
     * never needs the cookie sent on a cross-site request.
     */
    protected function setAuthCookie(string $token, int $ttlSeconds): void
    {
        Cookie::set(self::COOKIE_SESSION, $token, $ttlSeconds / 86400, '/', null, true, true, Cookie::SAMESITE_STRICT);
        Cookie::set(self::COOKIE_AUTH_FLAG, '1', $ttlSeconds / 86400, '/', null, true, false, Cookie::SAMESITE_STRICT);
    }

    /**
     * Clears both auth cookies (logout, session revoke of the current
     * session, account deletion, or an invalid/expired/revoked token found
     * on any authenticated request). The httpOnly one can only ever be
     * cleared server-side – the frontend has no way to touch it directly.
     */
    protected function clearAuthCookie(): void
    {
        Cookie::force_expiry(self::COOKIE_SESSION, '/', null, true, true, Cookie::SAMESITE_STRICT);
        Cookie::force_expiry(self::COOKIE_AUTH_FLAG, '/', null, true, false, Cookie::SAMESITE_STRICT);
    }

    #[Override]
    public function handleRequest(HTTPRequest $request): HTTPResponse
    {
        try {
            return parent::handleRequest($request);
        } catch (HTTPResponse_Exception $ex) {
            $response = $ex->getResponse();
            // Already a JSON error (thrown via $this->error()) – pass through.
            if (str_contains((string) $response->getHeader('Content-Type'), 'application/json')) {
                return $response;
            }
            return $this->errorResponse(
                $response->getBody() ?: 'Request failed',
                $response->getStatusCode() ?: 400
            );
        } catch (Throwable $ex) {
            $message = Director::isDev() ? $ex->getMessage() : 'Internal server error';
            return $this->errorResponse($message, 500);
        }
    }

    /**
     * Decode the request body as a JSON object.
     *
     * @return array<string, mixed>
     */
    protected function jsonBody(): array
    {
        $raw = $this->getRequest()->getBody();

        if ($raw === '' || $raw === null) {
            return [];
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            $this->error('Request body must be a JSON object', 400);
        }

        return $data;
    }

    protected function jsonResponse(mixed $data, int $status = 200): HTTPResponse
    {
        $response = HTTPResponse::create(
            (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status
        );
        $response->addHeader('Content-Type', 'application/json; charset=utf-8');

        return $response;
    }

    protected function errorResponse(string $message, int $status): HTTPResponse
    {
        return $this->jsonResponse([
            'error' => [
                'message' => $message,
                'status' => $status,
            ],
        ], $status);
    }

    /**
     * Abort the current request with a JSON error response.
     */
    protected function error(string $message, int $status): never
    {
        throw new HTTPResponse_Exception(
            $this->errorResponse($message, $status)
        );
    }
}
