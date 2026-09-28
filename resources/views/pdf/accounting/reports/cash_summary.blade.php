<table>
    <thead>
        <tr><th>Code</th><th>Name</th><th class="num">Balance</th></tr>
    </thead>
    <tbody>
        @forelse($report['accounts'] as $row)
            <tr><td>{{ $row['code'] }}</td><td>{{ $row['name'] }}</td><td class="num">{{ $cs }}{{ number_format($row['balance'], 2) }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">No cash accounts.</td></tr>
        @endforelse
        <tr class="totals-row"><td colspan="2">Total Cash on Hand</td><td class="num">{{ $cs }}{{ number_format($report['total_cash_on_hand'], 2) }}</td></tr>
    </tbody>
</table>
