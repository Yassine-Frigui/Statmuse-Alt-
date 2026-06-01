@extends('layouts.nba')

@section('title', $player->full_name)

@section('content')
<div class="max-w-7xl mx-auto px-6 py-12">
    <a href="{{ route('players.index') }}" class="text-data-slate hover:text-white text-xs font-bold uppercase tracking-widest transition-colors">&larr; All Players</a>

    <div class="mt-4 flex flex-col md:flex-row gap-8">
        <div class="md:w-72 shrink-0">
            <div class="bg-court-dark border border-white/5 rounded-2xl p-6">
                <div class="size-24 rounded-full bg-white/5 mx-auto grid place-items-center">
                    <span class="font-display italic text-4xl font-black text-hoop-orange">{{ substr($player->first_name, 0, 1) }}{{ substr($player->last_name, 0, 1) }}</span>
                </div>
                <h1 class="font-display text-3xl font-black uppercase tracking-tight text-center mt-4">{{ $player->full_name }}</h1>
                <div class="text-center text-data-slate text-sm mt-1">
                    {{ $player->position ?? 'N/A' }} @if($player->height)· {{ $player->height }} @endif @if($player->weight)· {{ $player->weight }}lbs @endif
                </div>
                @if($player->drafted_year)
                    <div class="text-center text-data-slate text-xs mt-1">Drafted: {{ $player->drafted_year }}</div>
                @endif
                <div class="mt-6 grid grid-cols-3 gap-3 text-center">
                    <div class="bg-white/5 rounded-lg p-3">
                        <div class="font-display text-2xl font-bold">{{ number_format($careerTotals->points) }}</div>
                        <div class="text-[10px] text-data-slate uppercase tracking-wider">PTS</div>
                    </div>
                    <div class="bg-white/5 rounded-lg p-3">
                        <div class="font-display text-2xl font-bold">{{ number_format($careerTotals->rebounds) }}</div>
                        <div class="text-[10px] text-data-slate uppercase tracking-wider">REB</div>
                    </div>
                    <div class="bg-white/5 rounded-lg p-3">
                        <div class="font-display text-2xl font-bold">{{ number_format($careerTotals->assists) }}</div>
                        <div class="text-[10px] text-data-slate uppercase tracking-wider">AST</div>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-3 text-center">
                    <div class="bg-white/5 rounded-lg p-3">
                        <div class="font-bold">{{ number_format($careerTotals->steals) }}</div>
                        <div class="text-[10px] text-data-slate uppercase tracking-wider">STL</div>
                    </div>
                    <div class="bg-white/5 rounded-lg p-3">
                        <div class="font-bold">{{ number_format($careerTotals->blocks) }}</div>
                        <div class="text-[10px] text-data-slate uppercase tracking-wider">BLK</div>
                    </div>
                </div>
            </div>

            @if($player->awards->isNotEmpty())
                <div class="mt-4 bg-court-dark border border-white/5 rounded-2xl p-6">
                    <h2 class="font-display text-sm font-bold uppercase tracking-wide mb-3">Awards</h2>
                    <div class="space-y-2">
                        @foreach($player->awards as $pa)
                            <div class="flex items-center gap-2 text-sm">
                                <span class="text-hoop-orange">&#9733;</span>
                                <span class="text-data-slate">{{ $pa->award?->name ?? 'Unknown' }}</span>
                                @if($pa->season)<span class="text-xs text-data-slate/60">{{ $pa->season->year }}</span>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="flex-1 min-w-0">
            <div class="bg-court-dark border border-white/5 rounded-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-white/5">
                    <h2 class="font-display text-lg font-bold uppercase tracking-wide">Season Stats</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-data-slate font-bold uppercase tracking-wider border-b border-white/5">
                                <th class="px-4 py-3">Season</th>
                                <th class="px-4 py-3">Team</th>
                                <th class="px-4 py-3 text-right">GP</th>
                                <th class="px-4 py-3 text-right">PTS</th>
                                <th class="px-4 py-3 text-right">REB</th>
                                <th class="px-4 py-3 text-right">AST</th>
                                <th class="px-4 py-3 text-right">STL</th>
                                <th class="px-4 py-3 text-right">BLK</th>
                                <th class="px-4 py-3 text-right">FG%</th>
                                <th class="px-4 py-3 text-right">3P%</th>
                                <th class="px-4 py-3 text-right">FT%</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($player->seasonStats as $stat)
                                <tr class="border-b border-white/5 hover:bg-white/[0.02] transition-colors">
                                    <td class="px-4 py-3 font-medium">{{ $stat->season?->year ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-data-slate">{{ $stat->team?->abbreviation ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-right">{{ $stat->games_played }}</td>
                                    <td class="px-4 py-3 text-right font-bold">{{ number_format($stat->points) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($stat->rebounds) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($stat->assists) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($stat->steals) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($stat->blocks) }}</td>
                                    <td class="px-4 py-3 text-right">{{ $stat->fg_pct !== null ? number_format($stat->fg_pct * 100, 1) : '-' }}</td>
                                    <td class="px-4 py-3 text-right">{{ $stat->three_pct !== null ? number_format($stat->three_pct * 100, 1) : '-' }}</td>
                                    <td class="px-4 py-3 text-right">{{ $stat->ft_pct !== null ? number_format($stat->ft_pct * 100, 1) : '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="px-4 py-8 text-center text-data-slate">No season stats available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($player->gameStats->isNotEmpty())
                <div class="mt-6 bg-court-dark border border-white/5 rounded-2xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-white/5">
                        <h2 class="font-display text-lg font-bold uppercase tracking-wide">Recent Games</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-data-slate font-bold uppercase tracking-wider border-b border-white/5">
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Matchup</th>
                                    <th class="px-4 py-3 text-right">PTS</th>
                                    <th class="px-4 py-3 text-right">REB</th>
                                    <th class="px-4 py-3 text-right">AST</th>
                                    <th class="px-4 py-3 text-right">STL</th>
                                    <th class="px-4 py-3 text-right">BLK</th>
                                    <th class="px-4 py-3 text-right">FG</th>
                                    <th class="px-4 py-3 text-right">3PT</th>
                                    <th class="px-4 py-3 text-right">FT</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($player->gameStats as $gs)
                                    <tr class="border-b border-white/5 hover:bg-white/[0.02] transition-colors">
                                        <td class="px-4 py-3">{{ \Carbon\Carbon::parse($gs->game->date)->format('M j, Y') }}</td>
                                        <td class="px-4 py-3 text-data-slate">{{ $gs->game->homeTeam->abbreviation }} vs {{ $gs->game->awayTeam->abbreviation }}</td>
                                        <td class="px-4 py-3 text-right font-bold">{{ $gs->points }}</td>
                                        <td class="px-4 py-3 text-right">{{ $gs->offensive_rebounds + $gs->defensive_rebounds }}</td>
                                        <td class="px-4 py-3 text-right">{{ $gs->assists }}</td>
                                        <td class="px-4 py-3 text-right">{{ $gs->steals }}</td>
                                        <td class="px-4 py-3 text-right">{{ $gs->blocks }}</td>
                                        <td class="px-4 py-3 text-right">{{ $gs->fgm }}/{{ $gs->fga }}</td>
                                        <td class="px-4 py-3 text-right">{{ $gs->fg3m }}/{{ $gs->fg3a }}</td>
                                        <td class="px-4 py-3 text-right">{{ $gs->ftm }}/{{ $gs->fta }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
