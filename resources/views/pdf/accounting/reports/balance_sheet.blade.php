<div class="section-title">Assets</div>
<table>
    <tbody>
        @forelse($report['assets'] as $row)
            <tr><td>{{ $row['code'] }}</td><td>{{ $row['name'] }}</td><td class="num">{{ $cs }}{{ number_format($row['balance'], 2) }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">No asset accounts.</td></tr>
        @endforelse
        <tr class="totals-row"><td colspan="2">Total Assets</td><td class="num">{{ $cs }}{{ number_format($report['total_assets'], 2) }}</td></tr>
    </tbody>
</table>

<div class="section-title">Liabilities</div>
<table>
    <tbody>
        @forelse($report['liabilities'] as $row)
            <tr><td>{{ $row['code'] }}</td><td>{{ $row['name'] }}</td><td class="num">{{ $cs }}{{ number_format($row['balance'], 2) }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">No liability accounts.</td></tr>
        @endforelse
        <tr class="totals-row"><td colspan="2">Total Liabilities</td><td class="num">{{ $cs }}{{ number_format($report['total_liabilities'], 2) }}</td></tr>
    </tbody>
</table>

<div class="section-title">Equity</div>
<table>
    <tbody>
        @forelse($report['equity'] as $row)
            <tr><td>{{ $row['code'] }}</td><td>{{ $row['name'] }}</td><td class="num">{{ $cs }}{{ number_format($row['balance'], 2) }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">No equity accounts.</td></tr>
        @endforelse
        <tr class="totals-row"><td colspan="2">Total Equity</td><td class="num">{{ $cs }}{{ number_format($report['total_equity'], 2) }}</td></tr>
    </tbody>
</table>
