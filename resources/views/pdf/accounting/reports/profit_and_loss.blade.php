<div class="section-title">Revenue</div>
<table>
    <tbody>
        @forelse($report['revenues'] as $row)
            <tr><td>{{ $row['code'] }}</td><td>{{ $row['name'] }}</td><td class="num">{{ $cs }}{{ number_format($row['amount'], 2) }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">No revenue accounts.</td></tr>
        @endforelse
        <tr class="totals-row"><td colspan="2">Total Revenue</td><td class="num">{{ $cs }}{{ number_format($report['total_revenue'], 2) }}</td></tr>
    </tbody>
</table>

<div class="section-title">Expenses</div>
<table>
    <tbody>
        @forelse($report['expenses'] as $row)
            <tr><td>{{ $row['code'] }}</td><td>{{ $row['name'] }}</td><td class="num">{{ $cs }}{{ number_format($row['amount'], 2) }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">No expense accounts.</td></tr>
        @endforelse
        <tr class="totals-row"><td colspan="2">Total Expenses</td><td class="num">{{ $cs }}{{ number_format($report['total_expenses'], 2) }}</td></tr>
    </tbody>
</table>

<div class="section-title">Net Income</div>
<table>
    <tbody>
        <tr class="totals-row"><td>Net Income</td><td class="num">{{ $cs }}{{ number_format($report['net_income'], 2) }}</td></tr>
    </tbody>
</table>
