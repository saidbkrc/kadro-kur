<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-gradient-to-b from-[#2C7A48] to-[#1F5A35] border border-[#3E9A60] rounded-md font-semibold text-xs text-pitch-ink uppercase tracking-widest hover:brightness-125 focus:outline-none focus:ring-2 focus:ring-gold disabled:opacity-40 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
