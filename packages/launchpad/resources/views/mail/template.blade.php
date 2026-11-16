{{-- An edited email (Launchpad\Mail\Renderer): the body is HTML made from the template's Markdown, or, for the plain-text part, that Markdown itself --}}
@php($plain = str_ends_with((string) (app('view')->getFinder()->getHints()['mail'][0] ?? ''), '/text'))
<x-mail::message>
@if ($plain)
{{ $text }}
@else
{!! $before !!}

@isset($action)
<x-mail::button :url="$action['url']">
{{ $action['label'] }}
</x-mail::button>
@endisset

{!! $after !!}
@endif

@lang('Regards,')<br>
{{ $site }}

@isset($action)
<x-slot:subcopy>
{{ __(
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".
    'into your web browser:',
    ['actionText' => $action['label']]
) }} <span class="break-all">[{{ $action['url'] }}]({{ $action['url'] }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
