<?php

namespace App\Http\Requests\PageAdmin;

use App\Http\Requests\ClubRequest;
use App\Support\SiteThemes;

/**
 * The form posted to PageAdminController::saveColourScheme().
 */
class SaveColourSchemeRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'regex:'.SiteThemes::CUSTOM_ID_PATTERN],
            'name' => ['required', 'string', 'max:40'],
            'primary' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
