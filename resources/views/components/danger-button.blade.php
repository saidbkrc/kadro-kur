<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-transparent border border-[#6c3030] rounded-md font-semibold text-xs text-[#ffb3b3] uppercase tracking-widest hover:bg-red-900/30 focus:outline-none focus:ring-2 focus:ring-red-500 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
