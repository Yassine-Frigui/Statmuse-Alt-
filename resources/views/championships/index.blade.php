@extends('layouts.nba')

@section('title', 'Championships')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-12">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
            <h1 class="font-display italic font-black text-4xl md:text-5xl uppercase tracking-tight">Championships</h1>
            <p class="text-data-slate mt-2">{{ $championships->total() }} NBA Finals</p>
        </div>
        <form method="GET" class="flex gap-2">
            <select name="decade" onchange="this.form.submit()" class="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs text-data-slate outline-none">
                <option value="">All Decades</option>
                @foreach($decades as $d)
                    <option value="{{ $d }}" {{ request('decade') == $d ? 'selected' : '' }}>{{ $d }}s</option>
                @endforeach
            </select>
            <select name="team" onchange="this.form.submit()" class="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs text-data-slate outline-none">
                <option value="">All Teams</option>
                @foreach($teams as $t)
                    <option value="{{ $t->id }}" {{ request('team') == $t->id ? 'selected' : '' }}>{{ $t->abbreviation }}</option>
                @endforeach
            </select>
            @if(request()->anyFilled(['decade', 'team']))
                <a href="{{ route('championships.index') }}" class="border border-white/10 text-data-slate hover:text-white px-3 rounded-lg text-xs font-bold transition-colors flex items-center">Clear</a>
            @endif
        </form>
    </div>

    <div class="space-y-3">
        @forelse($championships as $chip)
            <div class="bg-court-dark border border-white/5 rounded-xl p-5 hover:border-hoop-orange/30 transition-all">
                <div class="flex flex-col md:flex-row md:items-center gap-4">
                    <div class="text-3xl">&#127942;</div>
                    <div class="flex-1">
                        <div class="font-display text-xl font-bold uppercase tracking-tight">{{ $chip->championTeam?->name ?? 'N/A' }}</div>
                        <p class="text-data-slate text-sm">
                            defeated {{ $chip->runnerUpTeam?->name ?? 'N/A' }}
                            <span class="text-xs text-data-slate/60">· {{ $chip->season?->year ?? 'N/A' }} Season</span>
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="font-display text-2xl font-bold">{{ $chip->season?->year ?? 'N/A' }}</div>
                        @if($chip->mvpPlayer)
                            <div class="text-xs text-data-slate">Finals MVP: <span class="text-hoop-orange">{{ $chip->mvpPlayer->full_name }}</span></div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-12 text-data-slate">No championships found.</div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $championships->appends(request()->query())->links() }}
    </div>
</div>
@endsection
