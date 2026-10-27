<?php

namespace App\Http\Controllers;

use App\Http\Requests\SuperAdmin\StoreClubTypeRequest;
use App\Http\Requests\SuperAdminClubType\SendTestEmailTemplateRequest;
use App\Http\Requests\SuperAdminClubType\UpdateClubTypeRequest;
use App\Http\Requests\SuperAdminClubType\UpdateEmailTemplateRequest;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\DefaultEmailTemplate;
use App\Support\OrderColours;
use Database\Seeders\ClubTypeSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Club types and the platform's default email wording. Split out of SuperAdminController; the URLs, route names and permissions are unchanged.
 */
class SuperAdminClubTypeController extends Controller
{
    /**
     * List and manage Club Types, Officer Ladders, Ranks, and Terminology.
     */
    public function clubTypesIndex(): Response
    {
        $clubTypes = ClubType::with(['defaultOfficerRoles', 'defaultRanks'])->get();

        return Inertia::render('SuperAdmin/ClubTypes/Index', [
            'clubTypes' => $clubTypes,
        ]);
    }

    /**
     * Display a dedicated details page for a specific Masonic Order / Club Type.
     */
    public function showClubType(int $id): Response
    {
        $clubType = ClubType::with(['defaultOfficerRoles', 'defaultRanks'])
            ->withCount('clubs')
            ->findOrFail($id);

        $clubs = Club::where('club_type_id', $id)
            ->with(['province'])
            ->withCount('users')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'lodge_number' => $c->lodge_number ?? '—',
                'province' => $c->province?->name ?? '—',
                'members_count' => $c->users_count,
                'created_at' => $c->created_at ? $c->created_at->format('d M Y') : '—',
            ]);

        $allClubTypes = ClubType::select('id', 'name', 'code')->orderBy('name')->get();

        return Inertia::render('SuperAdmin/ClubTypes/Show', [
            'clubType' => $clubType,
            'clubs' => $clubs,
            'allClubTypes' => $allClubTypes,
        ]);
    }

    /**
     * Store a new Club Type preset.
     */
    public function storeClubType(StoreClubTypeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $ct = ClubType::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'colour' => $validated['colour'] ?? OrderColours::for($validated['code']),
            'description' => $validated['description'] ?? null,
            'website_url' => $validated['website_url'] ?? null,
            'available_modules' => $validated['available_modules'] ?? ['accounting', 'meetings', 'members', 'charity'],
            'default_settings' => [],
            'terminology' => $validated['terminology'] ?? [],
            'rulers_schema' => $validated['rulers_schema'] ?? [],
        ]);

        return redirect()->back()->with('success', "Club Type '{$ct->name}' created.");
    }

    /**
     * Update an existing Club Type preset.
     */
    public function updateClubType(UpdateClubTypeRequest $request, int $id): RedirectResponse
    {
        $clubType = ClubType::findOrFail($id);

        $validated = $request->validated();

        $clubType->update([
            'name' => $validated['name'],
            'colour' => $validated['colour'] ?? $clubType->colour,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $clubType->description,
            'website_url' => array_key_exists('website_url', $validated) ? $validated['website_url'] : $clubType->website_url,
            'available_modules' => $validated['available_modules'] ?? $clubType->available_modules,
            'terminology' => $validated['terminology'] ?? $clubType->terminology,
            'rulers_schema' => $validated['rulers_schema'] ?? $clubType->rulers_schema,
        ]);

        return redirect()->back()->with('success', "Club Type '{$clubType->name}' updated.");
    }

    /**
     * List default email templates.
     */
    public function emailTemplatesIndex(): Response
    {
        $templates = DefaultEmailTemplate::all();

        if ($templates->isEmpty()) {
            (new ClubTypeSeeder)->run();
            $templates = DefaultEmailTemplate::all();
        }

        return Inertia::render('SuperAdmin/EmailTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    /**
     * Update a default email template.
     */
    public function updateEmailTemplate(UpdateEmailTemplateRequest $request, int $id): RedirectResponse
    {
        $template = DefaultEmailTemplate::findOrFail($id);

        $validated = $request->validated();

        $template->update($validated);

        return redirect()->back()->with('success', "Email template '{$template->name}' updated.");
    }

    /**
     * Email the template, with each placeholder shown as its own name, to the signed-in superadmin so they can see how it reads.
     */
    public function sendTestEmailTemplate(SendTestEmailTemplateRequest $request, int $id): RedirectResponse
    {
        $template = DefaultEmailTemplate::findOrFail($id);

        $validated = $request->validated();

        $samples = collect($template->available_placeholders ?? [])->mapWithKeys(fn (string $name) => ['{{'.$name.'}}' => '['.$name.']'])->all();
        $subject = trim(str_replace(["\r", "\n"], ' ', strtr($validated['subject'], $samples)));
        $body = strtr($validated['body_html'], $samples);

        Mail::html($body, fn ($message) => $message->to($request->user()->email)->subject('[Test] '.$subject));

        return redirect()->back()->with('success', 'A test of this template has been sent to '.$request->user()->email.'.');
    }
}
