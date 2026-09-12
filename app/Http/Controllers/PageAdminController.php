<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Page;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PageAdminController extends Controller
{
    /**
     * Display a listing of club pages in admin builder.
     */
    public function index(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $pages = Page::where('club_id', $club->id)->orderBy('sort_order')->get();

        return Inertia::render('Admin/PageList', [
            'club' => $club,
            'pages' => $pages,
        ]);
    }

    /**
     * Show page builder editor for creating or editing a page.
     */
    public function edit(string $clubSlug, ?int $id = null): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $page = $id
            ? Page::where('club_id', $club->id)->findOrFail($id)
            : new Page(['club_id' => $club->id, 'is_published' => true, 'show_in_navigation' => true, 'blocks' => []]);

        return Inertia::render('Admin/PageBuilder', [
            'club' => $club,
            'page' => $page,
        ]);
    }

    /**
     * Store or update a page with block layout.
     */
    public function store(Request $request, string $clubSlug)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'is_published' => 'boolean',
            'is_homepage' => 'boolean',
            'show_in_navigation' => 'boolean',
            'blocks' => 'array',
        ]);

        if ($validated['is_homepage'] ?? false) {
            // Remove homepage flag from other pages
            Page::where('club_id', $club->id)->update(['is_homepage' => false]);
        }

        $page = Page::updateOrCreate(
            ['id' => $validated['id'] ?? null, 'club_id' => $club->id],
            [
                'title' => $validated['title'],
                'slug' => $validated['slug'],
                'is_published' => $validated['is_published'] ?? true,
                'is_homepage' => $validated['is_homepage'] ?? false,
                'show_in_navigation' => $validated['show_in_navigation'] ?? true,
                'blocks' => $validated['blocks'] ?? [],
            ]
        );

        return redirect()->route('admin.pages.index', ['clubSlug' => $club->slug]);
    }
}
