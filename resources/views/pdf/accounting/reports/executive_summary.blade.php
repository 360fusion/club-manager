<table>
    <tbody>
        <tr><td>Net Profit Margin</td><td class="num">{{ number_format($report['net_profit_margin_pct'], 1) }}%</td></tr>
        <tr><td>Operating Expense Ratio</td><td class="num">{{ number_format($report['operating_expense_ratio_pct'], 1) }}%</td></tr>
        <tr><td>Total Cash Reserves</td><td class="num">{{ $cs }}{{ number_format($report['total_cash_reserves'], 2) }}</td></tr>
        <tr><td>Outstanding Receivables</td><td class="num">{{ $cs }}{{ number_format($report['outstanding_ar'], 2) }}</td></tr>
        <tr><td>Outstanding Payables</td><td class="num">{{ $cs }}{{ number_format($report['outstanding_ap'], 2) }}</td></tr>
    </tbody>
</table>
