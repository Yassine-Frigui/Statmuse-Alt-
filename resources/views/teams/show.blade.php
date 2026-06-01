@extends('layouts.nba')

@section('title', $team->name)

@section('content')
<div class="max-w-7xl mx-auto px-6 py-12">
    <a href="{{ route('teams.index') }}" class="text-data-slate hover:text-white text-xs font-bold uppercase tracking-widest transition-colors">&larr; All Teams</a>

    <div class="mt-4 flex flex-col md:flex-row gap-8">
        <div class="md:w-72 shrink-0">
            <div class="bg-court-dark border border-white/5 rounded-2xl p-6">
                <div class="size-24 rounded-full bg-white/5 mx-auto grid place-items-center">
                    <span class="font-display italic text-4xl font-black text-hoop-orange">{{ substr($team->abbreviation, 0, 2) }}</span>
                </div>
                <h1 class="font-display text-3xl font-black uppercase tracking-tight text-center mt-4">{{ $team->name }}</h1>
                <p class="text-center text-data-slate text-sm">{{ $team->city }}</p>
                <div class="mt-4 flex justify-center gap-2">
                    <span class="px-3 py-1 bg-white/5 rounded-full text-xs text-data-slate">{{ $team->conference ?? 'N/A' }}</span>
                    @if($team->division)
                        <span class="px-3 py-1 bg-white/5 rounded-full text-xs text-data-slate">{{ $team->division }}</span>
                    @endif
                </div>
                <div class="mt-6 grid grid-cols-2 gap-3 text-center">
                    <div class="bg-white/5 rounded-lg p-3">
                        <div class="font-display text-2xl font-bold">{{ $team->championships->count() }}</div>
                        <div class="text-[10px] text-data-slate uppercase tracking-wider">Titles</div>
                    </div>
                    <div class="bg-white/5 rounded-lg p-3">
                        <div class="font-display text-2xl font-bold">{{ $team->homeGames->count() + $team->homeGames->count() }}</div>
                        <div class="text-[10px] text-data-slate uppercase tracking-wider">Games</div>
                    </div>
                </div>
            </div>

            @if($team->championships->isNotEmpty())
                <div class="mt-4 bg-court-dark border border-white/5 rounded-2xl p-6">
                    <h2 class="font-display text-sm font-bold uppercase tracking-wide mb-3">Championships</h2>
                    <div class="space-y-2">
                        @foreach($team->championships as $chip)
                            <div class="flex items-center gap-2 text-sm">
                                <span class="text-hoop-orange">&#127942;</span>
                                <span class="text-data-slate">{{ $chip->season?->year ?? 'N/A' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="flex-1 min-w-0 space-y-6">
            <div class="bg-court-dark border border-white/5 rounded-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-white/5">
                    <h2 class="font-display text-lg font-bold uppercase tracking-wide">Season Stats</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-data-slate font-bold uppercase tracking-wider border-b border-white/5">
                                <th class="px-4 py-3">Season</th>
                                <th class="px-4 py-3 text-right">PTS</th>
                                <th class="px-4 py-3 text-right">REB</th>
                                <th class="px-4 py-3 text-right">AST</th>
                                <th class="px-4 py-3 text-right">GP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($seasonStats as $stat)
                                <tr class="border-b border-white/5 hover:bg-white/[0.02] transition-colors">
                                    <td class="px-4 py-3 font-medium">{{ $stat->season?->year ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-right font-bold">{{ number_format($stat->pts) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($stat->reb) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($stat->ast) }}</td>
                                    <td class="px-4 py-3 text-right">{{ $stat->gp }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center text-data-slate">No stats available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-court-dark border border-white/5 rounded-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-white/5">
                    <h2 class="font-display text-lg font-bold uppercase tracking-wide">Recent Games</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-data-slate font-bold uppercase tracking-wider border-b border-white/5">
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Matchup</th>
                                <th class="px-4 py-3 text-right">Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($team->homeGames as $game)
                                <tr class="border-b border-white/5 hover:bg-white/[0.02] transition-colors">
                                    <td class="px-4 py-3">{{ \Carbon\Carbon::parse($game->date)->format('M j, Y') }}</td>
                                    <td class="px-4 py-3 text-data-slate">{{ $game->homeTeam->abbreviation }} vs {{ $game->awayTeam->abbreviation }}</td>
                                    <td class="px-4 py-3 text-right font-bold">{{ $game->home_score }} - {{ $game->away_score }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-8 text-center text-data-slate">No games available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
