<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-pitch-surface2 border border-pitch-line rounded-md font-semibold text-xs text-pitch-ink uppercase tracking-widest hover:brightness-125 focus:outline-none focus:ring-2 focus:ring-gold disabled:opacity-40 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
