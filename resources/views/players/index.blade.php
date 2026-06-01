@extends('layouts.nba')

@section('title', 'Players')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-12">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
            <h1 class="font-display italic font-black text-4xl md:text-5xl uppercase tracking-tight">Players</h1>
            <p class="text-data-slate mt-2">{{ $players->total() }} players in the database</p>
        </div>
        <form method="GET" class="flex gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name..." class="bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-sm placeholder:text-data-slate/40 outline-none focus:border-hoop-orange/50 transition-colors w-48">
            <button type="submit" class="bg-hoop-orange hover:bg-hoop-orange/90 text-white font-bold px-4 rounded-lg text-xs transition-all">Search</button>
            @if(request()->anyFilled(['q', 'position', 'team']))
                <a href="{{ route('players.index') }}" class="border border-white/10 text-data-slate hover:text-white px-4 rounded-lg text-xs font-bold transition-colors flex items-center">Clear</a>
            @endif
        </form>
    </div>

    <form method="GET" class="flex flex-wrap gap-3 mb-8">
        <select name="position" onchange="this.form.submit()" class="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs text-data-slate outline-none">
            <option value="">All Positions</option>
            @foreach($positions as $p)
                <option value="{{ $p }}" {{ request('position') === $p ? 'selected' : '' }}>{{ $p }}</option>
            @endforeach
        </select>
        <select name="team" onchange="this.form.submit()" class="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs text-data-slate outline-none">
            <option value="">All Teams</option>
            @foreach($teams as $t)
                <option value="{{ $t->id }}" {{ request('team') == $t->id ? 'selected' : '' }}>{{ $t->abbreviation }} — {{ $t->name }}</option>
            @endforeach
        </select>
        @if(request('q'))
            <input type="hidden" name="q" value="{{ request('q') }}">
        @endif
        <noscript><button type="submit" class="bg-hoop-orange text-white px-3 py-1 rounded text-xs">Filter</button></noscript>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($players as $player)
            <a href="{{ route('players.show', $player) }}" class="bg-court-dark border border-white/5 rounded-xl p-5 hover:border-hoop-orange/30 transition-all group">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="font-display text-lg font-bold uppercase tracking-tight group-hover:text-hoop-orange transition-colors">{{ $player->full_name }}</h3>
                        <p class="text-data-slate text-xs mt-1">
                            {{ $player->position ?? 'N/A' }}
                            @if($player->height) · {{ $player->height }} @endif
                            @if($player->weight) · {{ $player->weight }} lbs @endif
                        </p>
                    </div>
                    <div class="size-10 rounded-full bg-white/5 grid place-items-center text-sm font-bold text-data-slate">{{ ($player->seasonStats->sum('points') / max($player->seasonStats->sum('games_played'), 1)) | number_format(0) }}</div>
                </div>
                @php $pts = $player->seasonStats->sum('points'); $gp = $player->seasonStats->sum('games_played'); @endphp
                <div class="mt-4 flex gap-4 text-xs text-data-slate">
                    <span><strong class="text-white">{{ number_format($pts) }}</strong> PTS</span>
                    <span><strong class="text-white">{{ $gp }}</strong> GP</span>
                    <span><strong class="text-white">{{ $gp > 0 ? number_format($pts / $gp, 1) : '-' }}</strong> PPG</span>
                </div>
            </a>
        @empty
            <div class="col-span-full text-center py-12 text-data-slate">No players found matching your criteria.</div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $players->appends(request()->query())->links() }}
    </div>
</div>
@endsection
