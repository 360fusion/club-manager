<?php

namespace App\Domains\ClubAccounting\Livewire\Members;

use App\Domains\ClubAccounting\Livewire\Concerns\ShowsNotice;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberImport;
use App\Domains\ClubAccounting\Models\MemberImportMapping;
use App\Domains\ClubAccounting\Models\MemberImportRow;
use App\Domains\ClubAccounting\Services\MemberImport\MemberImportAnalyzer;
use App\Domains\ClubAccounting\Services\MemberImport\MemberImportFields;
use App\Domains\ClubAccounting\Services\MemberImport\MemberImportRunner;
use App\Domains\ClubAccounting\Services\MemberImport\MemberImportUndo;
use App\Domains\ClubAccounting\Services\MemberImport\MemberImportValues;
use App\Models\Club;
use App\Support\ClubAccess;
use App\Support\Csv;
use App\Support\CsvReader;
use App\Support\UploadRules;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The members import wizard: upload, match columns, review duplicates and errors, done.
 */
class MemberImportPage extends Component
{
    use ShowsNotice;
    use WithFileUploads;
    use WithPagination;

    private const ROW_ACTIONS = ['skip', 'create', 'fill', 'overwrite'];

    #[Locked]
    public string $clubSlug;

    #[Locked]
    public ?int $importId = null;

    #[Locked]
    public string $step = 'upload';

    public $file = null;

    /** @var array<int, string> Column index => member field ('' leaves the column out). */
    public array $mapping = [];

    public string $dateOrder = 'dmy';

    public string $mappingName = '';

    public string $tab = 'all';

    public string $fileDuplicates = 'keep_first';

    public bool $invite = false;

    public ?int $expandedRow = null;

    public function mount(string $clubSlug): void
    {
        $this->clubSlug = $clubSlug;
        $club = $this->getClub();

        // Pick up where the admin left off if they reloaded the page mid-import.
        $open = MemberImport::where('club_id', $club->id)->where('user_id', auth()->id())->where('status', MemberImport::STAGED)->latest('id')->first();

        if ($open) {
            $this->loadImport($open);
        }
    }

    public function updatingTab(): void
    {
        $this->resetPage();
    }

    // ---- step 1: upload ----

