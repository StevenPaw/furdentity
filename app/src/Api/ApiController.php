<?php

namespace App\Api;

use Override;
use SilverStripe\Control\Controller;
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
