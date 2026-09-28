<table>
    <thead>
        <tr><th>Reference</th><th>Name</th><th class="num">Days Overdue</th><th>Bucket</th><th class="num">Amount</th><th>Status</th></tr>
    </thead>
    <tbody>
        @forelse($report['items'] as $row)
            <tr>
                <td>{{ $row['bill_number'] ?? $row['invoice_number'] }}</td>
                <td>{{ $row['vendor_name'] ?? $row['recipient_name'] }}</td>
                <td class="num">{{ $row['days_overdue'] }}</td>
                <td>{{ $row['bucket'] }}</td>
                <td class="num">{{ $cs }}{{ number_format($row['amount'], 2) }}</td>
                <td>{{ $row['status'] === 'pending_approval' ? 'Pending Approval' : 'Unpaid' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">Nothing outstanding.</td></tr>
        @endforelse
        <tr class="totals-row">
            <td colspan="4">Total Outstanding</td>
            <td class="num">{{ $cs }}{{ number_format($report['total'], 2) }}</td>
            <td></td>
        </tr>
    </tbody>
</table>
