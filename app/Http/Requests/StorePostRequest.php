<?php

namespace App\Http\Requests;

use App\Enums\Visibility;
use App\Support\UploadRules;
use Illuminate\Validation\Rule;

/**
 * Store or update a blog post.
 */
class StorePostRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $club = $this->club();

        return [
            'id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'nullable|string|max:200000',
            'status' => 'required|in:draft,published',
            'visibility' => ['nullable', Rule::enum(Visibility::class)],
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'cover_image_url' => 'nullable|string|max:1000',
            'cover_image' => UploadRules::image(4096),
            'blocks' => 'nullable|array|max:200',
            'existing_attachments' => 'nullable|array|max:50',
            'new_attachments.*' => UploadRules::attachment(10240),
            'action_type' => 'nullable|string|max:50',
            'tag_ids' => 'nullable|array|max:20',
            'tag_ids.*' => ['integer', Rule::exists('news_tags', 'id')->where('club_id', $club->id)],

        ];
    }
}
