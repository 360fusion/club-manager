@if ($payment['instructions'])<p>{{ $payment['instructions'] }}</p>@endif
@if ($showBank && $payment['bank'])
  <p style="background:#f1f5f9;padding:10px;border-radius:6px;">
    <strong>Bank transfer</strong><br>
    @if ($payment['bank']['account_name'])Account name: {{ $payment['bank']['account_name'] }}<br>@endif
    @if ($payment['bank']['bank_name'])Bank: {{ $payment['bank']['bank_name'] }}<br>@endif
    @if ($payment['bank']['sort_code']){{ $payment['bank']['code_label'] }}: {{ $payment['bank']['sort_code'] }}<br>@endif
    @if ($payment['bank']['account_number'])Account number: {{ $payment['bank']['account_number'] }}<br>@endif
    @if ($payment['bank']['iban'])IBAN: {{ $payment['bank']['iban'] }}<br>@endif
    <strong>Reference: {{ $payment['reference'] }}</strong><br>
    <small>Please use the reference exactly so we can match your payment.</small>
  </p>
@elseif ($payment['method_type'] === 'cash_on_door')
  <p>You can pay when you arrive.</p>
@endif
