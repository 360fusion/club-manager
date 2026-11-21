<?php

namespace App\Http\Controllers;

use App\Http\Middleware\HandleInertiaRequests;
use App\Support\ZiggyScript;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The page's route table as its own file (see ZiggyScript). Only the visitor's own group is served, so a guest never
 * gets the club admin's or superadmin's route map. The address carries a hash of the routes, so a browser keeps the
 * file until the routes change; an out-of-date hash still gets today's table, just not kept.
 */
class ZiggyRoutesController extends Controller
{
    public function __invoke(Request $request, string $group, string $hash): Response
    {
        $own = HandleInertiaRequests::routeGroup($request->user()) ?? 'all';
        abort_unless($group === $own, 404);

        return response(ZiggyScript::body($own === 'all' ? null : $own), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => $hash === ZiggyScript::hash() ? 'private, max-age=31536000, immutable' : 'no-cache',
        ]);
    }
}
