<?php

namespace Launchpad\Http\Controllers\Workspaces;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Launchpad\Http\Ui;
use Launchpad\Media\Browse;
use Launchpad\Media\Library;
use Launchpad\Models\MediaFile;
use Launchpad\Models\MediaFolder;
use Launchpad\Models\MediaTag;
use Launchpad\Workspaces\Ability;
use Launchpad\Workspaces\CurrentWorkspace;
use Symfony\Component\HttpFoundation\Response;

/** A team's media library. Any member may browse, add and edit; whoever added a file, or manages the team, may delete it. Every address answers JSON to a script and a redirect to a plain form. */
class MediaController extends Controller
{
    public function __construct(private readonly CurrentWorkspace $current, private readonly Library $library) {}

    public function index(Request $request): View|Responsable
    {
        $workspace = $this->current->workspace;
        $browse = $this->browse($request);

        return Ui::render('workspaces.media', ['workspace' => $workspace, 'tree' => $this->tree()] + $browse, fn () => [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'tree' => $this->tree(),
            'urls' => ['browse' => route('workspace.media.browse', $workspace->slug), 'store' => route('workspace.media.store', $workspace->slug), 'check' => route('workspace.media.check', $workspace->slug), 'folders' => route('workspace.media.folders.store', $workspace->slug)],
            'accept' => '.'.implode(',.', (array) config('launchpad.media.mimes')),
        ] + $browse);
    }

