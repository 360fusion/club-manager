<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubEmailTemplate;
use App\Models\DefaultEmailTemplate;
use App\Support\EmailTemplate;
use App\Support\EmailTemplateSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A lodge rewords the emails it sends. Its version is used instead of the platform default until it resets it.
 */
class ClubEmailTemplateController extends Controller
{
    public function index(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $overrides = ClubEmailTemplate::where('club_id', $club->id)->get()->keyBy('template_key');

        $templates = DefaultEmailTemplate::whereIn('template_key', array_keys(EmailTemplate::LODGE_EDITABLE))->orderBy('id')->get()->map(function (DefaultEmailTemplate $default) use ($overrides) {
            $override = $overrides->get($default->template_key);

            return [
                'key' => $default->template_key,
                'name' => $default->name,
                'subject' => $override->subject ?? $default->subject,
                'body_html' => $override->body_html ?? $default->body_html,
                'available_placeholders' => $default->available_placeholders ?? [],
                'required_placeholder' => EmailTemplate::LODGE_EDITABLE[$default->template_key] ?? null,
                'customised' => $override !== null,
            ];
        })->values();

        return Inertia::render('Admin/EmailTemplates/Index', ['club' => $club, 'templates' => $templates]);
    }

    public function update(Request $request, string $clubSlug, string $key): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $default = $this->editable($key);

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string|max:50000',
        ]);

        $body = EmailTemplateSanitizer::sanitize($validated['body_html']);

        if (trim(strip_tags($body)) === '') {
            throw ValidationException::withMessages(['body_html' => 'The email needs some text.']);
        }

        $this->assertPlaceholders($default, $validated['subject'], $body);

        ClubEmailTemplate::updateOrCreate(
            ['club_id' => $club->id, 'template_key' => $key],
            ['subject' => trim(str_replace(["\r", "\n"], ' ', $validated['subject'])), 'body_html' => $body],
        );

        return redirect()->back()->with('success', "'{$default->name}' saved for {$club->name}.");
    }

    public function destroy(string $clubSlug, string $key): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $default = $this->editable($key);

        ClubEmailTemplate::where('club_id', $club->id)->where('template_key', $key)->delete();

        return redirect()->back()->with('success', "'{$default->name}' is back to the standard wording.");
    }

    /**
     * Email the wording, with each placeholder shown as its own name, to the person testing it.
     */
    public function test(Request $request, string $clubSlug, string $key): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $default = $this->editable($key);

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string|max:50000',
        ]);

        $body = EmailTemplateSanitizer::sanitize($validated['body_html']);
        $this->assertPlaceholders($default, $validated['subject'], $body);

        $samples = collect($default->available_placeholders ?? [])->mapWithKeys(fn (string $name) => ['{{'.$name.'}}' => '['.$name.']'])->all();
        $subject = trim(str_replace(["\r", "\n"], ' ', strtr($validated['subject'], $samples)));

        Mail::html(strtr($body, $samples), fn ($message) => $message
            ->to($request->user()->email)
            ->from(config('mail.from.address'), $club->settings['email_from_name'] ?? $club->name)
            ->subject('[Test] '.$subject));

        return redirect()->back()->with('success', 'A test has been sent to '.$request->user()->email.'.');
    }

    private function editable(string $key): DefaultEmailTemplate
    {
        abort_unless(array_key_exists($key, EmailTemplate::LODGE_EDITABLE), 404);

        return DefaultEmailTemplate::where('template_key', $key)->firstOrFail();
    }

    /**
     * Only this email's own placeholders may be used, and any the email cannot work without must be kept.
     */
    private function assertPlaceholders(DefaultEmailTemplate $default, string $subject, string $body): void
    {
        $allowed = $default->available_placeholders ?? [];
        preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $subject.' '.$body, $found);
        $unknown = array_values(array_diff(array_unique($found[1]), $allowed));

        if ($unknown !== []) {
            throw ValidationException::withMessages(['body_html' => 'These placeholders are not available in this email: {{'.implode('}}, {{', $unknown).'}}.']);
        }

        $required = EmailTemplate::LODGE_EDITABLE[$default->template_key] ?? null;

        if ($required && ! str_contains($body, '{{'.$required.'}}')) {
            throw ValidationException::withMessages(['body_html' => 'This email must keep {{'.$required.'}}, so the link in the email keeps working.']);
        }
    }
}
