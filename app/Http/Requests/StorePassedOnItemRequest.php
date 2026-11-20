<?php

namespace App\Http\Requests;

use App\Http\Controllers\PassOnController;
use App\Models\ClubLodgeLink;
use App\Models\Lodge;
use App\Models\PassedOnItem;
use App\Services\PassedOn\LodgeLinks;
use App\Services\PassedOn\PlatformSummons;
use App\Support\PassedOnPreferences;
use App\Support\UploadRules;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Passing something on (PassOnController::store). Who may send is decided first, so a non-sender gets a refusal and
 * never a validation error. A lodge's summons or notice must be about a lodge on one of this lodge's lists.
 */
class StorePassedOnItemRequest extends ClubRequest
{
    public const LODGE_KINDS = [ClubLodgeLink::SISTER, ClubLodgeLink::RECIPROCAL];

    public function authorize(): bool
    {
        return PassOnController::canSend($this->user(), $this->club());
    }

    protected function prepareForValidation(): void
    {
        // Provincial and Grand Lodge communications are notices: there is no lodge meeting to put in a calendar.
        if (! in_array($this->input('kind'), self::LODGE_KINDS, true)) {
            $this->merge(['type' => PassedOnItem::NOTICE, 'lodge_slug' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $forLodge = in_array($this->input('kind'), self::LODGE_KINDS, true);
        $summons = $forLodge && $this->input('type') === PassedOnItem::SUMMONS;

        return [
            'kind' => ['required', Rule::in(PassedOnPreferences::KINDS)],
            'type' => ['required', Rule::in([PassedOnItem::SUMMONS, PassedOnItem::NOTICE])],
            'lodge_slug' => [Rule::requiredIf($forLodge), 'nullable', 'string', 'max:200', $this->onTheList(...)],
            'title' => ['required', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:20000', 'required_without:attachment'],
            'attachment' => ['nullable', 'file', 'mimes:pdf', 'max:10240', $this->safeFile(...)],
            'meeting_date' => [Rule::requiredIf($summons), 'nullable', 'date', 'after_or_equal:today'],
            'meeting_time' => ['nullable', 'date_format:H:i'],
            'venue' => ['nullable', 'string', 'max:200'],
            'dress_code' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required_without' => 'Paste the text or attach the PDF.',
            'meeting_date.required' => 'Give the meeting date, so members can put it in their calendar.',
            'lodge_slug.required' => 'Choose the lodge it is from.',
        ];
    }

    public function lodge(): ?Lodge
    {
        $slug = $this->input('lodge_slug');

        return $slug ? Lodge::listed()->where('slug', $slug)->first() : null;
    }

    private function onTheList(string $attribute, mixed $value, Closure $fail): void
    {
        $lodge = is_string($value) ? $this->lodge() : null;

        if (! $lodge || ! app(LodgeLinks::class)->onList($this->club(), $lodge, (string) $this->input('kind'))) {
            $fail('Choose a lodge from your '.($this->input('kind') === ClubLodgeLink::SISTER ? 'sister lodges' : 'reciprocal visit lodges').'.');

            return;
        }

        // Its own summons already reaches the members; only the full PDF, if the Secretary has it, adds anything.
        if ($this->input('type') === PassedOnItem::SUMMONS && ! $this->hasFile('attachment') && PlatformSummons::publishesOwn($lodge)) {
            $fail($lodge->displayName().' publishes its own summonses on Club Manager, so your members already get them.');
        }
    }

    private function safeFile(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof UploadedFile && ($error = UploadRules::assertSafeUpload($value))) {
            $fail($error);
        }
    }
}
