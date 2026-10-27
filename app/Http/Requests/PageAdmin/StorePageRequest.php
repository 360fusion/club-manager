<?php

namespace App\Http\Requests\PageAdmin;

use App\Http\Requests\ClubRequest;
use App\Models\Page;
use App\Support\UploadRules;
use Illuminate\Validation\Rule;

/**
 * The form posted to PageAdminController::store().
 */
class StorePageRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|alpha_dash',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'share_image' => ['nullable', 'string', 'max:2048', UploadRules::IMAGE_URL],
            'noindex' => 'boolean',
            'is_published' => 'boolean',
            'publish_at' => 'nullable|date',
            'unpublish_at' => 'nullable|date|after:publish_at',
            'is_homepage' => 'boolean',
            'is_members_only' => 'boolean',
            'show_in_navigation' => 'boolean',
            'show_in_footer' => 'boolean',
            'header_style' => ['nullable', Rule::in(Page::HEADER_STYLES)],
            'blocks' => 'array|max:200',

        ];
    }
}
