@extends('layouts.nba')

@section('title', 'What-If Scenarios')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-12">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
            <h1 class="font-display italic font-black text-4xl md:text-5xl uppercase tracking-tight">What-If Scenarios</h1>
            <p class="text-data-slate mt-2">Alternate-reality NBA stats and comparisons</p>
        </div>
        <div class="flex gap-2">
            @auth
                <a href="{{ route('scenarios.create') }}" class="bg-hoop-orange hover:bg-hoop-orange/90 text-white font-bold px-4 py-2 rounded-lg text-xs transition-all">+ New Scenario</a>
                <a href="{{ route('scenarios.index', ['mine' => 1]) }}" class="border border-white/10 text-data-slate hover:text-white px-4 py-2 rounded-lg text-xs font-bold transition-colors {{ request('mine') ? 'bg-white/10' : '' }}">My Scenarios</a>
            @endauth
            <form method="GET" class="flex gap-2">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search..." class="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm placeholder:text-data-slate/40 outline-none focus:border-hoop-orange/50 transition-colors w-36">
                <button type="submit" class="bg-hoop-orange hover:bg-hoop-orange/90 text-white font-bold px-3 rounded-lg text-xs transition-all">Search</button>
                @if(request('q'))
                    <a href="{{ route('scenarios.index') }}" class="border border-white/10 text-data-slate hover:text-white px-3 rounded-lg text-xs font-bold transition-colors flex items-center">Clear</a>
                @endif
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($scenarios as $scenario)
            <a href="{{ route('scenarios.show', $scenario) }}" class="bg-court-dark border border-white/5 rounded-xl p-5 hover:border-hoop-orange/30 transition-all group">
                <div class="flex items-start justify-between">
                    <h3 class="font-display text-lg font-bold uppercase tracking-tight group-hover:text-hoop-orange transition-colors">{{ $scenario->name }}</h3>
                    @if($scenario->is_public)
                        <span class="px-2 py-1 bg-green-500/10 border border-green-500/20 rounded text-[10px] text-green-400 font-medium">Public</span>
                    @else
                        <span class="px-2 py-1 bg-white/5 border border-white/10 rounded text-[10px] text-data-slate font-medium">Private</span>
                    @endif
                </div>
                @if($scenario->description)
                    <p class="mt-2 text-sm text-data-slate line-clamp-2">{{ $scenario->description }}</p>
                @endif
                <div class="mt-4 flex items-center justify-between text-xs text-data-slate">
                    <span>by {{ $scenario->user?->name ?? 'Anonymous' }}</span>
                    <span>{{ $scenario->created_at->diffForHumans() }}</span>
                </div>
            </a>
        @empty
            <div class="col-span-full text-center py-12 text-data-slate">
                @auth
                    No scenarios yet. <a href="{{ route('scenarios.create') }}" class="text-hoop-orange hover:underline">Create one</a>.
                @else
                    No public scenarios available. <a href="{{ route('login') }}" class="text-hoop-orange hover:underline">Login</a> to create your own.
                @endauth
            </div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $scenarios->appends(request()->query())->links() }}
    </div>
</div>
@endsection
