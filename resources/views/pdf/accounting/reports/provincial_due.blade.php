@if(! $report['has_province'])
    <p class="muted">This lodge is not linked to a Province, so no rates apply.</p>
@else
    <div class="muted">Province: {{ $report['province_name'] }}</div>
    <table>
        <thead>
            <tr><th>Item</th><th class="num">Members</th><th class="num">Rate</th><th class="num">Amount</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>Per-capita dues</td>
                <td class="num">{{ $report['member_count'] }}</td>
                <td class="num">{{ $report['per_capita_rate'] !== null ? $cs.number_format($report['per_capita_rate'], 2) : 'Rate not set' }}</td>
                <td class="num">{{ $report['per_capita_amount'] !== null ? $cs.number_format($report['per_capita_amount'], 2) : '—' }}</td>
            </tr>
            <tr>
                <td>Festival contribution</td>
                <td class="num">{{ $report['member_count'] }}</td>
                <td class="num">{{ $report['festival_rate'] !== null ? $cs.number_format($report['festival_rate'], 2) : 'Rate not set' }}</td>
                <td class="num">{{ $report['festival_amount'] !== null ? $cs.number_format($report['festival_amount'], 2) : '—' }}</td>
            </tr>
            <tr class="totals-row">
                <td colspan="3">Total due to Province</td>
                <td class="num">{{ $report['total_due'] !== null ? $cs.number_format($report['total_due'], 2) : '—' }}</td>
            </tr>
        </tbody>
    </table>
    @if($report['per_capita_rate'] === null || $report['festival_rate'] === null)
        <p class="muted">Where a rate shows "Rate not set", your Province has not published one here: contact your Province for the figure.</p>
    @endif
@endif
<p class="muted">Counted on {{ $report['counted_on'] }} from current active members. Lodges keep no leave date for members, so this is today's count, not a snapshot at a past date.</p>
