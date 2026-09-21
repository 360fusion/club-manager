<ul style="padding-left: 18px; margin-top: 0;">
  @foreach ($registration->attendees as $person)
    @php($meal = array_filter($person->mealSummary()))
    <li>{{ $person->name }}@if ($meal) &mdash; {{ implode(', ', $meal) }}@endif @if ($person->dietary_requirements)<br><small style="color:#64748b">Dietary: {{ $person->dietary_requirements }}</small>@endif</li>
  @endforeach
</ul>
