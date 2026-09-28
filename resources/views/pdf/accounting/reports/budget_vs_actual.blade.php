<div class="muted">Financial year {{ $report['financial_year'] }}</div>
<table>
    <thead>
        <tr><th>Code</th><th>Name</th><th>Type</th><th class="num">Budgeted</th><th class="num">Actual</th><th class="num">Variance</th></tr>
    </thead>
    <tbody>
        @forelse($report['rows'] as $row)
            <tr>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ ucfirst($row['type']) }}</td>
                <td class="num">{{ $cs }}{{ number_format($row['budgeted'], 2) }}</td>
                <td class="num">{{ $cs }}{{ number_format($row['actual'], 2) }}</td>
                <td class="num">{{ $cs }}{{ number_format($row['variance'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No budget set for this year.</td></tr>
        @endforelse
        <tr class="totals-row">
            <td colspan="3">Total</td>
            <td class="num">{{ $cs }}{{ number_format($report['total_budgeted'], 2) }}</td>
            <td class="num">{{ $cs }}{{ number_format($report['total_actual'], 2) }}</td>
            <td class="num">{{ $cs }}{{ number_format($report['total_actual'] - $report['total_budgeted'], 2) }}</td>
        </tr>
    </tbody>
</table>
