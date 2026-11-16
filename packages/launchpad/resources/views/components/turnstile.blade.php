@props(['key' => null])
@if ($key)
    <div class="lp-field"><div class="cf-turnstile" data-sitekey="{{ $key }}" data-response-field-name="cf_turnstile_response"></div></div>
    @error('turnstile')<p class="lp-error">{{ $message }}</p>@enderror
    @push('scripts')<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endpush
@endif
