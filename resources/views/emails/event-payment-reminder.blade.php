<!DOCTYPE html>
<html lang="en">
<body style="font-family: Helvetica, Arial, sans-serif; color: #0f172a; line-height: 1.5; max-width: 560px; margin: 0 auto; padding: 24px;">
  <h2 style="margin: 0 0 4px;">A reminder to pay for {{ $event->title }}</h2>
  <p style="margin: 0 0 16px; color: #475569;">{{ $club->name }}</p>

  <p>Hello {{ $registration->contact_name }},</p>
  <p>
    You still have <strong>{{ $symbol }}{{ number_format((float) $payment['balance'], 2) }}</strong> to pay for
    <strong>{{ $event->title }}</strong> on {{ $event->starts_at?->format('l j F Y') }}@if ($payment['due_at']), due by <strong>{{ $payment['due_at'] }}</strong>@endif.
  </p>

  @if ($payment['instructions'])<p>{{ $payment['instructions'] }}</p>@endif

  @if ($payment['bank'])
    <p style="background:#f1f5f9;padding:10px;border-radius:6px;">
      <strong>Bank transfer</strong><br>
      @if ($payment['bank']['account_name'])Account name: {{ $payment['bank']['account_name'] }}<br>@endif
      @if ($payment['bank']['sort_code'])Sort code: {{ $payment['bank']['sort_code'] }}<br>@endif
      @if ($payment['bank']['account_number'])Account number: {{ $payment['bank']['account_number'] }}<br>@endif
      <strong>Reference: {{ $payment['reference'] }}</strong>
    </p>
  @elseif ($payment['method_type'] === 'cash_on_door')
    <p>You can pay when you arrive.</p>
  @endif

  <p style="color:#64748b"><small>If you have already paid, thank you, and please ignore this reminder; it can take a few days for a payment to be checked.</small></p>
</body>
</html>
