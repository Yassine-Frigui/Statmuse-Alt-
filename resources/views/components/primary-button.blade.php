<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-hoop-orange border border-transparent rounded-lg font-bold text-xs text-white uppercase tracking-widest hover:bg-hoop-orange/90 focus:outline-none focus:ring-2 focus:ring-hoop-orange/50 focus:ring-offset-2 focus:ring-offset-court-dark transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
