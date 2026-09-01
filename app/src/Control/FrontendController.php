<?php

namespace App\Control;

use Override;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Serves the Vue 3 single-page-application shell for every non-API,
 * non-admin route. The actual routing happens client-side in the SPA.
 *
 * In dev mode, if the Vite dev server (started via "yarn dev") is running
 * alongside PHP in the same ddev web container, its transformed index.html
 * is proxied through so the site works with HMR directly on the normal
 * ddev URL, without needing the separate :5173 port. Otherwise this falls
 * back to the built shell in public/frontend/ (see vite.config.js).
 */
class FrontendController extends Controller
{
    private static array $allowed_actions = ['index'];

    private const VITE_DEV_PORT = 5173;

    #[Override]
    public function handleRequest(HTTPRequest $request): HTTPResponse
    {
        $this->setRequest($request);

        if (Director::isDev()) {
            $devHtml = $this->getViteDevIndex($request);

            if ($devHtml !== null) {
                $response = HTTPResponse::create($devHtml);
                $response->addHeader('Content-Type', 'text/html; charset=utf-8');
                $response->addHeader('Cache-Control', 'no-store');

                return $response;
            }
        }

        $indexFile = PUBLIC_PATH . '/frontend/index.html';

        if (!is_file($indexFile)) {
            $message = 'Frontend not built yet. Run "yarn build" (or "yarn dev" for local development).';
            return HTTPResponse::create($message, 503)
                ->addHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        $response = HTTPResponse::create((string) file_get_contents($indexFile));
        $response->addHeader('Content-Type', 'text/html; charset=utf-8');

        if (Director::isDev()) {
            $response->addHeader('Cache-Control', 'no-store');
        }

        return $response;
    }

    /**
     * Fetches the Vite-transformed index.html from the dev server running
     * locally in this container, and rewrites its root-relative asset URLs
     * (e.g. "/src/main.js", "/@vite/client") to point at the dev server's
     * publicly exposed port, so the browser can load them cross-port.
     * Returns null if the dev server isn't reachable.
     */
    private function getViteDevIndex(HTTPRequest $request): ?string
    {
        $socket = @fsockopen('127.0.0.1', self::VITE_DEV_PORT, $errno, $errstr, 0.2);

        if ($socket === false) {
            return null;
        }

        fclose($socket);

        $context = stream_context_create(['http' => ['timeout' => 1]]);
        $html = @file_get_contents(
            'http://127.0.0.1:' . self::VITE_DEV_PORT . '/index.html',
            false,
            $context,
        );

        if ($html === false) {
            return null;
        }

        $publicHost = preg_replace('/:\d+$/', '', $request->getHeader('Host') ?? Director::host());
        $viteOrigin = 'https://' . $publicHost . ':' . self::VITE_DEV_PORT;

        return preg_replace('/(href|src)="(\/[^"]*)"/', '$1="' . $viteOrigin . '$2"', $html);
    }
}
