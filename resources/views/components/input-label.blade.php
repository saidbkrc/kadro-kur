@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-semibold text-xs uppercase tracking-widest text-pitch-muted']) }}>
    {{ $value ?? $slot }}
</label>
