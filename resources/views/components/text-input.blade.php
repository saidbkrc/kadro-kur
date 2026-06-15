@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-pitch-bg border-pitch-line text-pitch-ink placeholder-pitch-muted/60 focus:border-bibB focus:ring-bibB/40 rounded-md shadow-sm']) }}>
