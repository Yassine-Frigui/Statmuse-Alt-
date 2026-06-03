@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-white/5 border-white/10 text-white placeholder:text-data-slate/40 focus:border-hoop-orange/50 focus:ring-hoop-orange/30 rounded-lg shadow-sm']) }}>
