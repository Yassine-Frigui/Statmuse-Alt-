@extends('layouts.nba')

@section('title', 'Teams')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-12">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
            <h1 class="font-display italic font-black text-4xl md:text-5xl uppercase tracking-tight">Teams</h1>
            <p class="text-data-slate mt-2">{{ $teams->count() }} NBA teams</p>
        </div>
        <form method="GET" class="flex gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name/city..." class="bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-sm placeholder:text-data-slate/40 outline-none focus:border-hoop-orange/50 transition-colors w-48">
            <button type="submit" class="bg-hoop-orange hover:bg-hoop-orange/90 text-white font-bold px-4 rounded-lg text-xs transition-all">Search</button>
            @if(request()->anyFilled(['q', 'conference']))
                <a href="{{ route('teams.index') }}" class="border border-white/10 text-data-slate hover:text-white px-4 rounded-lg text-xs font-bold transition-colors flex items-center">Clear</a>
            @endif
        </form>
    </div>

    <form method="GET" class="flex flex-wrap gap-3 mb-8">
        <select name="conference" onchange="this.form.submit()" class="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs text-data-slate outline-none">
            <option value="">All Conferences</option>
            @foreach($conferences as $c)
                <option value="{{ $c }}" {{ request('conference') === $c ? 'selected' : '' }}>{{ $c }}</option>
            @endforeach
        </select>
        @if(request('q'))
            <input type="hidden" name="q" value="{{ request('q') }}">
        @endif
        <noscript><button type="submit" class="bg-hoop-orange text-white px-3 py-1 rounded text-xs">Filter</button></noscript>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($teams as $team)
            <a href="{{ route('teams.show', $team) }}" class="bg-court-dark border border-white/5 rounded-xl p-5 hover:border-hoop-orange/30 transition-all group">
                <div class="flex items-center gap-4">
                    <div class="size-12 rounded-full bg-white/5 grid place-items-center font-display font-black text-xl">{{ substr($team->abbreviation, 0, 2) }}</div>
                    <div>
                        <h3 class="font-display text-lg font-bold uppercase tracking-tight group-hover:text-hoop-orange transition-colors">{{ $team->name }}</h3>
                        <p class="text-data-slate text-xs">{{ $team->city }} · {{ $team->conference ?? 'N/A' }} {{ $team->division ? '· ' . $team->division : '' }}</p>
                    </div>
                </div>
                <div class="mt-4 flex gap-4 text-xs text-data-slate">
                    <span><strong class="text-white">{{ $team->championships_count }}</strong> Championships</span>
                    <span><strong class="text-white">{{ $team->home_games_count + $team->away_games_count }}</strong> Games</span>
                </div>
            </a>
        @empty
            <div class="col-span-full text-center py-12 text-data-slate">No teams found.</div>
        @endforelse
    </div>
</div>
@endsection
