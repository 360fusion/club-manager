<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\NewsTag;
use App\Support\OrderColours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The tags a club's news items can carry. Admins manage the list under Settings (the
 * `settings` capability covers these routes); anyone who can edit news only picks from it.
 */
class NewsTagAdminController extends Controller
{
    public function store(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $validated = $this->validated($request, $club);

        NewsTag::create([
            'club_id' => $club->id,
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($club, $validated['name']),
            'color' => $validated['color'] ?? OrderColours::DEFAULT,
        ]);

        return redirect()->back()->with('success', 'Tag added.');
    }

    public function update(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $tag = NewsTag::where('club_id', $club->id)->findOrFail($id);
        $validated = $this->validated($request, $club, $tag);

        // The slug follows the name so links and filters stay readable; it only changes with the name.
        $tag->update([
            'name' => $validated['name'],
            'slug' => $tag->name === $validated['name'] ? $tag->slug : $this->uniqueSlug($club, $validated['name'], $tag),
            'color' => $validated['color'] ?? $tag->color,
        ]);

        return redirect()->back()->with('success', 'Tag updated.');
    }

    public function destroy(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $tag = NewsTag::where('club_id', $club->id)->findOrFail($id);

        $tag->posts()->detach();
        $tag->delete();

        return redirect()->back()->with('success', 'Tag deleted.');
    }

    /**
     * @return array{name: string, color?: ?string}
     */
    private function validated(Request $request, Club $club, ?NewsTag $ignore = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('news_tags', 'name')->where('club_id', $club->id)->ignore($ignore?->id),
            ],
            'color' => ['nullable', 'string', Rule::in(OrderColours::KEYS)],
        ]);
    }

    private function uniqueSlug(Club $club, string $name, ?NewsTag $ignore = null): string
    {
        $base = Str::slug($name) ?: 'tag';
        $slug = $base;
        $suffix = 2;

        while (NewsTag::where('club_id', $club->id)->where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
