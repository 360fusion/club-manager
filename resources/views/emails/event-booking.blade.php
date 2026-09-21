<!DOCTYPE html>
<html lang="en">
<body style="font-family: Helvetica, Arial, sans-serif; color: #0f172a; line-height: 1.5; max-width: 560px; margin: 0 auto; padding: 24px;">
  <h2 style="margin: 0 0 4px;">{{ $registration->status === 'waitlisted' ? 'You are on the waiting list' : 'Thank you, you are booked in' }}</h2>
  <p style="margin: 0 0 16px; color: #475569;">{{ $club->name }}</p>

  <p><strong>{{ $event->title }}</strong><br>
    {{ $event->starts_at?->format('l j F Y, H:i') }}
    @if ($event->formatted_location)<br>{{ $event->formatted_location }}@endif
  </p>

  @if ($registration->status === 'waitlisted')
    <p>The event is currently full. We will email you if a place becomes available.</p>
  @endif

  <p style="margin-bottom: 4px;"><strong>Booked for</strong></p>
  <ul style="padding-left: 18px; margin-top: 0;">
    @foreach ($registration->attendees as $person)
      @php($meal = array_filter($person->mealSummary()))
      <li>{{ $person->name }}@if ($meal) &mdash; {{ implode(', ', $meal) }}@endif @if ($person->dietary_requirements)<br><small style="color:#64748b">Dietary: {{ $person->dietary_requirements }}</small>@endif</li>
    @endforeach
  </ul>

  @if ((float) $payment['total'] > 0)
    <p style="margin-bottom: 4px;"><strong>Payment</strong></p>
    <p style="margin-top: 0;">
      Total: {{ $symbol }}{{ number_format((float) $payment['total'], 2) }}
      @if ((float) $payment['booking_fee'] > 0) (includes a {{ $symbol }}{{ number_format((float) $payment['booking_fee'], 2) }} {{ strtolower($payment['booking_fee_label']) }})@endif
      @if ((float) $payment['amount_paid'] > 0)<br>Paid so far: {{ $symbol }}{{ number_format((float) $payment['amount_paid'], 2) }}@endif
      @if ($payment['method_label'])<br>Paying by: {{ $payment['method_label'] }}@if ($payment['due_at']), due by {{ $payment['due_at'] }}@endif @endif
    </p>
    @if ($payment['instructions'])<p>{{ $payment['instructions'] }}</p>@endif
    @if ($payment['bank'] && $registration->status !== 'waitlisted')
      <p style="background:#f1f5f9;padding:10px;border-radius:6px;">
        <strong>Bank transfer</strong><br>
        @if ($payment['bank']['account_name'])Account name: {{ $payment['bank']['account_name'] }}<br>@endif
        @if ($payment['bank']['sort_code'])Sort code: {{ $payment['bank']['sort_code'] }}<br>@endif
        @if ($payment['bank']['account_number'])Account number: {{ $payment['bank']['account_number'] }}<br>@endif
        <strong>Reference: {{ $payment['reference'] }}</strong><br>
        <small>Please use the reference exactly so we can match your payment.</small>
      </p>
    @endif
  @endif

  <p>You can view or cancel your booking at any time with this private link:<br>
    <a href="{{ $manageUrl }}">{{ $manageUrl }}</a></p>

  @if ($event->cancellation_policy)
    <p style="color:#475569"><small>{{ $event->cancellation_policy }}</small></p>
  @endif
</body>
</html>
