@props(['subject'])
{{-- A "Report" link that opens a small form, for content listed in config launchpad.moderation.types; nothing for a guest or while moderation is off --}}
@php($type = \Launchpad\Moderation\Reports::typeOf($subject))
@if ($type && auth()->check() && Route::has('reports.store'))
@php($id = 'lp-report-'.$type.'-'.$subject->getKey())
<details class="lp-report">
    <summary>{{ __('Report') }}</summary>
    <form method="post" action="{{ route('reports.store') }}">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="hidden" name="id" value="{{ $subject->getKey() }}">
        <div class="lp-field">
            <label for="{{ $id }}-reason">{{ __('What is wrong with it?') }}</label>
            <select id="{{ $id }}-reason" name="reason">
                @foreach (\Launchpad\Moderation\Reports::reasons() as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="lp-field">
            <label for="{{ $id }}-details">{{ __('Anything else we should know (optional)') }}</label>
            <textarea id="{{ $id }}-details" name="details" maxlength="2000" rows="3"></textarea>
        </div>
        <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Send report') }}</button>
    </form>
</details>
@endif
