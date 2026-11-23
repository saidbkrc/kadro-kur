<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-1.5 min-h-[44px] px-4 py-2 bg-pitch-surface2 border border-pitch-line rounded-xl font-display font-semibold text-base text-pitch-ink uppercase tracking-wider hover:border-pitch-muted/60 active:scale-[.98] focus:outline-none focus:ring-2 focus:ring-gold disabled:opacity-40 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