    public function updatedFile(): void
    {
        $club = $this->authorizedClub();

        $this->resetErrorBag();

        $this->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']], [
            'file.mimes' => 'Please upload a CSV file. In Excel or Sheets, choose File, then Save as or Download, then CSV.',
            'file.max' => 'That file is over 5 MB. Please split it into smaller files.',
        ]);

        if ($unsafe = UploadRules::assertSafeUpload($this->file)) {
            $this->addError('file', $unsafe);
            $this->file = null;

            return;
        }

        $hash = hash_file('sha256', $this->file->getRealPath());
        $path = 'member-imports/'.$club->id.'/'.Str::uuid().'.csv';
        $stored = Storage::disk('local')->putFileAs('member-imports/'.$club->id, $this->file, basename($path));

        try {
            $document = CsvReader::read(Storage::disk('local')->path($stored));
        } catch (InvalidArgumentException $e) {
            Storage::disk('local')->delete($stored);
            $this->addError('file', $e->getMessage());
            $this->file = null;

            return;
        }

        $this->discardOpenImports($club);

        $previous = MemberImport::where('club_id', $club->id)->where('file_hash', $hash)->where('status', MemberImport::IMPORTED)->latest('imported_at')->first();

        $import = MemberImport::create([
            'club_id' => $club->id,
            'user_id' => auth()->id(),
            'filename' => mb_substr($this->file->getClientOriginalName(), 0, 255),
            'file_hash' => $hash,
            'stored_path' => $stored,
            'headers' => $document['headers'],
            'mapping' => $this->initialMapping($club, $document['headers']),
            'options' => [
                'date_order' => MemberImportValues::detectDateOrder($this->dateSamples($document)),
                'samples' => $this->samples($document),
                'already_imported_at' => $previous?->imported_at?->toDateTimeString(),
                'row_count' => count($document['rows']),
            ],
        ]);

        $this->file = null;
        $this->loadImport($import);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $this->authorizedClub();

        return response()->streamDownload(function () {
            echo "\xEF\xBB\xBF";
            echo Csv::line(array_keys(MemberImportFields::EXPORT_COLUMNS));
            echo Csv::line(MemberImportFields::exampleRow());
        }, 'members-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    // ---- step 2: match columns ----

    public function loadSavedMapping(int $mappingId): void
    {
        $club = $this->authorizedClub();
        $import = $this->stagedImport($club);
        $saved = MemberImportMapping::where('club_id', $club->id)->findOrFail($mappingId);

        $this->mapping = $this->applyByHeader($import->headers ?? [], $saved->mapping);
    }

    public function deleteSavedMapping(int $mappingId): void
    {
        $club = $this->authorizedClub();
        MemberImportMapping::where('club_id', $club->id)->whereKey($mappingId)->delete();
        $this->notify('Saved mapping removed.');
    }

    public function autoMatch(): void
    {
        $club = $this->authorizedClub();
        $import = $this->stagedImport($club);

        $this->mapping = array_map(fn ($field) => $field ?? '', MemberImportFields::autoMap($import->headers ?? []));
    }

    public function reviewRows(): void
    {
        $club = $this->authorizedClub();
        $import = $this->stagedImport($club);
        $mapping = $this->cleanMapping();

        if ($problem = MemberImportAnalyzer::mappingProblem($mapping)) {
            $this->notify($problem, 'error');

            return;
        }

        $this->validate(['dateOrder' => 'required|in:dmy,mdy,ymd', 'mappingName' => 'nullable|string|max:100']);

        $import->update([
            'mapping' => $mapping,
            'options' => array_merge($import->options ?? [], ['date_order' => $this->dateOrder]),
        ]);

        try {
            app(MemberImportAnalyzer::class)->analyse($import);
        } catch (InvalidArgumentException $e) {
            $this->notify($e->getMessage(), 'error');

            return;
        }

        if (trim($this->mappingName) !== '') {
            MemberImportMapping::updateOrCreate(
                ['club_id' => $club->id, 'name' => trim($this->mappingName)],
                ['header_signature' => MemberImportFields::signature($import->headers ?? []), 'mapping' => $this->mappingByHeader($import->headers ?? [], $mapping)],
            );
        }

        $this->mappingName = '';
        $this->tab = 'all';
        $this->step = 'review';
        $this->resetPage();
    }

    public function backToMatch(): void
    {
        $this->stagedImport($this->authorizedClub());
        $this->step = 'match';
        $this->expandedRow = null;
    }

    // ---- step 3: review ----

    public function setRowAction(int $rowId, string $action): void
    {
        $club = $this->authorizedClub();
        $import = $this->stagedImport($club);
        $row = $import->rows()->findOrFail($rowId);

        if (! in_array($action, $this->allowedActions($row), true)) {
            return;
        }

        $row->update(['action' => $action]);
    }

    public function applyToTab(string $action): void
    {
        $club = $this->authorizedClub();
        $import = $this->stagedImport($club);

        if (! in_array($this->tab, [MemberImportRow::DUPLICATE, MemberImportRow::POSSIBLE], true) || ! in_array($action, self::ROW_ACTIONS, true)) {
            return;
        }

        $import->rows()
            ->where('status', $this->tab)
            ->when(in_array($action, ['fill', 'overwrite'], true), fn ($q) => $q->whereNotNull('match_member_id'))
            ->update(['action' => $action]);

        $this->notify('Updated every '.($this->tab === MemberImportRow::DUPLICATE ? 'duplicate' : 'possible duplicate').' in this list that can take that choice.');
    }

    public function toggleRow(int $rowId): void
    {
        $this->expandedRow = $this->expandedRow === $rowId ? null : $rowId;
    }

    public function updatedFileDuplicates(): void
    {
        $import = $this->stagedImport($this->authorizedClub());
        $this->validate(['fileDuplicates' => 'in:keep_first,keep_last,keep_both']);
        $import->update(['options' => array_merge($import->options ?? [], ['file_duplicates' => $this->fileDuplicates])]);
    }

    public function downloadErrors(): StreamedResponse
    {
        $club = $this->authorizedClub();
        $import = MemberImport::where('club_id', $club->id)->findOrFail($this->importId);
        $headers = $import->headers ?? [];

        return response()->streamDownload(function () use ($import, $headers) {
            echo "\xEF\xBB\xBF";
            echo Csv::line(array_merge($headers, ['Problem']));

            $import->rows()->where('status', MemberImportRow::ERROR)->orderBy('row_number')->each(function (MemberImportRow $row) use ($headers) {
                $cells = array_pad($row->raw ?? [], count($headers), '');
                echo Csv::line(array_merge($cells, [implode('; ', $row->errors ?? [])]));
            });
        }, 'members-import-rows-to-fix.csv', ['Content-Type' => 'text/csv']);
    }

    // ---- step 4: import, undo ----

    public function runImport(): void
    {
        $club = $this->authorizedClub();
        $import = $this->stagedImport($club);

        if ($import->rows()->whereIn('action', ['create', 'fill', 'overwrite'])->where('status', '!=', MemberImportRow::ERROR)->doesntExist()) {
            $this->notify('Nothing to import. Every row is skipped or has an error.', 'error');

            return;
        }

        try {
            app(MemberImportRunner::class)->run($import, auth()->user(), $this->invite);
        } catch (RuntimeException $e) {
            $this->notify($e->getMessage(), 'error');

            return;
        }

        $this->step = 'done';
        $this->expandedRow = null;
    }

    public function undoImport(int $importId): void
    {
        $club = $this->authorizedClub();
        $import = MemberImport::where('club_id', $club->id)->findOrFail($importId);

        try {
            $summary = app(MemberImportUndo::class)->undo($import);
        } catch (RuntimeException $e) {
            $this->notify($e->getMessage(), 'error');

            return;
        }

        $message = "Undone: {$summary['removed']} removed, {$summary['restored']} restored.";

        if ($summary['blocked'] !== []) {
            $message .= ' Left alone: '.implode('; ', $summary['blocked']).'.';
        }

        $this->notify($message, $summary['blocked'] === [] ? 'success' : 'error');
    }

    public function startOver(): void
    {
        $club = $this->authorizedClub();
        $this->discardOpenImports($club);

        $this->reset(['importId', 'mapping', 'mappingName', 'expandedRow', 'invite', 'tab']);
        $this->step = 'upload';
        $this->resetPage();
    }

    // ---- internals ----

    private function getClub(): Club
    {
        return Club::where('slug', $this->clubSlug)->firstOrFail();
    }

    private function authorizedClub(): Club
    {
        $club = $this->getClub();
        ClubAccess::authorize(auth()->user(), $club, 'manage_members');

        return $club;
    }

    /**
     * The import being worked on, which must still be waiting to run.
     */
    private function stagedImport(Club $club): MemberImport
    {
        return MemberImport::where('club_id', $club->id)->where('status', MemberImport::STAGED)->findOrFail($this->importId);
    }

    private function loadImport(MemberImport $import): void
    {
        $this->importId = $import->id;
        $this->mapping = [];

        foreach ($import->mapping ?? [] as $index => $field) {
            $this->mapping[(int) $index] = $field ?? '';
        }

        $this->dateOrder = $import->option('date_order', 'dmy');
        $this->fileDuplicates = $import->option('file_duplicates', 'keep_first');
        $this->step = $import->total_rows > 0 ? 'review' : 'match';
    }

    /**
     * Cancel this admin's unfinished imports and delete their stored files.
     */
    private function discardOpenImports(Club $club): void
    {
        MemberImport::where('club_id', $club->id)->where('user_id', auth()->id())->where('status', MemberImport::STAGED)->each(function (MemberImport $open) {
            if ($open->stored_path) {
                Storage::disk('local')->delete($open->stored_path);
            }

            $open->rows()->delete();
            $open->update(['status' => MemberImport::CANCELLED, 'stored_path' => null]);
        });
    }

    /**
     * A saved mapping for this layout of headers if the lodge has one, otherwise a guess from the header names.
     *
     * @param  array<int, string>  $headers
     * @return array<int, string|null>
     */
    private function initialMapping(Club $club, array $headers): array
    {
        $saved = MemberImportMapping::where('club_id', $club->id)->where('header_signature', MemberImportFields::signature($headers))->latest('id')->first();

        if ($saved) {
            return array_map(fn ($field) => $field === '' ? null : $field, $this->applyByHeader($headers, $saved->mapping));
        }

        return MemberImportFields::autoMap($headers);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<string, string|null>  $byHeader  normalised header => field
     * @return array<int, string>
     */
    private function applyByHeader(array $headers, array $byHeader): array
    {
        $mapping = [];

        foreach ($headers as $index => $header) {
            $mapping[$index] = $byHeader[MemberImportFields::normalise($header)] ?? '';
        }

        return $mapping;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string|null>  $mapping
     * @return array<string, string|null>
     */
    private function mappingByHeader(array $headers, array $mapping): array
    {
        $byHeader = [];

        foreach ($headers as $index => $header) {
            $byHeader[MemberImportFields::normalise($header)] = $mapping[$index] ?? null;
        }

        return $byHeader;
    }

    /**
     * The chosen mapping as column index => field key or null, ignoring anything that is not a real field.
     *
     * @return array<int, string|null>
     */
    private function cleanMapping(): array
    {
        $valid = array_keys(MemberImportFields::targets());
        $mapping = [];

        foreach ($this->mapping as $index => $field) {
            $mapping[(int) $index] = in_array($field, $valid, true) ? $field : null;
        }

        return $mapping;
    }

    /**
     * @param  array{headers: array<int, string>, rows: array<int, array<int, string>>}  $document
     * @return array<int, array<int, string>> up to three example values per column
     */
    private function samples(array $document): array
    {
        $samples = [];

        foreach (array_keys($document['headers']) as $index) {
            $samples[$index] = array_slice(array_values(array_filter(array_map(fn ($row) => mb_substr($row[$index] ?? '', 0, 60), $document['rows']), fn ($v) => $v !== '')), 0, 3);
        }

        return $samples;
    }

    /**
     * @param  array{headers: array<int, string>, rows: array<int, array<int, string>>}  $document
     * @return array<int, string>
     */
    private function dateSamples(array $document): array
    {
        $dateColumns = array_keys(array_filter(MemberImportFields::autoMap($document['headers']), fn ($f) => $f !== null && str_starts_with($f, 'date_of_')));
        $values = [];

        foreach ($document['rows'] as $row) {
            foreach ($dateColumns as $column) {
                $values[] = $row[$column] ?? '';
            }
        }

        return $values;
    }

    /**
     * How many rows each choice will affect, so the Import button can say what it is about to do.
     *
     * @return array{create: int, update: int, skip: int, error: int}
     */
    private function plan(MemberImport $import): array
    {
        $counts = $import->rows()->selectRaw('status, action, count(*) as total')->groupBy('status', 'action')->get();

        $plan = ['create' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];

        foreach ($counts as $group) {
            $key = match (true) {
                $group->status === MemberImportRow::ERROR => 'error',
                $group->action === 'create' => 'create',
                in_array($group->action, ['fill', 'overwrite'], true) => 'update',
                default => 'skip',
            };

            $plan[$key] += (int) $group->total;
        }

        return $plan;
    }

    /**
     * What may be chosen for a row.
     *
     * @return array<int, string>
     */
    private function allowedActions(MemberImportRow $row): array
    {
        if ($row->status === MemberImportRow::ERROR) {
            return [];
        }

        if ($row->status === MemberImportRow::NEW) {
            return ['create', 'skip'];
        }

        if ($row->match_member_id) {
            return ['skip', 'fill', 'overwrite', 'create'];
        }

        return ['skip', 'create'];
    }

    public function render()
    {
        $club = $this->getClub();
        $import = $this->importId ? MemberImport::where('club_id', $club->id)->find($this->importId) : null;

        $rows = null;
        $expanded = null;

        if ($import && $this->step === 'review') {
            $rows = $import->rows()
                ->when($this->tab !== 'all', fn ($q) => $q->where('status', $this->tab))
                ->orderBy('row_number')
                ->paginate(25);

            if ($this->expandedRow) {
                $row = $import->rows()->find($this->expandedRow);
                $expanded = $row ? ['row' => $row, 'member' => $row->match_member_id ? Member::where('club_id', $club->id)->find($row->match_member_id) : null] : null;
            }
        }

        $mappingProblem = $import && $this->step === 'match' ? MemberImportAnalyzer::mappingProblem($this->cleanMapping()) : null;

        return view('livewire.members.member-import', [
            'club' => $club,
            'import' => $import,
            'rows' => $rows,
            'expanded' => $expanded,
            'targets' => MemberImportFields::targets(),
            'dateOrders' => MemberImportValues::DATE_ORDERS,
            'mappingProblem' => $mappingProblem,
            'savedMappings' => MemberImportMapping::where('club_id', $club->id)->orderBy('name')->get(),
            'history' => MemberImport::where('club_id', $club->id)->whereIn('status', [MemberImport::IMPORTED, MemberImport::UNDONE])->with('user')->latest('id')->limit(8)->get(),
            'matchedMembers' => $rows ? Member::where('club_id', $club->id)->whereIn('id', $rows->pluck('match_member_id')->filter()->all())->get()->keyBy('id') : collect(),
            'hasFileDuplicates' => $import && $this->step === 'review' ? $import->rows()->whereNotNull('duplicate_of_row')->exists() : false,
            'plan' => $import && $this->step === 'review' ? $this->plan($import) : [],
        ])->layout('components.layouts.app', [
            'title' => 'Import Members',
            'club' => $club,
        ]);
    }
}