    private function browse(Request $request): array
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'], 'kind' => ['nullable', 'in:image,video,audio,document,other'], 'tag' => ['nullable', 'string', 'max:100'],
            'month' => ['nullable', 'date_format:Y-m'], 'sort' => ['nullable', 'in:newest,oldest,name,size'], 'per_page' => ['nullable', 'integer'], 'folder' => ['nullable', 'integer'],
        ]) + ['trashed' => $request->boolean('trashed'), 'everywhere' => $request->boolean('everywhere')];
        $trashed = $filters['trashed'];
        $result = Browse::for($this->current->workspace, $filters);
        $folder = $trashed || empty($filters['folder']) ? null : MediaFolder::findOrFail((int) $filters['folder']);
        $files = $result['files'];

        return [
            'trashed' => $trashed,
            'folder' => $folder ? $this->folderJson($folder) : null,
            'breadcrumbs' => Browse::breadcrumbs($folder),
            'folders' => $result['folders']->map(fn ($f) => $this->folderJson($f))->all(),
            'files' => $files->getCollection()->map(fn ($f) => $this->fileJson($f))->all(),
            'page' => $files->currentPage(), 'pages' => $files->lastPage(), 'total' => $files->total(),
            'filters' => ['q' => $filters['q'] ?? '', 'kind' => $filters['kind'] ?? '', 'tag' => $filters['tag'] ?? '', 'month' => $filters['month'] ?? '', 'sort' => $filters['sort'] ?? 'newest', 'everywhere' => $filters['everywhere']],
            'used' => $this->library->used($this->current->workspace), 'quota' => $this->library->quota($this->current->workspace),
            'tags' => MediaTag::orderBy('name_key')->pluck('name')->all(),
        ];
    }

    public function browseJson(Request $request): JsonResponse
    {
        return response()->json($this->browse($request));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['file' => Library::rules(), 'folder_id' => ['nullable', 'integer']], Library::messages());
        $file = $this->library->store($this->current->workspace, $request->file('file'), $this->folder($request, 'folder_id'), $request->user());

        return $this->answer($request, $this->fileJson($file->load(['tags', 'uploader'])), __(':name added.', ['name' => $file->name]), 201);
    }

    public function check(Request $request): JsonResponse
    {
        $request->validate(['hash' => ['required', 'string', 'size:64']]);
        $file = $this->library->duplicateOf($this->current->workspace, strtolower($request->string('hash')));

        return response()->json(['file' => $file ? $this->fileJson($file->load(['tags', 'uploader'])) : null]);
    }

    public function show(string $slug, string $file): Response
    {
        $row = MediaFile::withTrashed()->findOrFail($file);
        // no svg: it can carry script
        $inline = (str_starts_with($row->mime, 'image/') && $row->mime !== 'image/svg+xml') || $row->mime === 'application/pdf';

        return Storage::disk($row->disk)->response($row->path, $row->name, ['Content-Type' => $row->mime, 'X-Content-Type-Options' => 'nosniff'], $inline ? 'inline' : 'attachment');
    }

    public function update(Request $request, string $slug, string $file): JsonResponse|RedirectResponse
    {
        $row = MediaFile::findOrFail($file);
        // the page's form without JavaScript sends its tags as one box, with commas between
        $request->has('tags_text') && $request->merge(['tags' => array_values(array_filter(array_map('trim', explode(',', (string) $request->input('tags_text')))))]);
        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:200'], 'alt' => ['sometimes', 'nullable', 'string', 'max:500'], 'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'tags' => ['sometimes', 'array', 'max:50'], 'tags.*' => ['string', 'max:100'], 'folder_id' => ['sometimes', 'nullable', 'integer'],
        ]);
        // a folder in the request is checked before anything is changed
        $folder = $request->has('folder_id') ? $this->folder($request, 'folder_id') : null;

        DB::transaction(function () use ($request, $row, $folder) {
            $this->library->update($row, $request->only(['name', 'alt', 'notes', 'tags']));
            $request->has('folder_id') && $this->library->moveFile($row, $folder);
        });

        return $this->answer($request, $this->fileJson($row->refresh()->load(['tags', 'uploader'])), __('Saved.'));
    }

    public function trash(Request $request, string $slug, string $file): JsonResponse|RedirectResponse
    {
        $row = MediaFile::findOrFail($file);
        $this->mayDelete($row->uploaded_by);
        $this->library->trash($row);

        return $this->answer($request, $this->fileJson($row->refresh()->load(['tags', 'uploader'])), __(':name moved to the trash.', ['name' => $row->name]));
    }

    public function restore(Request $request, string $slug, string $file): JsonResponse|RedirectResponse
    {
        $row = MediaFile::onlyTrashed()->findOrFail($file);
        $this->mayDelete($row->uploaded_by);
        $this->library->restore($row);

        return $this->answer($request, $this->fileJson($row->refresh()->load(['tags', 'uploader'])), __(':name restored.', ['name' => $row->name]));
    }

    public function destroy(Request $request, string $slug, string $file): JsonResponse|RedirectResponse
    {
        $row = MediaFile::onlyTrashed()->findOrFail($file);
        $this->mayDelete($row->uploaded_by);
        $this->library->destroy($row);

        return $this->answer($request, ['id' => $row->id], __(':name deleted for good.', ['name' => $row->name]));
    }

    /** @return array<string, mixed> */
    public function fileJson(MediaFile $file): array
    {
        return [
            'id' => $file->id, 'name' => $file->name, 'kind' => $file->kind, 'mime' => $file->mime, 'size' => (int) $file->size,
            'size_label' => Number::fileSize((int) $file->size, precision: 1),
            'url' => route('workspace.media.show', [$this->current->workspace->slug, $file->id]),
            'alt' => $file->alt, 'notes' => $file->notes, 'tags' => $file->tags->pluck('name')->values()->all(), 'folder_id' => $file->folder_id,
            'uploaded_by_name' => $file->uploader?->name, 'created_at' => $file->created_at?->toAtomString(), 'trashed_at' => $file->deleted_at?->toAtomString(),
            'can_delete' => $this->canDelete($file->uploaded_by),
        ];
    }

    /** @return array<string, mixed> */
    public function folderJson(MediaFolder $folder): array
    {
        return ['id' => $folder->id, 'name' => $folder->name, 'parent_id' => $folder->parent_id, 'trashed_at' => $folder->deleted_at?->toAtomString(), 'can_delete' => $this->canDelete($folder->created_by)];
    }

    public function canManage(): bool
    {
        return $this->current->can(Ability::ManageWorkspace);
    }

    public function canDelete(mixed $addedBy): bool
    {
        return $this->canManage() || ($addedBy !== null && $addedBy === request()->user()->getKey());
    }

    public function mayDelete(mixed $addedBy): void
    {
        abort_unless($this->canDelete($addedBy), 403);
    }

    /** The folder a request names, which must be a live folder of this team (the library only checks the team) */
    public function folder(Request $request, string $field): ?MediaFolder
    {
        if (! $request->filled($field)) {
            return null;
        }

        return MediaFolder::find($request->integer($field)) ?? throw ValidationException::withMessages([$field => __('That folder is not in this team.')]);
    }

    public function answer(Request $request, array $json, string $status, int $code = 200): JsonResponse|RedirectResponse
    {
        return $request->wantsJson() ? response()->json($json, $code) : back()->with('status', $status);
    }

    /** every live folder, flat, for the page's folder list and the pickers */
    private function tree(): array
    {
        return MediaFolder::orderBy('path')->orderBy('name_key')->get()->map(fn ($f) => ['id' => $f->id, 'name' => $f->name, 'parent_id' => $f->parent_id, 'depth' => max(0, substr_count($f->path, '/') - 1)])->all();
    }
}
