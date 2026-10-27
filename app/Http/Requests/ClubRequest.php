<?php

namespace App\Http\Requests;

use App\Models\Club;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A form posted to /{clubSlug}/admin/... Who may post it is decided before this runs, by the club admin middleware
 * and config/club_permissions.php, so authorize() only has to let it through.
 */
abstract class ClubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The club named in the URL (the middleware has usually loaded it already).
     */
    protected function club(): Club
    {
        $slug = (string) ($this->route('clubSlug') ?? $this->route('slug'));

        return $this->attributes->get("club:{$slug}")
            ?? tap(Club::where('slug', $slug)->firstOrFail(), fn (Club $club) => $this->attributes->set("club:{$slug}", $club));
    }

    /**
     * The currency field of a bank account form: it has to be the club's own currency.
     *
     * @return list<mixed>
     */
    protected function currencyRule(): array
    {
        return ['required', 'string', 'size:3', Rule::in([$this->club()->currencyCode()])];
    }
}
