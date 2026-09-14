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
                'published_at' => null,
                'expires_at' => null,
            ]);

        $postArray = $post->toArray();
        $postArray['published_at'] = $post->published_at ? $post->published_at->format('Y-m-d\TH:i') : null;
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
        ]);

        $coverImageUrl = $validated['cover_image_url'] ?? null;
        if ($request->hasFile('cover_image') && $request->file('cover_image')->isValid()) {
            $path = $request->file('cover_image')->store("post_images/{$club->id}", 'public');
            $coverImageUrl = "/storage/{$path}";
        }

        $attachments = $validated['existing_attachments'] ?? [];
        if ($request->hasFile('new_attachments')) {
            foreach ($request->file('new_attachments') as $file) {
                if ($file->isValid()) {
                    $path = $file->store("post_attachments/{$club->id}", 'public');
                    $bytes = $file->getSize();
                    $sizeFormatted = $bytes >= 1048576 
                        ? round($bytes / 1048576, 1) . ' MB' 
                        : round($bytes / 1024, 1) . ' KB';

                    $attachments[] = [
                        'name' => $file->getClientOriginalName(),
                        'url' => "/storage/{$path}",
                        'size' => $sizeFormatted,
                        'mime_type' => $file->getMimeType(),
                    ];
                }
            }
        }

        $blocks = $validated['blocks'] ?? [];

        if ($request->hasFile('block_files')) {
            foreach ($request->file('block_files') as $blockKey => $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store("post_images/{$club->id}", 'public');
                    $url = "/storage/{$path}";

                    foreach ($blocks as &$b) {
                        if (isset($b['id']) && $b['id'] === $blockKey && $b['type'] === 'image') {
                            $b['url'] = $url;
                        }
                        if (isset($b['type']) && $b['type'] === 'images' && isset($b['items']) && is_array($b['items'])) {
                            foreach ($b['items'] as &$gItem) {
                                if (isset($gItem['id']) && $gItem['id'] === $blockKey) {
                                    $gItem['url'] = $url;
                                }
                            }
                        }
                    }
                }
            }
        }

        $publishedAt = !empty($validated['published_at']) ? $validated['published_at'] : null;
        if (!$publishedAt && $validated['status'] === 'published') {
            $publishedAt = now();
        }

        $expiresAt = !empty($validated['expires_at']) ? $validated['expires_at'] : null;

        Post::updateOrCreate(
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

        return redirect()->route('admin.posts.index', ['clubSlug' => $club->slug])
            ->with('success', 'Blog post saved successfully.');
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
