<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-white/5 border border-white/10 rounded-lg font-bold text-xs text-gray-300 uppercase tracking-widest shadow-sm hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-hoop-orange/50 focus:ring-offset-2 focus:ring-offset-court-dark disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
