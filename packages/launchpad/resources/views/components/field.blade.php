@props(['name', 'label', 'type' => 'text', 'autocomplete' => null, 'value' => null, 'required' => true])
<div class="lp-field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}"
           @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
           @if ($required) required @endif
           @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
           {{ $attributes }}>
    @error($name)<p class="lp-error" id="{{ $name }}-error">{{ $message }}</p>@enderror
</div>
