<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-1.5 min-h-[44px] px-4 py-2 bg-transparent border border-[#6c3030] rounded-xl font-display font-semibold text-base text-[#ffb3b3] uppercase tracking-wider hover:bg-red-900/30 active:scale-[.98] focus:outline-none focus:ring-2 focus:ring-red-500 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
