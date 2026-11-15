<?php

namespace App\Http\Controllers;

use App\Models\Lodge;
use App\Models\Province;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * The superadmin lodge directory screen for a provincial admin: they add and edit the listings of lodges in the provinces
 * they look after. A lodge elsewhere is "not found". A lodge that a club account manages is kept by that lodge's own
 * officers, so it is shown but not changed here; deleting lodges and linking them to clubs stay with the platform team.
 */
class ProvinceLodgeController extends SuperAdminLodgeController implements HasMiddleware
{
    /**
     * Refuse anyone who looks after no province before a form is validated, so they get a 403 and not a form error.
     *
     * @return list<Closure>
     */
    public static function middleware(): array
    {
        return [function (Request $request, Closure $next): Response {
            abort_unless($request->user()?->administeredProvinces()->exists(), 403);

            return $next($request);
        }];
    }

    protected function scope(Request $request): Builder
    {
        return Lodge::whereIn('province_id', $this->provinces($request)->select('provinces.id'));
    }

    protected function provinces(Request $request): Builder
    {
        return Province::whereIn('id', $request->user()->administeredProvinces()->select('provinces.id'));
    }

    protected function editable(Request $request, int $id): Lodge
    {
        $lodge = parent::editable($request, $id);
        abort_if($lodge->isManaged(), 403, 'This lodge keeps its own listing through its club account.');

        return $lodge;
    }

    protected function screen(): array
    {
        return ['routes' => 'members.lodges.directory', 'layout' => 'members', 'full' => false];
    }
}
