@props([
    'name',
    'enum',
    'label' => null,
    'selected' => null,
    'placeholder' => 'Seçiniz',
    'required' => false,
    'hint' => null,
    'selectClass' => 'form-select',
])

@php
    /** @var class-string<\BackedEnum> $enum */
    $current = old($name, $selected instanceof \BackedEnum ? $selected->value : $selected);
@endphp

<div class="mb-3">
    @if($label)
        <label class="form-label" for="{{ $name }}">
            {{ $label }}
            @if($required)<span class="text-danger">*</span>@endif
        </label>
    @endif

    <select name="{{ $name }}"
            id="{{ $name }}"
            {{ $attributes->merge(['class' => $selectClass . ($errors->has($name) ? ' is-invalid' : '')]) }}
            @required($required)>
        @if($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach($enum::cases() as $case)
            <option value="{{ $case->value }}" @selected((string) $current === (string) $case->value)>
                {{ method_exists($case, 'label') ? $case->label() : $case->name }}
            </option>
        @endforeach
    </select>

    @if($hint)
        <small class="form-text text-muted">{{ $hint }}</small>
    @endif

    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
