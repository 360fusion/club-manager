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
                'published_at' => now()->format('Y-m-d\TH:i'),
            ]);

        return Inertia::render('Admin/Posts/Form', [
            'club' => $club,
            'post' => $post,
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
            'content' => 'required|string',
            'status' => 'required|in:draft,published',
        ]);

        Post::updateOrCreate(
            ['id' => $validated['id'] ?? null, 'club_id' => $club->id],
            [
                'author_id' => $user->id ?? 1,
                'title' => $validated['title'],
                'slug' => $validated['slug'],
                'excerpt' => $validated['excerpt'] ?? '',
                'content' => $validated['content'],
                'status' => $validated['status'],
                'published_at' => $validated['status'] === 'published' ? now() : null,
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
