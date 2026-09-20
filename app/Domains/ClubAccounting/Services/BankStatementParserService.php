<?php

namespace App\Domains\ClubAccounting\Services;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Models\BankImport;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Models\Club;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BankStatementParserService
{
    /**
     * Parse a bank statement file (CSV or OFX) and return structured line items with duplicate detection.
     */
    public function parseFile(string $filePath, ?string $originalFilename = null, int $clubId = 0): array
    {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("Bank statement file not found at: {$filePath}");
        }

        $filename = $originalFilename ?: basename($filePath);
        $content = file_get_contents($filePath);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($extension, ['ofx', 'qfx'])) {
            return $this->parseOfx($content, $filename, $clubId);
        }

        return $this->parseCsv($content, $filename, $clubId);
    }

    /**
     * Parse UK CSV Bank Statement content.
     */
    public function parseCsv(string $content, string $filename = 'statement.csv', int $clubId = 0): array
    {
        $lines = explode("\n", str_replace("\r", '', $content));
        $rows = array_filter(array_map('str_getcsv', $lines));

        if (empty($rows)) {
            throw new InvalidArgumentException('Empty or invalid CSV file.');
        }

        // Detect header row
        $headerIndex = -1;
        $headers = [];
        foreach ($rows as $idx => $row) {
            $rowLower = array_map('strtolower', array_map('trim', $row));
            if (array_intersect($rowLower, ['date', 'transaction date', 'posting date', 'description', 'narrative', 'amount', 'paid in', 'paid out', 'debit', 'credit'])) {
                $headerIndex = $idx;
                $headers = $rowLower;
                break;
            }
        }

        if ($headerIndex === -1) {
            // Assume first row is header if not explicitly matched
            $headerIndex = 0;
            $headers = array_map('strtolower', array_map('trim', $rows[0]));
        }

        // Map column indexes
        $colDate = $this->findColumnIndex($headers, ['date', 'transaction date', 'posting date', 'posted date']);
        $colDesc = $this->findColumnIndex($headers, ['description', 'raw_description', 'memo', 'details', 'narrative', 'name', 'payee', 'transaction details']);
        $colRef = $this->findColumnIndex($headers, ['reference', 'ref', 'cheque number', 'type', 'transaction type']);
        $colAmount = $this->findColumnIndex($headers, ['amount', 'transaction amount', 'value']);
        $colPaidIn = $this->findColumnIndex($headers, ['paid in', 'credit', 'credit amount', 'money in', 'in']);
        $colPaidOut = $this->findColumnIndex($headers, ['paid out', 'debit', 'debit amount', 'money out', 'out']);
        $colBalance = $this->findColumnIndex($headers, ['balance', 'account balance', 'running balance']);

        $parsedLines = [];
        $totalAmount = 0.0;
        $duplicateCount = 0;
        $existingHashes = $this->getExistingHashes($clubId);

        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (count($row) < 2) {
                continue;
            }

            $dateStr = $colDate !== null ? trim($row[$colDate] ?? '') : '';
            if (empty($dateStr)) {
                continue;
            }

            $parsedDate = $this->parseDate($dateStr);
            if (! $parsedDate) {
                continue;
            }

            $description = $colDesc !== null ? trim($row[$colDesc] ?? '') : 'Bank Transaction';
            $reference = $colRef !== null ? trim($row[$colRef] ?? '') : null;

            // Calculate Amount
            $amount = 0.0;
            if ($colAmount !== null && isset($row[$colAmount]) && trim($row[$colAmount]) !== '') {
                $amount = $this->cleanAmount($row[$colAmount]);
            } else {
                $in = $colPaidIn !== null ? $this->cleanAmount($row[$colPaidIn] ?? '') : 0.0;
                $out = $colPaidOut !== null ? $this->cleanAmount($row[$colPaidOut] ?? '') : 0.0;
                $amount = $in > 0 ? abs($in) : -abs($out);
            }

            $balance = ($colBalance !== null && isset($row[$colBalance]) && trim($row[$colBalance]) !== '')
                ? $this->cleanAmount($row[$colBalance])
                : null;

            $hash = $this->generateTransactionHash($parsedDate->format('Y-m-d'), $amount, $description, $balance);
            $isDuplicate = in_array($hash, $existingHashes) || isset($parsedLines[$hash]);

            if ($isDuplicate) {
                $duplicateCount++;
            }

            $parsedLines[$hash] = [
                'transaction_date' => $parsedDate,
                'raw_description' => $description,
                'reference' => $reference,
                'amount' => $amount,
                'balance_after' => $balance,
                'transaction_hash' => $hash,
                'is_duplicate' => $isDuplicate,
            ];

            $totalAmount += $amount;
        }

        $linesList = array_values($parsedLines);

        return [
            'filename' => $filename,
            'account_number' => null,
            'sort_code' => null,
            'lines' => $linesList,
            'total_lines' => count($linesList),
            'new_lines_count' => count($linesList) - $duplicateCount,
            'duplicate_lines_count' => $duplicateCount,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Parse Standard OFX / QFX File.
     */
    public function parseOfx(string $content, string $filename = 'statement.ofx', int $clubId = 0): array
    {
        $accountNumber = null;
        $sortCode = null;

        if (preg_match('/<ACCTID>([^<\r\n]+)/i', $content, $matches)) {
            $accountNumber = trim($matches[1]);
        }
        if (preg_match('/<BANKID>([^<\r\n]+)/i', $content, $matches)) {
            $sortCode = trim($matches[1]);
        }

        preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/is', $content, $transactions);
        if (empty($transactions[1])) {
            // Fallback for unclosed SGML tags
            preg_match_all('/<STMTTRN>([^<]+(?:<(?!\/STMTTRN)[^<]+)*)/i', $content, $transactions);
        }

        $parsedLines = [];
        $totalAmount = 0.0;
        $duplicateCount = 0;
        $existingHashes = $this->getExistingHashes($clubId);

        foreach ($transactions[1] ?? [] as $trnXml) {
            $dateStr = $this->extractOfxTag($trnXml, 'DTPOSTED');
            $parsedDate = $this->parseOfxDate($dateStr);
            if (! $parsedDate) {
                continue;
            }

            $amountStr = $this->extractOfxTag($trnXml, 'TRNAMT');
            $amount = floatval($amountStr);

            $name = $this->extractOfxTag($trnXml, 'NAME') ?: $this->extractOfxTag($trnXml, 'MEMO') ?: 'Bank Transaction';
            $memo = $this->extractOfxTag($trnXml, 'MEMO');
            $ref = $this->extractOfxTag($trnXml, 'FITID') ?: $this->extractOfxTag($trnXml, 'CHECKNUM');

            $fullDesc = trim("{$name} ".($memo && $memo !== $name ? $memo : ''));

            $hash = $this->generateTransactionHash($parsedDate->format('Y-m-d'), $amount, $fullDesc, null);
            $isDuplicate = in_array($hash, $existingHashes) || isset($parsedLines[$hash]);

            if ($isDuplicate) {
                $duplicateCount++;
            }

            $parsedLines[$hash] = [
                'transaction_date' => $parsedDate,
                'raw_description' => $fullDesc,
                'reference' => $ref,
                'amount' => $amount,
                'balance_after' => null,
                'transaction_hash' => $hash,
                'is_duplicate' => $isDuplicate,
            ];

            $totalAmount += $amount;
        }

        $linesList = array_values($parsedLines);

        return [
            'filename' => $filename,
            'account_number' => $accountNumber,
            'sort_code' => $sortCode,
            'lines' => $linesList,
            'total_lines' => count($linesList),
            'new_lines_count' => count($linesList) - $duplicateCount,
            'duplicate_lines_count' => $duplicateCount,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Generate unique transaction SHA-256 hash.
     */
    public function generateTransactionHash(string $date, float $amount, string $description, ?float $balance = null): string
    {
        $amountStr = number_format($amount, 2, '.', '');
        $balStr = $balance !== null ? number_format($balance, 2, '.', '') : '';
        $rawString = "{$date}|{$amountStr}|".trim($description)."|{$balStr}";

        return hash('sha256', $rawString);
    }

    /**
     * Persist parsed bundle into database tables (`club_acc_bank_imports` & `club_acc_bank_transactions`).
     */
    public function importParsedBundle(Club|int $club, array $parsedBundle, ?int $importedByMemberId = null): BankImport
    {
        $clubId = $club instanceof Club ? $club->id : $club;

        return DB::transaction(function () use ($clubId, $parsedBundle, $importedByMemberId) {
            $newLines = array_filter($parsedBundle['lines'], fn ($l) => empty($l['is_duplicate']));
            $totalLines = count($newLines);
            $totalAmount = array_reduce($newLines, fn ($acc, $l) => $acc + (float) $l['amount'], 0.0);

            $importBatch = BankImport::create([
                'club_id' => $clubId,
                'filename' => $parsedBundle['filename'] ?? 'statement.csv',
                'account_number' => $parsedBundle['account_number'] ?? null,
                'sort_code' => $parsedBundle['sort_code'] ?? null,
                'imported_by_member_id' => $importedByMemberId,
                'total_lines' => $totalLines,
                'total_amount' => $totalAmount,
            ]);

            foreach ($newLines as $line) {
                BankTransaction::create([
                    'club_id' => $clubId,
                    'bank_import_id' => $importBatch->id,
                    'transaction_date' => $line['transaction_date'],
                    'raw_description' => $line['raw_description'],
                    'reference' => $line['reference'] ?? null,
                    'amount' => $line['amount'],
                    'balance_after' => $line['balance_after'] ?? null,
                    'transaction_hash' => $line['transaction_hash'],
                    'status' => BankTransactionStatus::Unmatched,
                ]);
            }

            return $importBatch->fresh();
        });
    }

    private function getExistingHashes(int $clubId): array
    {
        if ($clubId <= 0) {
            return [];
        }

        return BankTransaction::where('club_id', $clubId)
            ->pluck('transaction_hash')
            ->toArray();
    }

    private function findColumnIndex(array $headers, array $candidates): ?int
    {
        foreach ($candidates as $cand) {
            $idx = array_search($cand, $headers);
            if ($idx !== false) {
                return $idx;
            }
        }

        return null;
    }

    private function cleanAmount(string $val): float
    {
        $clean = preg_replace('/[^\d\.\-]/', '', $val);

        return floatval($clean);
    }

    private function parseDate(string $val): ?Carbon
    {
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'j M Y', 'd M Y', 'Y/m/d'];
        foreach ($formats as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, trim($val))->startOfDay();
            } catch (\Exception $e) {
                // Continue to next format
            }
        }

        return null;
    }

    private function parseOfxDate(string $val): ?Carbon
    {
        if (strlen($val) >= 8) {
            $year = substr($val, 0, 4);
            $month = substr($val, 4, 2);
            $day = substr($val, 6, 2);

            return Carbon::create((int) $year, (int) $month, (int) $day)->startOfDay();
        }

        return null;
    }

    private function extractOfxTag(string $xml, string $tag): string
    {
        if (preg_match("/<{$tag}>([^<\r\n]+)/i", $xml, $m)) {
            return trim($m[1]);
        }

        return '';
    }
}
