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

  <p>You can view or cancel your booking at any time with this private link:<br>
    <a href="{{ $manageUrl }}">{{ $manageUrl }}</a></p>

  @if ($event->cancellation_policy)
    <p style="color:#475569"><small>{{ $event->cancellation_policy }}</small></p>
  @endif
</body>
</html>
