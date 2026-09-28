<table>
    <thead>
        <tr><th>Code</th><th>Name</th><th>Type</th><th class="num">Total Debit</th><th class="num">Total Credit</th><th class="num">Net Balance</th></tr>
    </thead>
    <tbody>
        @forelse($report as $row)
            <tr>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ ucfirst($row['type']) }}</td>
                <td class="num">{{ $cs }}{{ number_format($row['total_debit'], 2) }}</td>
                <td class="num">{{ $cs }}{{ number_format($row['total_credit'], 2) }}</td>
                <td class="num">{{ $cs }}{{ number_format($row['net_balance'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No accounts recorded.</td></tr>
        @endforelse
    </tbody>
</table>
