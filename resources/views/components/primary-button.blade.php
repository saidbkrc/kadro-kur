<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2 bg-gradient-to-b from-pitch-green2 to-pitch-green border border-pitch-green2 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:brightness-125 focus:outline-none focus:ring-2 focus:ring-gold disabled:opacity-40 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
