@extends('layouts.nba')

@section('title', $season->year . ' Season')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-12">
    <a href="{{ route('seasons.index') }}" class="text-data-slate hover:text-white text-xs font-bold uppercase tracking-widest transition-colors">&larr; All Seasons</a>

    <div class="mt-4 mb-8">
        <h1 class="font-display italic font-black text-4xl md:text-5xl uppercase tracking-tight">{{ $season->year }}–{{ substr($season->year + 1, -2) }} Season</h1>
        <p class="text-data-slate mt-2">{{ $season->label ?? 'NBA Season' }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Champions --}}
        <div class="bg-court-dark border border-white/5 rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-white/5">
                <h2 class="font-display text-lg font-bold uppercase tracking-wide">Champion</h2>
            </div>
            <div class="p-6">
                @if($season->championship)
                    <div class="text-center">
                        <div class="text-4xl mb-2">&#127942;</div>
                        <h3 class="font-display text-xl font-bold">{{ $season->championship->championTeam?->name ?? 'N/A' }}</h3>
                        <p class="text-data-slate text-sm mt-1">defeated {{ $season->championship->runnerUpTeam?->name ?? 'N/A' }}</p>
                        @if($season->championship->mvpPlayer)
                            <p class="text-xs text-data-slate mt-2">Finals MVP: <span class="text-hoop-orange">{{ $season->championship->mvpPlayer->full_name }}</span></p>
                        @endif
                    </div>
                @else
                    <p class="text-data-slate text-sm text-center">No championship data available.</p>
                @endif
            </div>
        </div>

        {{-- Scoring Leaders --}}
        <div class="bg-court-dark border border-white/5 rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-white/5">
                <h2 class="font-display text-lg font-bold uppercase tracking-wide">Scoring Leaders</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-data-slate font-bold uppercase tracking-wider border-b border-white/5">
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Player</th>
                            <th class="px-4 py-3 text-right">PTS</th>
                            <th class="px-4 py-3 text-right">GP</th>
                            <th class="px-4 py-3 text-right">PPG</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($scoringLeaders as $i => $sl)
                            <tr class="border-b border-white/5 hover:bg-white/[0.02] transition-colors">
                                <td class="px-4 py-3 text-data-slate">{{ $i + 1 }}</td>
                                <td class="px-4 py-3">{{ $sl->player?->full_name ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-right font-bold">{{ number_format($sl->total_pts) }}</td>
                                <td class="px-4 py-3 text-right">{{ $sl->gp }}</td>
                                <td class="px-4 py-3 text-right">{{ $sl->gp > 0 ? number_format($sl->total_pts / $sl->gp, 1) : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-data-slate">No data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Rebound Leaders --}}
        <div class="bg-court-dark border border-white/5 rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-white/5">
                <h2 class="font-display text-lg font-bold uppercase tracking-wide">Rebound Leaders</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-data-slate font-bold uppercase tracking-wider border-b border-white/5">
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Player</th>
                            <th class="px-4 py-3 text-right">REB</th>
                            <th class="px-4 py-3 text-right">GP</th>
                            <th class="px-4 py-3 text-right">RPG</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reboundLeaders as $i => $rl)
                            <tr class="border-b border-white/5 hover:bg-white/[0.02] transition-colors">
                                <td class="px-4 py-3 text-data-slate">{{ $i + 1 }}</td>
                                <td class="px-4 py-3">{{ $rl->player?->full_name ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-right font-bold">{{ number_format($rl->total_reb) }}</td>
                                <td class="px-4 py-3 text-right">{{ $rl->gp }}</td>
                                <td class="px-4 py-3 text-right">{{ $rl->gp > 0 ? number_format($rl->total_reb / $rl->gp, 1) : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-data-slate">No data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Assist Leaders --}}
        <div class="col-span-1 lg:col-span-3 bg-court-dark border border-white/5 rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-white/5">
                <h2 class="font-display text-lg font-bold uppercase tracking-wide">Assist Leaders</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-data-slate font-bold uppercase tracking-wider border-b border-white/5">
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Player</th>
                            <th class="px-4 py-3 text-right">AST</th>
                            <th class="px-4 py-3 text-right">GP</th>
                            <th class="px-4 py-3 text-right">APG</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assistLeaders as $i => $al)
                            <tr class="border-b border-white/5 hover:bg-white/[0.02] transition-colors">
                                <td class="px-4 py-3 text-data-slate">{{ $i + 1 }}</td>
                                <td class="px-4 py-3">{{ $al->player?->full_name ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-right font-bold">{{ number_format($al->total_ast) }}</td>
                                <td class="px-4 py-3 text-right">{{ $al->gp }}</td>
                                <td class="px-4 py-3 text-right">{{ $al->gp > 0 ? number_format($al->total_ast / $al->gp, 1) : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-data-slate">No data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
