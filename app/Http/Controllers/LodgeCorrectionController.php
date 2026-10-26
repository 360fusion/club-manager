<?php

namespace App\Http\Controllers;

use App\Models\Lodge;
use App\Models\LodgeCorrection;
use App\Models\MasonicHall;
use App\Services\Lodges\LodgeCorrectionService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Telling us a directory listing is wrong. A superadmin checks each suggestion before it changes
 * anything; every step goes through LodgeCorrectionService.
 */
class LodgeCorrectionController extends Controller
{
    public function __construct(private readonly LodgeCorrectionService $corrections) {}

    public function create(Request $request, string $slug): Response|RedirectResponse
    {
        $lodge = Lodge::listed()->with(['clubType:id,code,name', 'province:id,name', 'masonicHall:id,name,town'])->where('slug', $slug)->firstOrFail();

        if ($lodge->corrections()->where('user_id', $request->user()->id)->open()->exists()) {
            return redirect()->route('lodges.show', $lodge->slug)->with('success', 'You already sent a fix for this lodge. It is waiting to be checked.');
        }

        return Inertia::render('Lodges/SuggestFix', [
            'lodge' => [
                'slug' => $lodge->slug,
                'name' => $lodge->displayName(),
                'order' => $lodge->clubType?->name,
                'province' => $lodge->province?->name,
                'installation_month' => $lodge->installationMonthName(),
                'current' => [
                    'meets_text' => $lodge->meets_text,
                    'masonic_hall_id' => $lodge->masonic_hall_id,
                    'status' => $lodge->status,
                    'name' => $lodge->name,
                    'number' => $lodge->number,
                    'website_url' => $lodge->website_url,
                ],
            ],
            // The halls of the lodge's own province, or every hall when we do not know its province.
            'halls' => MasonicHall::when($lodge->province_id, fn ($query) => $query->where('province_id', $lodge->province_id))
                ->orderBy('name')->get(['id', 'name', 'town']),
            'statuses' => LodgeCorrection::STATUSES,
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $lodge = Lodge::listed()->where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'meets_text' => ['nullable', 'string', 'max:500', self::noContactDetails()],
            'masonic_hall_id' => 'nullable|exists:masonic_halls,id',
            'hall_text' => ['nullable', 'string', 'max:255', self::noContactDetails()],
            'status' => ['nullable', Rule::in(array_keys(LodgeCorrection::STATUSES))],
            'name' => 'nullable|string|max:255',
            'number' => 'nullable|string|max:20',
            'website_url' => ['nullable', 'url', 'max:255', 'regex:#^https?://#i'],
            'note' => ['nullable', 'string', 'max:2000', self::noContactDetails()],
        ]);

        $this->corrections->submit($lodge, $request->user(), Arr::except($validated, 'note'), $validated['note'] ?? null);

        return redirect()->route('lodges.show', $lodge->slug)->with('success', 'Thank you. We will check your suggestion and let you know what happens.');
    }

    /**
     * Suggestions are kept and shown to our team, so they may not carry anyone's email address or
     * phone number.
     */
    private static function noContactDetails(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && preg_match('/[^\s@]+@[^\s@]+\.[a-z]{2,}|(?:\d[\s().-]*){10,}/i', $value)) {
                $fail('Please don\'t include anyone\'s contact details.');
            }
        };
    }
}
