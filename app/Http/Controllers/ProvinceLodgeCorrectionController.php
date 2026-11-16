<?php

namespace App\Http\Controllers;

use App\Http\Requests\SuperAdminLodgeCorrection\ApplyRequest;
use App\Models\LodgeCorrection;
use App\Models\MasonicHall;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
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

        // A lodge with its own club account keeps its own listing, so its suggestions are not the province's to decide.
        return LodgeCorrection::whereHas('lodge', fn (Builder $lodge) => $lodge->whereIn('province_id', $provinceIds)->whereNull('club_id'));
    }

    /**
     * As the superadmin's apply, but a hall can only be one in the provinces this admin looks after.
     */
    public function apply(ApplyRequest $request, int $id): RedirectResponse
    {
        $hallId = $request->validated()['values']['masonic_hall_id'] ?? null;

        if ($hallId !== null && in_array('masonic_hall_id', $request->validated()['fields'], true)) {
            $provinceIds = $request->user()->administeredProvinces()->pluck('provinces.id');
            abort_unless(MasonicHall::whereKey($hallId)->whereIn('province_id', $provinceIds)->exists(), 422, 'That hall is not in a province you look after.');
        }

        return parent::apply($request, $id);
    }

    protected function screen(): array
    {
        return ['routes' => 'members.lodges.fixes', 'layout' => 'members'];
    }
}
