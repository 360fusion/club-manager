@props(['action' => 'help.search', 'query' => '', 'label' => 'Search the help centre'])
<form class="lp-search" role="search" method="get" action="{{ route($action) }}">
    <label class="lp-skip" for="q">{{ __($label) }}</label>
    <input id="q" name="q" type="search" value="{{ $query }}" placeholder="{{ __($label) }}" autocomplete="off">
    <button class="lp-btn" type="submit">{{ __('Search') }}</button>
</form>
