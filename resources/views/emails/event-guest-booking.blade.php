<!DOCTYPE html>
<html lang="en">
<body style="font-family: Helvetica, Arial, sans-serif; color: #0f172a; line-height: 1.5; max-width: 560px; margin: 0 auto; padding: 24px;">
  <h2 style="margin: 0 0 4px;">{{ $waiting ? 'You are on the waiting list' : 'You are booked in' }}</h2>
  <p style="margin: 0 0 16px; color: #475569;">{{ $club->name }}</p>

  <p>Hello {{ $guest->name }},</p>
  <p>{{ $bookedBy }} has {{ $waiting ? 'asked for' : 'booked' }} a place for you at:</p>

  <p><strong>{{ $event->title }}</strong><br>
    {{ $event->starts_at?->format('l j F Y, H:i') }}
    @if ($event->formatted_location)<br>{{ $event->formatted_location }}@endif
  </p>

  @if ($waiting)
    <p>The event is currently full, so you are on the waiting list. We will email {{ $bookedBy }} if a place becomes available.</p>
  @endif

  @if ($meal)
    <p style="margin-bottom: 4px;"><strong>Your meal</strong></p>
    <p style="margin-top: 0;">{{ implode(' · ', $meal) }}</p>
  @endif
  @if ($guest->dietary_requirements)
    <p><strong>Dietary needs noted:</strong> {{ $guest->dietary_requirements }}</p>
  @endif

  <p>If anything needs to change, please speak to {{ $bookedBy }}, who made the booking, or contact {{ $club->name }}.</p>

  @if ($event->cancellation_policy)
    <p style="color:#475569"><small>{{ $event->cancellation_policy }}</small></p>
  @endif

  <p style="color:#64748b"><small>You are receiving this once because {{ $bookedBy }} gave us your email address. If it wasn't expected you can ignore it, and we won't email you again about this booking.</small></p>
</body>
</html>
