<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostAdminController extends Controller
{
    /**
     * Display a listing of club posts and news articles.
     */
    public function index(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $posts = Post::where('club_id', $club->id)
            ->with('author')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Admin/Posts/Index', [
            'club' => $club,
            'posts' => $posts,
        ]);
    }

    /**
     * Preview a news article inside the admin portal.
     */
    public function show(string $clubSlug, int $id): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $post = Post::where('club_id', $club->id)
            ->with('author')
            ->findOrFail($id);

        return Inertia::render('Admin/Posts/Show', [
            'club' => $club,
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'content' => $post->content,
                'blocks' => $post->blocks ?? [],
                'attachments' => $post->attachments ?? [],
                'cover_image_url' => $post->cover_image_url,
                'status' => $post->status,
                'published_at' => ($post->published_at ?? $post->created_at)?->format('M d, Y'),
                'author_name' => $post->author?->name ?? 'Club Admin',
            ],
        ]);
    }

    /**
     * Show form for creating or editing a blog post.
     */
    public function edit(string $clubSlug, ?int $id = null): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $post = $id
            ? Post::where('club_id', $club->id)->findOrFail($id)
            : new Post([
                'club_id' => $club->id,
                'status' => 'published',
                'published_at' => \Carbon\Carbon::now(),
                'expires_at' => null,
            ]);

        $postArray = $post->toArray();
        $publishedAt = $post->published_at ?? ($post->created_at ?? \Carbon\Carbon::now());
        $postArray['published_at'] = $publishedAt ? $publishedAt->format('Y-m-d\TH:i') : \Carbon\Carbon::now()->format('Y-m-d\TH:i');
        $postArray['expires_at'] = $post->expires_at ? $post->expires_at->format('Y-m-d\TH:i') : null;

        return Inertia::render('Admin/Posts/Form', [
            'club' => $club,
            'post' => $postArray,
        ]);
    }

    /**
     * Store or update a blog post.
     */
    public function store(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $user = $request->user();

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'nullable|string',
            'status' => 'required|in:draft,published',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'cover_image_url' => 'nullable|string|max:1000',
            'cover_image' => 'nullable|image|max:4096',
            'blocks' => 'nullable|array',
            'existing_attachments' => 'nullable|array',
            'new_attachments.*' => 'nullable|file|max:10240',
            'action_type' => 'nullable|string',
        ]);

        $coverImageUrl = $validated['cover_image_url'] ?? null;
        if ($request->hasFile('cover_image') && $request->file('cover_image')->isValid()) {
            $media = $club->addMediaFromRequest('cover_image')->toMediaCollection('news');
            $coverImageUrl = "/storage/{$media->id}/{$media->file_name}";
        }

        $rawAttachments = $validated['existing_attachments'] ?? [];
        $attachments = array_values(array_filter($rawAttachments, function ($att) {
            if (!is_array($att)) return false;
            if (!empty($att['isPendingFile'])) return false;
            if (isset($att['url']) && str_starts_with($att['url'], 'blob:')) return false;
            return true;
        }));

        if ($request->hasFile('new_attachments')) {
            foreach ($request->file('new_attachments') as $file) {
                if ($file && $file->isValid()) {
                    $name = $file->getClientOriginalName();
                    $bytes = $file->getSize();
                    $mime = $file->getClientMimeType();

                    $media = $club->addMedia($file)->toMediaCollection('news');
                    $sizeFormatted = $bytes >= 1048576 
                        ? round($bytes / 1048576, 1) . ' MB' 
                        : round($bytes / 1024, 1) . ' KB';

                    $attachments[] = [
                        'name' => $name,
                        'url' => "/storage/{$media->id}/{$media->file_name}",
                        'size' => $sizeFormatted,
                        'mime_type' => $mime,
                    ];
                }
            }
        }

        $blocks = $validated['blocks'] ?? [];

        // 1. Process files from block_files map (e.g. block_files[block-key])
        if ($request->hasFile('block_files')) {
            foreach ($request->file('block_files') as $blockKey => $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store("post_images/{$club->id}", 'public');
                    $url = "/storage/{$path}";

                    foreach ($blocks as &$b) {
                        if (isset($b['id']) && (string)$b['id'] === (string)$blockKey && $b['type'] === 'image') {
                            $b['url'] = $url;
                        }
                        if (isset($b['type']) && $b['type'] === 'images' && isset($b['items']) && is_array($b['items'])) {
                            foreach ($b['items'] as &$gItem) {
                                if (isset($gItem['id']) && (string)$gItem['id'] === (string)$blockKey) {
                                    $gItem['url'] = $url;
                                }
                            }
                        }
                    }
                }
            }
        }

        // 2. Process files uploaded directly within blocks array (blocks[i][file] or blocks[i][items][j][file])
        if ($request->hasFile('blocks')) {
            $blockFiles = $request->file('blocks');
            foreach ($blockFiles as $i => $blockFileData) {
                if (isset($blockFileData['file']) && $blockFileData['file']->isValid()) {
                    $path = $blockFileData['file']->store("post_images/{$club->id}", 'public');
                    if (isset($blocks[$i])) {
                        $blocks[$i]['url'] = "/storage/{$path}";
                    }
                }
                if (isset($blockFileData['items']) && is_array($blockFileData['items'])) {
                    foreach ($blockFileData['items'] as $j => $gFileData) {
                        if (isset($gFileData['file']) && $gFileData['file']->isValid()) {
                            $path = $gFileData['file']->store("post_images/{$club->id}", 'public');
                            if (isset($blocks[$i]['items'][$j])) {
                                $blocks[$i]['items'][$j]['url'] = "/storage/{$path}";
                            }
                        }
                    }
                }
            }
        }

        // 3. Clean up transient file objects and unsaved blob: URLs
        foreach ($blocks as &$b) {
            unset($b['file']);
            if (isset($b['url']) && str_starts_with($b['url'], 'blob:')) {
                $b['url'] = '';
            }
            if (isset($b['items']) && is_array($b['items'])) {
                foreach ($b['items'] as &$gItem) {
                    unset($gItem['file']);
                    if (isset($gItem['url']) && str_starts_with($gItem['url'], 'blob:')) {
                        $gItem['url'] = '';
                    }
                }
            }
        }

        $publishedAt = !empty($validated['published_at']) ? $validated['published_at'] : null;
        $expiresAt = !empty($validated['expires_at']) ? $validated['expires_at'] : null;

        $post = Post::updateOrCreate(
            ['id' => $validated['id'] ?? null, 'club_id' => $club->id],
            [
                'author_id' => $user->id ?? 1,
                'title' => $validated['title'],
                'slug' => $validated['slug'],
                'excerpt' => $validated['excerpt'] ?? '',
                'content' => $validated['content'] ?? '',
                'blocks' => $blocks,
                'attachments' => $attachments,
                'cover_image_url' => $coverImageUrl,
                'status' => $validated['status'],
                'published_at' => $publishedAt,
                'expires_at' => $expiresAt,
            ]
        );

        $actionType = $validated['action_type'] ?? $request->input('action_type', null);

        if ($actionType === 'save_and_new') {
            return redirect()->route('admin.posts.create', ['clubSlug' => $club->slug])
                ->with('success', 'Article saved successfully. You can now create another post.');
        }

        if ($actionType === 'save_and_duplicate') {
            $duplicate = $post->replicate(['slug']);
            $duplicate->title = $post->title . ' (Copy)';
            $duplicate->slug = $post->slug . '-copy-' . time();
            $duplicate->save();

            return redirect()->route('admin.posts.edit', ['clubSlug' => $club->slug, 'id' => $duplicate->id])
                ->with('success', 'Article saved and duplicated successfully.');
        }

        if ($actionType === 'save' || $actionType === 'save_and_edit') {
            return redirect()->route('admin.posts.edit', ['clubSlug' => $club->slug, 'id' => $post->id])
                ->with('success', 'Article saved successfully.');
        }

        // Default when action_type is save_and_close, save_and_go_back, or omitted:
        return redirect()->route('admin.posts.index', ['clubSlug' => $club->slug])
            ->with('success', 'Article saved successfully.');
    }

    /**
     * Delete a blog post.
     */
    public function destroy(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $post = Post::where('club_id', $club->id)->findOrFail($id);
        $post->delete();

        return redirect()->back()->with('success', 'Blog post deleted successfully.');
    }
}
