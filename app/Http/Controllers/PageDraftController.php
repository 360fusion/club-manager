<?php

namespace App\Http\Controllers;

use App\Http\Requests\PageAdmin\CopyBlockRequest;
use App\Models\Page;
use App\Models\PageRevision;
use App\Services\PagePublisher;
use App\Support\UploadRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A page's draft, its published revisions, the private preview link and copying a block to another page. Split out of PageAdminController; the URLs, route names and permissions are unchanged.
 */
class PageDraftController extends Controller
{
    /**
     * An uploaded picture's address (a full web address, or a path on this site such as /storage/x.jpg).
     */

    /**
     * The fields shared by a full save, a draft and a copy: what the visitor reads on the page.
     *
     * @return array<string, mixed>
     */
    private function validatedContent(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'share_image' => ['nullable', 'string', 'max:2048', UploadRules::IMAGE_URL],
            'blocks' => 'array|max:200',
        ]);
    }

    private function pageOf(string $clubSlug, int $id): Page
    {
        $club = $this->findClub($clubSlug);

        return Page::where('club_id', $club->id)->findOrFail($id);
    }

    /**
     * Keep edits to a live page aside as an unpublished draft. Visitors keep seeing the live version.
     */
    public function saveDraft(Request $request, string $clubSlug, int $id, PagePublisher $publisher): RedirectResponse
    {
        $page = $this->pageOf($clubSlug, $id);

        if (! $page->is_published) {
            throw ValidationException::withMessages(['title' => 'Only a published page can have a draft. Save the page instead.']);
        }

        $publisher->saveDraft($page, $this->validatedContent($request));

        return redirect()->route('admin.pages.edit', ['clubSlug' => $clubSlug, 'id' => $page->id])->with('success', 'Draft saved. Visitors still see the live page.');
    }

    public function discardDraft(string $clubSlug, int $id, PagePublisher $publisher): RedirectResponse
    {
        $publisher->discardDraft($this->pageOf($clubSlug, $id));

        return redirect()->route('admin.pages.edit', ['clubSlug' => $clubSlug, 'id' => $id])->with('success', 'Draft discarded.');
    }

    public function publishDraft(Request $request, string $clubSlug, int $id, PagePublisher $publisher): RedirectResponse
    {
        $page = $this->pageOf($clubSlug, $id);

        abort_unless($page->hasDraft(), 422, 'There is no draft to publish.');

        $publisher->publishDraft($page, $request->user());

        return redirect()->route('admin.pages.edit', ['clubSlug' => $clubSlug, 'id' => $page->id])->with('success', 'Draft published.');
    }

    /**
     * The secret link that shows this page, draft included, to anyone who has it. Asking again with `renew`
     * makes a new link and switches the old one off.
     */
    public function previewLink(Request $request, string $clubSlug, int $id): JsonResponse
    {
        $page = $this->pageOf($clubSlug, $id);

        if (! $page->preview_token || $request->boolean('renew')) {
            $page->forceFill(['preview_token' => Str::random(40)])->save();
        }

        $url = route('public.site', $page->is_homepage ? ['clubSlug' => $clubSlug] : ['clubSlug' => $clubSlug, 'pageSlug' => $page->slug]);

        return response()->json(['url' => $url.'?preview='.$page->preview_token]);
    }

    /**
     * Earlier saves of a page, newest first.
     */
    public function revisions(string $clubSlug, int $id): JsonResponse
    {
        $page = $this->pageOf($clubSlug, $id);

        return response()->json([
            'revisions' => $page->revisions()->with('user:id,name')->latest('id')->limit(PagePublisher::keepFor($page->club))->get()->map(fn (PageRevision $revision) => [
                'id' => $revision->id,
                'created_at' => $revision->created_at?->toIso8601String(),
                'user' => $revision->user?->name,
                'source' => $revision->source,
                'title' => $revision->title,
                'block_count' => count($revision->blocks ?? []),
            ])->values(),
        ]);
    }

    /**
     * One earlier version's content, for loading into the editor (nothing is changed until the admin saves).
     */
    public function revision(string $clubSlug, int $id, int $revisionId): JsonResponse
    {
        $revision = $this->pageOf($clubSlug, $id)->revisions()->findOrFail($revisionId);

        return response()->json(['content' => [
            'title' => $revision->title,
            'meta_title' => $revision->meta_title,
            'meta_description' => $revision->meta_description,
            'share_image' => $revision->share_image,
            'blocks' => $revision->blocks ?? [],
        ]]);
    }

    /**
     * Add a copy of an element to the end of another page of this club.
     */
    public function copyBlock(CopyBlockRequest $request, string $clubSlug, int $id, PagePublisher $publisher): JsonResponse
    {
        $target = $this->pageOf($clubSlug, $id);

        // Validated as a whole: a rule on a nested key would make validated() drop the rest of the element.

        $block = (array) $request->input('block');

        if (! is_string($block['type'] ?? null) || $block['type'] === '' || strlen($block['type']) > 50) {
            throw ValidationException::withMessages(['block' => 'That is not an element that can be copied.']);
        }

        if (strlen((string) json_encode($block)) > 200000) {
            throw ValidationException::withMessages(['block' => 'That element is too large to copy.']);
        }

        // The copy is a new element: it must not share an id with the original, and it is sanitised like any save.
        $block['id'] = 'block-'.now()->timestamp.'-'.Str::lower(Str::random(4));

        $publisher->appendBlock($target, $block, $request->user());

        return response()->json(['ok' => true, 'title' => $target->title, 'to_draft' => $target->hasDraft()]);
    }
}
