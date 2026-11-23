<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-1.5 min-h-[44px] px-5 py-2 bg-bibB border border-bibB rounded-xl font-display font-bold text-base text-pitch-bg uppercase tracking-wider hover:brightness-110 active:scale-[.98] focus:outline-none focus:ring-2 focus:ring-gold focus:ring-offset-2 focus:ring-offset-pitch-bg disabled:opacity-40 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
