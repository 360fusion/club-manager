<?php

namespace App\Http\Controllers;

use App\Models\LodgeCorrection;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * The superadmin queue of suggested fixes, for a provincial admin: only the lodges in the provinces they look after.
 * Another province's suggestion is "not found", never shown or decided.
 */
class ProvinceLodgeCorrectionController extends SuperAdminLodgeCorrectionController implements HasMiddleware
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
        $provinceIds = $request->user()->administeredProvinces()->pluck('provinces.id');

        return LodgeCorrection::whereHas('lodge', fn (Builder $lodge) => $lodge->whereIn('province_id', $provinceIds));
    }

    protected function screen(): array
    {
        return ['routes' => 'members.lodges.fixes', 'layout' => 'members'];
    }
}
