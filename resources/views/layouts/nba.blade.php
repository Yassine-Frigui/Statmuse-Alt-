<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') | @endif Stat Engine</title>
    <meta name="description" content="Natural-language sports query engine.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Barlow+Condensed:ital,wght@0,700;0,900;1,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="font-sans antialiased bg-court-black text-white">
    {{-- SiteHeader --}}
    <nav class="border-b border-white/5 bg-court-dark/50 backdrop-blur-md sticky top-0 z-50" x-data="sportNav()">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="/chatbot" class="flex items-center gap-2">
                    <div class="size-8 bg-hoop-orange rounded flex items-center justify-center font-display font-black text-xl italic text-white shadow-[0_0_20px_rgba(255,93,34,0.3)]">
                        E
                    </div>
                    <span class="font-display text-xl font-bold tracking-tight uppercase italic">
                        Stat Engine
                    </span>
                </a>
                <div class="hidden md:flex gap-6 text-sm font-medium">
                    <a href="{{ route('chatbot.index') }}" class="hover:text-white transition-colors {{ request()->routeIs('chatbot.index') ? 'text-white' : 'text-data-slate' }}">Query</a>
                    <a href="{{ route('scenarios.index') }}" class="hover:text-white transition-colors {{ request()->routeIs('scenarios.*') ? 'text-white' : 'text-data-slate' }}">Scenarios</a>
                    <a href="{{ route('compare.index') }}" class="hover:text-white transition-colors {{ request()->routeIs('compare.*') ? 'text-white' : 'text-data-slate' }}">Compare</a>
                </div>
            </div>
            <div class="flex items-center gap-3">
                {{-- Sport Toggler --}}
                <div class="flex bg-white/5 border border-white/10 rounded-lg overflow-hidden text-[11px] font-bold">
                    <button @click="setSport('nba')" class="px-3 py-1.5 transition-colors" :class="sport === 'nba' ? 'bg-hoop-orange text-white' : 'text-data-slate hover:text-white'">NBA</button>
                    <button @click="setSport('champions')" class="px-3 py-1.5 transition-colors" :class="sport === 'champions' ? 'bg-hoop-orange text-white' : 'text-data-slate hover:text-white'">UCL</button>
                </div>
                @auth
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="size-8 rounded-full bg-white/10 border border-white/20 grid place-items-center text-xs font-bold hover:bg-white/20 transition-colors">
                            {{ substr(Auth::user()->name, 0, 2) }}
                        </button>
                        <div x-show="open" @click.outside="open = false" class="absolute right-0 mt-2 w-48 bg-court-dark border border-white/10 rounded-xl shadow-2xl overflow-hidden z-50">
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-3 text-sm text-data-slate hover:text-white hover:bg-white/5 transition-colors">Profile</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-3 text-sm text-data-slate hover:text-white hover:bg-white/5 transition-colors">Log Out</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="text-xs font-bold uppercase tracking-widest text-data-slate hover:text-white transition-colors">Login</a>
                    <a href="{{ route('register') }}" class="bg-hoop-orange hover:bg-hoop-orange/90 text-white font-bold py-2 px-4 rounded-lg text-xs transition-all">Register</a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- Page content --}}
    @yield('content')

    {{-- Persistent chatbot FAB --}}
    <div x-data="chatbotFab()" x-cloak class="fixed bottom-6 right-6 z-50">
        <template x-if="!open">
            <button @click="open = true" class="size-14 bg-hoop-orange rounded-full shadow-[0_0_30px_rgba(255,93,34,0.4)] flex items-center justify-center hover:bg-hoop-orange/90 transition-all transform hover:scale-105">
                <svg class="size-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
            </button>
        </template>
        <template x-if="open">
            <div class="bg-court-dark border border-white/10 rounded-2xl shadow-2xl w-96 max-w-[calc(100vw-2rem)] overflow-hidden" @click.outside="open = false">
                <div class="flex items-center justify-between px-4 py-3 border-b border-white/5">
                    <span class="font-display text-sm font-bold uppercase tracking-wide"><span class="text-hoop-orange">Ask</span> AI</span>
                    <button @click="open = false" class="text-data-slate hover:text-white transition-colors">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-4">
                    <form @submit.prevent="ask($refs.fabInput.value); $refs.fabInput.value = ''" class="flex gap-2">
                        <input x-ref="fabInput" type="text" maxlength="500" :disabled="loading" class="flex-1 bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm placeholder:text-data-slate/40 outline-none focus:border-hoop-orange/50 transition-colors" placeholder="Quick question...">
                        <button type="submit" :disabled="loading" class="bg-hoop-orange hover:bg-hoop-orange/90 disabled:opacity-50 text-white font-bold px-4 rounded-lg text-xs transition-all">Go</button>
                    </form>
                    <template x-if="fabResult">
                        <div class="mt-3 text-sm text-data-slate" x-text="fabResult"></div>
                    </template>
                    <template x-if="fabError">
                        <div class="mt-3 text-sm text-red-400" x-text="fabError"></div>
                    </template>
                    <a href="/chatbot" class="mt-3 block text-[10px] text-hoop-orange hover:underline text-center font-bold uppercase tracking-widest">Open full query engine →</a>
                </div>
            </div>
        </template>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('sport', {
                current: localStorage.getItem('sport') || 'nba',
                set(val) { this.current = val; localStorage.setItem('sport', val); }
            });

            Alpine.data('sportNav', () => ({
                get sport() { return Alpine.store('sport').current; },
                setSport(val) { Alpine.store('sport').set(val); }
            }));

            Alpine.data('chatbotFab', () => ({
                open: false,
                loading: false,
                fabResult: null,
                fabError: null,
                async ask(q) {
                    const trimmed = (q || '').trim();
                    if (!trimmed || this.loading) return;
                    this.loading = true;
                    this.fabResult = null;
                    this.fabError = null;
                    try {
                        const sport = Alpine.store('sport').current;
                        const res = await fetch('/api/chatbot', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ message: trimmed, sport })
                        });
                        if (!res.ok) throw new Error('API error ' + res.status);
                        const data = await res.json();
                        this.fabResult = data.reply;
                    } catch (err) {
                        this.fabError = err.message;
                    } finally {
                        this.loading = false;
                    }
                }
            }));
        });
    </script>
</body>
</html>
