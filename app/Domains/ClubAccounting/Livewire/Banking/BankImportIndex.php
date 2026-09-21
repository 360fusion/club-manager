<?php

namespace App\Domains\ClubAccounting\Livewire\Banking;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Models\BankImport;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Services\BankStatementParserService;
use App\Models\Club;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class BankImportIndex extends Component
{
    use WithFileUploads, WithPagination;

    #[Locked]
    public string $clubSlug;

    public string $search = '';

    public ?string $statusFilter = null;

    // File Upload & Preview State
    public $statementFile;

    public ?array $previewBundle = null;

    public bool $showPreviewModal = false;

    public function mount(string $clubSlug): void
    {
        $this->clubSlug = $clubSlug;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatementFile(BankStatementParserService $parserService): void
    {
        $this->validate([
            'statementFile' => 'required|file|mimes:csv,txt,ofx,qfx|max:10240',
        ], [
            'statementFile.mimes' => 'Bank statement file must be a CSV, TXT, or OFX format export.',
        ]);

        $club = $this->getClub();
        $realPath = $this->statementFile->getRealPath();
        $originalName = $this->statementFile->getClientOriginalName();

        try {
            $this->previewBundle = $parserService->parseFile($realPath, $originalName, $club->id);
            $this->showPreviewModal = true;
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to parse bank statement file: '.$e->getMessage());
            $this->reset('statementFile');
        }
    }

    public function confirmImport(BankStatementParserService $parserService): void
    {
        if (empty($this->previewBundle)) {
            return;
        }

        $club = $this->getClub();

        $importBatch = $parserService->importParsedBundle($club, $this->previewBundle);

        session()->flash('success', "Bank statement '{$importBatch->filename}' imported successfully! {$importBatch->total_lines} new transaction lines staged for reconciliation.");

        $this->cancelPreview();
    }

    public function cancelPreview(): void
    {
        $this->reset(['statementFile', 'previewBundle', 'showPreviewModal']);
    }

    public function deleteImportBatch(int $importId): void
    {
        $club = $this->getClub();
        $import = BankImport::where('club_id', $club->id)->findOrFail($importId);
        $filename = $import->filename;

        $import->delete();
        session()->flash('success', "Import batch '{$filename}' and staged transactions deleted.");
    }

    public function deleteTransaction(int $transactionId): void
    {
        $club = $this->getClub();
        $tx = BankTransaction::where('club_id', $club->id)->findOrFail($transactionId);
        $tx->delete();

        session()->flash('success', 'Staged bank transaction deleted.');
    }

    private function getClub(): Club
    {
        return Club::where('slug', $this->clubSlug)->firstOrFail();
    }

    public function render()
    {
        $club = $this->getClub();

        // Staged Bank Transactions Query
        $txQuery = BankTransaction::with('import')
            ->where('club_id', $club->id);

        if (! empty($this->search)) {
            $txQuery->search($this->search);
        }

        if (! empty($this->statusFilter)) {
            $txQuery->where('status', $this->statusFilter);
        }

        $transactions = $txQuery->orderBy('transaction_date', 'desc')->paginate(20);

        // Previous Import Batches
        $importBatches = BankImport::where('club_id', $club->id)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        // Metrics
        $totalStagedCount = BankTransaction::where('club_id', $club->id)->count();
        $unmatchedCount = BankTransaction::where('club_id', $club->id)->unmatched()->count();
        $matchedCount = BankTransaction::where('club_id', $club->id)->matched()->count();
        $totalNetAmount = BankTransaction::where('club_id', $club->id)->sum('amount');

        return view('livewire.banking.bank-import-index', [
            'club' => $club,
            'transactions' => $transactions,
            'importBatches' => $importBatches,
            'totalStagedCount' => $totalStagedCount,
            'unmatchedCount' => $unmatchedCount,
            'matchedCount' => $matchedCount,
            'totalNetAmount' => $totalNetAmount,
            'statuses' => BankTransactionStatus::cases(),
        ])->layout('components.layouts.app', [
            'title' => 'Bank Statement Import Engine — '.$club->name,
            'club' => $club,
        ]);
    }
}
