<?php

namespace Launchpad\Http\Controllers\Site;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Launchpad\Http\Ui;
use Launchpad\Models\RoadmapItem;

/** The public roadmap: what is being built, planned and done, and ideas people can suggest and vote for */
class RoadmapController
{
    public function index(Request $request): View|Responsable
    {
        $user = $request->user();
        $items = RoadmapItem::visibleTo($user)->orderByDesc('votes')->latest('id')->get();
        $voted = $user ? DB::table('roadmap_votes')->where('user_id', $user->getKey())->whereIn('roadmap_item_id', $items->pluck('id'))->pluck('roadmap_item_id')->all() : [];
        // done: the latest ten; the rest by votes
        $groups = collect(['review', ...RoadmapItem::SHOWN])->mapWithKeys(fn ($status) => [$status => $status === 'done'
            ? $items->where('status', 'done')->sortByDesc('updated_at')->take(10)->values()
            : $items->where('status', $status)->values()]);

        return Ui::render('site.roadmap', ['groups' => $groups, 'voted' => $voted], fn () => [
            'groups' => $groups->map(fn ($list, $status) => ['status' => $status, 'label' => __(RoadmapItem::HEADINGS[$status]), 'items' => $list->map(fn (RoadmapItem $i) => [
                'id' => $i->id, 'title' => $i->title, 'description' => $i->description, 'votes' => $i->votes, 'voted' => in_array($i->id, $voted, true),
            ])->all()])->values()->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:2000']]);

        $item = RoadmapItem::create($data + ['author_id' => $request->user()->getKey()]);
        $this->vote($request, $item);

        return back()->with('status', __('Thank you. Your idea shows to others once our team has looked at it.'));
    }

    /** Votes, or takes the vote back */
    public function toggle(Request $request, RoadmapItem $item): RedirectResponse
    {
        abort_unless(in_array($item->status, ['idea', 'planned', 'building'], true), 404);

        $deleted = DB::table('roadmap_votes')->where(['roadmap_item_id' => $item->id, 'user_id' => $request->user()->getKey()])->delete();
        $deleted ? $item->countVotes() : $this->vote($request, $item);

        return back();
    }

    private function vote(Request $request, RoadmapItem $item): void
    {
        DB::table('roadmap_votes')->insertOrIgnore(['roadmap_item_id' => $item->id, 'user_id' => $request->user()->getKey(), 'created_at' => now()]);
        $item->countVotes();
    }
}
