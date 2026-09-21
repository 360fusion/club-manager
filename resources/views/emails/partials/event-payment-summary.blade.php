@if ((float) $payment['total'] > 0)
  <p style="margin-bottom: 4px;"><strong>Payment</strong></p>
  <p style="margin-top: 0;">
    Total: {{ $symbol }}{{ number_format((float) $payment['total'], 2) }}
    @if ((float) $payment['booking_fee'] > 0) (includes a {{ $symbol }}{{ number_format((float) $payment['booking_fee'], 2) }} {{ strtolower($payment['booking_fee_label']) }})@endif
    @if ((float) $payment['amount_paid'] > 0)<br>Paid so far: {{ $symbol }}{{ number_format((float) $payment['amount_paid'], 2) }}@endif
    @if ($payment['method_label'])<br>Paying by: {{ $payment['method_label'] }}@if ($payment['due_at']), due by {{ $payment['due_at'] }}@endif @endif
  </p>
  @include('emails.partials.event-pay-how', ['payment' => $payment, 'showBank' => $registration->status !== 'waitlisted'])
@endif
