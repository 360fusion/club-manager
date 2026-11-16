<?php

namespace Launchpad\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Launchpad\Api\OpenApi;

/** The API's description as JSON (/api/v1/openapi.json) and a page that shows it (/api/docs). Both are public: the API's customers read them. */
class ApiDocsController extends Controller
{
    public function spec(): JsonResponse
    {
        return response()->json(OpenApi::build())->header('Cache-Control', 'public, max-age=300');
    }

    public function page(): View
    {
        return view('launchpad::api.docs', ['specUrl' => url('api/v1/openapi.json')]);
    }
}
