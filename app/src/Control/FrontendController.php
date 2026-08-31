<?php

namespace App\Control;

use Override;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Serves the built Vue 3 single-page-application shell for every non-API,
 * non-admin route. The actual routing happens client-side in the SPA.
 *
 * Build output lives in public/frontend/ (see vite.config.js). During
 * development you normally hit the Vite dev server on :5173 directly.
 */
class FrontendController extends Controller
{
    private static array $allowed_actions = ['index'];

    #[Override]
    public function handleRequest(HTTPRequest $request): HTTPResponse
    {
        $this->setRequest($request);

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
}
