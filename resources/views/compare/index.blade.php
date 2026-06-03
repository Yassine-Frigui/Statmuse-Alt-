@extends('layouts.nba')

@section('title', 'Comparison')

@push('head')
    <meta name="description" content="Compare two sports entities side by side.">
    <style>
        .bar-fill { transition: width 0.8s cubic-bezier(0.22, 1, 0.36, 1); }
        .stat-card { transition: all 0.2s ease; }
        .stat-card:hover { transform: translateY(-1px); }
        .profile-gradient-a { background: linear-gradient(135deg, rgba(255,107,53,0.15), rgba(255,107,53,0.05)); }
        .profile-gradient-b { background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(59,130,246,0.05)); }
        .archetype-badge { background: linear-gradient(135deg, rgba(255,215,0,0.2), rgba(255,215,0,0.05)); border: 1px solid rgba(255,215,0,0.3); }
        .strength-dot { background: #22c55e; box-shadow: 0 0 8px rgba(34,197,94,0.4); }
        .weakness-dot { background: #ef4444; box-shadow: 0 0 8px rgba(239,68,68,0.4); }
    </style>
@endpush

@section('content')
<div x-data="compare" class="min-h-screen bg-court-black text-white">
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-12 pb-24">
        {{-- Header --}}
        <header class="mb-8 sm:mb-10">
            <h1 class="font-display italic font-black text-4xl sm:text-5xl md:text-6xl uppercase tracking-tight leading-none">
                Head-to-<span class="text-hoop-orange">Head</span>
            </h1>
            <p class="text-data-slate mt-3 max-w-2xl text-sm sm:text-base">
                Compare two teams or players side by side.
            </p>
        </header>

        {{-- Selectors --}}
        <div class="grid md:grid-cols-5 gap-3 sm:gap-4 items-end">
            <div class="md:col-span-2 relative">
                <label class="text-[10px] font-bold uppercase tracking-widest text-data-slate block mb-2" x-text="entityLabel + ' A'">Entity A</label>
                <input
                    x-ref="inputA"
                    x-model="queryA"
                    @input.debounce.300ms="searchA"
                    @focus="showDropdownA = true"
                    @click.outside="showDropdownA = false"
                    @keydown.escape="showDropdownA = false"
                    @keydown.down.prevent="$focus.wrap().next()"
                    @keydown.up.prevent="$focus.wrap().prev()"
                    type="text"
                    placeholder="Search..."
                    class="w-full bg-court-dark border border-white/10 rounded-xl px-4 py-3 text-sm outline-none focus:border-hoop-orange/50 transition-colors placeholder:text-data-slate/40"
                >
                <template x-if="resultsA.length > 0 && showDropdownA">
                    <ul class="absolute z-50 mt-1 w-full bg-court-dark border border-white/10 rounded-xl shadow-2xl overflow-hidden max-h-60 overflow-y-auto">
                        <template x-for="p in resultsA" :key="p.id">
                            <li>
                                <button
                                    @click="selectA(p)"
                                    class="w-full text-left px-4 py-3 text-sm hover:bg-white/5 transition-colors flex justify-between items-center"
                                >
                                    <span x-text="p.label"></span>
                                    <span class="text-[10px] text-data-slate uppercase" x-text="p.position || ''"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </template>
                <template x-if="selectedA">
                    <div class="mt-2 px-3 py-2 bg-hoop-orange/10 border border-hoop-orange/30 rounded-lg flex justify-between items-center">
                        <span class="text-sm font-medium truncate" x-text="selectedA.label"></span>
                        <button @click="clearA" class="text-data-slate hover:text-white text-xs shrink-0 ml-2">&times;</button>
                    </div>
                </template>
            </div>

            <div class="text-center text-data-slate font-display text-xl sm:text-2xl font-black italic">VS</div>

            <div class="md:col-span-2 relative">
                <label class="text-[10px] font-bold uppercase tracking-widest text-data-slate block mb-2" x-text="entityLabel + ' B'">Entity B</label>
                <input
                    x-ref="inputB"
                    x-model="queryB"
                    @input.debounce.300ms="searchB"
                    @focus="showDropdownB = true"
                    @click.outside="showDropdownB = false"
                    @keydown.escape="showDropdownB = false"
                    @keydown.down.prevent="$focus.wrap().next()"
                    @keydown.up.prevent="$focus.wrap().prev()"
                    type="text"
                    placeholder="Search..."
                    class="w-full bg-court-dark border border-white/10 rounded-xl px-4 py-3 text-sm outline-none focus:border-blue-400/50 transition-colors placeholder:text-data-slate/40"
                >
                <template x-if="resultsB.length > 0 && showDropdownB">
                    <ul class="absolute z-50 mt-1 w-full bg-court-dark border border-white/10 rounded-xl shadow-2xl overflow-hidden max-h-60 overflow-y-auto">
                        <template x-for="p in resultsB" :key="p.id">
                            <li>
                                <button
                                    @click="selectB(p)"
                                    class="w-full text-left px-4 py-3 text-sm hover:bg-white/5 transition-colors flex justify-between items-center"
                                >
                                    <span x-text="p.label"></span>
                                    <span class="text-[10px] text-data-slate uppercase" x-text="p.position || ''"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </template>
                <template x-if="selectedB">
                    <div class="mt-2 px-3 py-2 bg-blue-500/10 border border-blue-500/30 rounded-lg flex justify-between items-center">
                        <span class="text-sm font-medium truncate" x-text="selectedB.label"></span>
                        <button @click="clearB" class="text-data-slate hover:text-white text-xs shrink-0 ml-2">&times;</button>
                    </div>
                </template>
            </div>
        </div>

        <div class="mt-6 flex justify-center">
            <button
                @click="runComparison"
                :disabled="!selectedA || !selectedB || loading"
                class="bg-hoop-orange hover:bg-hoop-orange/90 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-3 px-10 rounded-xl text-sm transition-all tracking-wider"
            >
                <span x-show="!loading">COMPARE</span>
                <span x-show="loading">Comparing...</span>
            </button>
        </div>

        {{-- Error --}}
        <template x-if="error">
            <div class="mt-12 bg-red-950/30 border border-red-500/30 rounded-xl p-6">
                <h3 class="font-display italic font-bold text-xl uppercase text-red-400 mb-2">Error</h3>
                <p class="text-sm text-data-slate font-mono break-all" x-text="error"></p>
            </div>
        </template>

        {{-- Results --}}
        <template x-if="result">
            <div class="mt-10 space-y-10">
                {{-- Score Banner --}}
                <template x-if="result.comparison">
                    <div class="bg-court-dark border border-white/10 rounded-2xl p-6 text-center">
                        <div class="flex items-center justify-center gap-4 sm:gap-8 flex-wrap">
                            <span class="text-hoop-orange font-display font-black text-xl sm:text-2xl" x-text="nameA"></span>
                            <div class="flex items-center gap-3">
                                <div class="text-3xl sm:text-4xl font-display font-black" :class="result.comparison.winner === 'a' ? 'text-hoop-orange' : result.comparison.winner === 'b' ? 'text-blue-400' : 'text-data-slate'" x-text="result.comparison.aWins"></div>
                                <span class="text-data-slate text-lg font-bold">-</span>
                                <div class="text-3xl sm:text-4xl font-display font-black" :class="result.comparison.winner === 'b' ? 'text-blue-400' : result.comparison.winner === 'a' ? 'text-hoop-orange' : 'text-data-slate'" x-text="result.comparison.bWins"></div>
                            </div>
                            <span class="text-blue-400 font-display font-black text-xl sm:text-2xl" x-text="nameB"></span>
                        </div>
                        <p class="text-xs text-data-slate mt-2">Stat categories won (higher is better)</p>
                    </div>
                </template>

                {{-- Side-by-Side Profiles --}}
                <div class="grid md:grid-cols-2 gap-6">
                    {{-- Player/Team A Profile --}}
                    <template x-if="result.player_a">
                        <div>
                            {{-- NBA Player Profile --}}
                            <template x-if="isNba">
                                <div class="profile-gradient-a border border-hoop-orange/20 rounded-2xl overflow-hidden">
                                    <div class="p-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="text-xl font-display italic font-black uppercase" x-text="result.player_a.player.name"></h3>
                                            <span class="text-[10px] uppercase font-bold px-2 py-1 rounded bg-hoop-orange/20 text-hoop-orange" x-text="result.player_a.player.position || 'N/A'"></span>
                                        </div>
                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5 text-center">
                                            <div class="bg-white/5 rounded-lg p-2">
                                                <p class="text-[9px] uppercase tracking-widest text-data-slate">Height</p>
                                                <p class="text-sm font-bold" x-text="result.player_a.player.height || '-'"></p>
                                            </div>
                                            <div class="bg-white/5 rounded-lg p-2">
                                                <p class="text-[9px] uppercase tracking-widest text-data-slate">Weight</p>
                                                <p class="text-sm font-bold" x-text="result.player_a.player.weight ? result.player_a.player.weight + ' lbs' : '-'"></p>
                                            </div>
                                            <div class="bg-white/5 rounded-lg p-2">
                                                <p class="text-[9px] uppercase tracking-widest text-data-slate">Drafted</p>
                                                <p class="text-sm font-bold" x-text="result.player_a.player.drafted_year || 'Undrafted'"></p>
                                            </div>
                                            <div class="bg-white/5 rounded-lg p-2">
                                                <p class="text-[9px] uppercase tracking-widest text-data-slate">College</p>
                                                <p class="text-sm font-bold truncate" x-text="result.player_a.player.college || '-'"></p>
                                            </div>
                                        </div>
                                        <template x-if="result.player_a.archetype">
                                            <div class="archetype-badge rounded-xl p-4 mb-5">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="text-lg" x-text="result.player_a.archetype.icon"></span>
                                                    <span class="font-display font-bold text-sm uppercase tracking-wide text-amber-300" x-text="result.player_a.archetype.primary"></span>
                                                    <template x-if="result.player_a.archetype.secondary">
                                                        <span class="text-amber-300/60 text-xs">/ <span x-text="result.player_a.archetype.secondary"></span></span>
                                                    </template>
                                                </div>
                                                <p class="text-xs text-data-slate leading-relaxed" x-text="result.player_a.archetype.description"></p>
                                            </div>
                                        </template>
                                        <div class="mb-5">
                                            <h4 class="text-[10px] uppercase tracking-widest font-bold text-data-slate mb-3">Per Game Averages</h4>
                                            <div class="grid grid-cols-5 gap-2">
                                                <div class="stat-card bg-white/5 rounded-lg p-2 text-center">
                                                    <p class="text-[9px] uppercase text-data-slate">PPG</p>
                                                    <p class="text-lg font-display font-black text-hoop-orange" x-text="result.player_a.per_game_averages.ppg"></p>
                                                </div>
                                                <div class="stat-card bg-white/5 rounded-lg p-2 text-center">
                                                    <p class="text-[9px] uppercase text-data-slate">RPG</p>
                                                    <p class="text-lg font-display font-black" x-text="result.player_a.per_game_averages.rpg"></p>
                                                </div>
                                                <div class="stat-card bg-white/5 rounded-lg p-2 text-center">
                                                    <p class="text-[9px] uppercase text-data-slate">APG</p>
                                                    <p class="text-lg font-display font-black" x-text="result.player_a.per_game_averages.apg"></p>
                                                </div>
                                                <div class="stat-card bg-white/5 rounded-lg p-2 text-center">
                                                    <p class="text-[9px] uppercase text-data-slate">SPG</p>
                                                    <p class="text-lg font-display font-black" x-text="result.player_a.per_game_averages.spg"></p>
                                                </div>
                                                <div class="stat-card bg-white/5 rounded-lg p-2 text-center">
                                                    <p class="text-[9px] uppercase text-data-slate">BPG</p>
                                                    <p class="text-lg font-display font-black" x-text="result.player_a.per_game_averages.bpg"></p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-5">
                                            <h4 class="text-[10px] uppercase tracking-widest font-bold text-data-slate mb-3">Shooting Splits</h4>
                                            <div class="grid grid-cols-3 gap-2 text-center">
                                                <div class="bg-white/5 rounded-lg p-2"><p class="text-[9px] uppercase text-data-slate">FG%</p><p class="text-base font-bold" x-text="formatPct(result.player_a.per_game_averages.fg_pct)"></p></div>
                                                <div class="bg-white/5 rounded-lg p-2"><p class="text-[9px] uppercase text-data-slate">3P%</p><p class="text-base font-bold" x-text="formatPct(result.player_a.per_game_averages.three_pct)"></p></div>
                                                <div class="bg-white/5 rounded-lg p-2"><p class="text-[9px] uppercase text-data-slate">FT%</p><p class="text-base font-bold" x-text="formatPct(result.player_a.per_game_averages.ft_pct)"></p></div>
                                            </div>
                                        </div>
                                        <template x-if="result.player_a.strengths && result.player_a.strengths.length">
                                            <div class="mb-5"><h4 class="text-[10px] uppercase tracking-widest font-bold text-green-400 mb-3">Strengths</h4>
                                                <ul class="space-y-1.5"><template x-for="s in result.player_a.strengths" :key="s"><li class="flex items-start gap-2 text-xs text-data-slate"><span class="mt-1.5 size-1.5 rounded-full strength-dot shrink-0"></span><span x-text="s"></span></li></template></ul></div>
                                        </template>
                                        <template x-if="result.player_a.weaknesses && result.player_a.weaknesses.length">
                                            <div><h4 class="text-[10px] uppercase tracking-widest font-bold text-red-400 mb-3">Areas for Improvement</h4>
                                                <ul class="space-y-1.5"><template x-for="w in result.player_a.weaknesses" :key="w"><li class="flex items-start gap-2 text-xs text-data-slate"><span class="mt-1.5 size-1.5 rounded-full weakness-dot shrink-0"></span><span x-text="w"></span></li></template></ul></div>
                                        </template>
                                    </div>
                                    <template x-if="result.player_a.peak_season">
                                        <div class="border-t border-white/5 px-6 py-4">
                                            <p class="text-[9px] uppercase tracking-widest font-bold text-data-slate mb-2">Peak Season</p>
                                            <div class="flex items-center gap-4 text-sm">
                                                <span class="text-hoop-orange font-bold" x-text="result.player_a.peak_season.year"></span>
                                                <span x-text="result.player_a.peak_season.ppg + ' PPG'"></span>
                                                <span x-text="result.player_a.peak_season.rpg + ' RPG'"></span>
                                                <span x-text="result.player_a.peak_season.apg + ' APG'"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            {{-- CL Team Profile --}}
                            <template x-if="!isNba">
                                <div class="profile-gradient-a border border-hoop-orange/20 rounded-2xl overflow-hidden">
                                    <div class="p-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="text-xl font-display italic font-black uppercase" x-text="result.player_a.team?.name || nameA"></h3>
                                            <span class="text-[10px] uppercase font-bold px-2 py-1 rounded bg-white/10 text-data-slate">UCL</span>
                                        </div>
                                        {{-- Archetype --}}
                                        <template x-if="result.player_a.archetype">
                                            <div class="archetype-badge rounded-xl p-4 mb-5">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="text-lg" x-text="result.player_a.archetype.icon"></span>
                                                    <span class="font-display font-bold text-sm uppercase tracking-wide text-amber-300" x-text="result.player_a.archetype.primary"></span>
                                                </div>
                                                <p class="text-xs text-data-slate leading-relaxed" x-text="result.player_a.archetype.description"></p>
                                            </div>
                                        </template>
                                        {{-- Overall Stats --}}
                                        <h4 class="text-[10px] uppercase tracking-widest font-bold text-data-slate mb-3">Overall Record</h4>
                                        <div class="grid grid-cols-4 gap-2 mb-3">
                                            <div class="bg-white/5 rounded-lg p-2 text-center">
                                                <p class="text-[9px] uppercase text-data-slate">W</p>
                                                <p class="text-lg font-display font-black text-green-400" x-text="result.player_a.stats?.wins || '0'"></p>
                                            </div>
                                            <div class="bg-white/5 rounded-lg p-2 text-center">
                                                <p class="text-[9px] uppercase text-data-slate">D</p>
                                                <p class="text-lg font-display font-black text-amber-400" x-text="result.player_a.stats?.draws || '0'"></p>
                                            </div>
                                            <div class="bg-white/5 rounded-lg p-2 text-center">
                                                <p class="text-[9px] uppercase text-data-slate">L</p>
                                                <p class="text-lg font-display font-black text-red-400" x-text="result.player_a.stats?.losses || '0'"></p>
                                            </div>
                                            <div class="bg-white/5 rounded-lg p-2 text-center">
                                                <p class="text-[9px] uppercase text-data-slate">Pts</p>
                                                <p class="text-lg font-display font-black text-hoop-orange" x-text="result.player_a.stats?.points || '0'"></p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-3 gap-2 mb-5">
                                            <div class="bg-white/5 rounded-lg p-2 text-center">
                                                <p class="text-[9px] uppercase text-data-slate">GF</p>
                                                <p class="text-sm font-bold" x-text="result.player_a.stats?.goals_for || '0'"></p>
                                            </div>
                                            <div class="bg-white/5 rounded-lg p-2 text-center">
                                                <p class="text-[9px] uppercase text-data-slate">GA</p>
                                                <p class="text-sm font-bold" x-text="result.player_a.stats?.goals_against || '0'"></p>
                                            </div>
                                            <div class="bg-white/5 rounded-lg p-2 text-center">
                                                <p class="text-[9px] uppercase text-data-slate">GD</p>
                                                <p class="text-sm font-bold" :class="result.player_a.stats?.goal_difference >= 0 ? 'text-green-400' : 'text-red-400'" x-text="(result.player_a.stats?.goal_difference ?? 0) >= 0 ? '+' + result.player_a.stats.goal_difference : result.player_a.stats.goal_difference"></p>
                                            </div>
                                        </div>
                                        {{-- Stage Breakdown --}}
                                        <template x-if="result.player_a.stage_breakdown">
                                            <div>
                                                <h4 class="text-[10px] uppercase tracking-widest font-bold text-data-slate mb-3">By Stage</h4>
                                                <div class="grid grid-cols-2 gap-2 mb-5">
                                                    <div class="bg-white/5 rounded-lg p-3" x-show="result.player_a.stage_breakdown.group_phase">
                                                        <p class="text-[9px] uppercase tracking-widest text-data-slate mb-1">Group Phase</p>
                                                        <p class="text-xs"><span class="text-green-400 font-bold" x-text="result.player_a.stage_breakdown.group_phase.wins || 0"></span><span class="text-data-slate">W </span><span class="text-amber-400 font-bold" x-text="result.player_a.stage_breakdown.group_phase.draws || 0"></span><span class="text-data-slate">D </span><span class="text-red-400 font-bold" x-text="result.player_a.stage_breakdown.group_phase.losses || 0"></span><span class="text-data-slate">L</span></p>
                                                        <p class="text-[10px] text-data-slate mt-1">GF <span class="text-white" x-text="result.player_a.stage_breakdown.group_phase.goals_for || 0"></span> GA <span class="text-white" x-text="result.player_a.stage_breakdown.group_phase.goals_against || 0"></span></p>
                                                    </div>
                                                    <div class="bg-white/5 rounded-lg p-3" x-show="result.player_a.stage_breakdown.knockout">
                                                        <p class="text-[9px] uppercase tracking-widest text-data-slate mb-1">Knockout</p>
                                                        <p class="text-xs"><span class="text-green-400 font-bold" x-text="result.player_a.stage_breakdown.knockout.wins || 0"></span><span class="text-data-slate">W </span><span class="text-amber-400 font-bold" x-text="result.player_a.stage_breakdown.knockout.draws || 0"></span><span class="text-data-slate">D </span><span class="text-red-400 font-bold" x-text="result.player_a.stage_breakdown.knockout.losses || 0"></span><span class="text-data-slate">L</span></p>
                                                        <p class="text-[10px] text-data-slate mt-1">GF <span class="text-white" x-text="result.player_a.stage_breakdown.knockout.goals_for || 0"></span> GA <span class="text-white" x-text="result.player_a.stage_breakdown.knockout.goals_against || 0"></span></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Player/Team B Profile --}}
                    <template x-if="result.player_b">
                        <div>
                            <template x-if="isNba">
                                <div class="profile-gradient-b border border-blue-500/20 rounded-2xl overflow-hidden">
                                    <div class="p-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="text-xl font-display italic font-black uppercase" x-text="result.player_b.player.name"></h3>
                                            <span class="text-[10px] uppercase font-bold px-2 py-1 rounded bg-blue-500/20 text-blue-400" x-text="result.player_b.player.position || 'N/A'"></span>
                                        </div>
                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5 text-center">
                                            <div class="bg-white/5 rounded-lg p-2"><p class="text-[9px] uppercase tracking-widest text-data-slate">Height</p><p class="text-sm font-bold" x-text="result.player_b.player.height || '-'"></p></div>
                                            <div class="bg-white/5 rounded-lg p-2"><p class="text-[9px] uppercase tracking-widest text-data-slate">Weight</p><p class="text-sm font-bold" x-text="result.player_b.player.weight ? result.player_b.player.weight + ' lbs' : '-'"></p></div>
                                            <div class="bg-white/5 rounded-lg p-2"><p class="text-[9px] uppercase tracking-widest text-data-slate">Drafted</p><p class="text-sm font-bold" x-text="result.player_b.player.drafted_year || 'Undrafted'"></p></div>
                                            <div class="bg-white/5 rounded-lg p-2"><p class="text-[9px] uppercase tracking-widest text-data-slate">College</p><p class="text-sm font-bold truncate" x-text="result.player_b.player.college || '-'"></p></div>
                                        </div>
                                        <template x-if="result.player_b.archetype">
                                            <div class="archetype-badge rounded-xl p-4 mb-5">
                                                <div class="flex items-center gap-2 mb-1"><span class="text-lg" x-text="result.player_b.archetype.icon"></span><span class="font-display font-bold text-sm uppercase tracking-wide text-amber-300" x-text="result.player_b.archetype.primary"></span><template x-if="result.player_b.archetype.secondary"><span class="text-amber-300/60 text-xs">/ <span x-text="result.player_b.archetype.secondary"></span></span></template></div>
                                                <p class="text-xs text-data-slate leading-relaxed" x-text="result.player_b.archetype.description"></p>
                                            </div>
                                        </template>
                                        <div class="mb-5">
                                            <h4 class="text-[10px] uppercase tracking-widest font-bold text-data-slate mb-3">Per Game Averages</h4>
                                            <div class="grid grid-cols-5 gap-2">
                                                <div class="stat-card bg-white/5 rounded-lg p-2 text-center"><p class="text-[9px] uppercase text-data-slate">PPG</p><p class="text-lg font-display font-black text-blue-400" x-text="result.player_b.per_game_averages.ppg"></p></div>
                                                <div class="stat-card bg-white/5 rounded-lg p-2 text-center"><p class="text-[9px] uppercase text-data-slate">RPG</p><p class="text-lg font-display font-black" x-text="result.player_b.per_game_averages.rpg"></p></div>
                                                <div class="stat-card bg-white/5 rounded-lg p-2 text-center"><p class="text-[9px] uppercase text-data-slate">APG</p><p class="text-lg font-display font-black" x-text="result.player_b.per_game_averages.apg"></p></div>
                                                <div class="stat-card bg-white/5 rounded-lg p-2 text-center"><p class="text-[9px] uppercase text-data-slate">SPG</p><p class="text-lg font-display font-black" x-text="result.player_b.per_game_averages.spg"></p></div>
                                                <div class="stat-card bg-white/5 rounded-lg p-2 text-center"><p class="text-[9px] uppercase text-data-slate">BPG</p><p class="text-lg font-display font-black" x-text="result.player_b.per_game_averages.bpg"></p></div>
                                            </div>
                                        </div>
                                        <div class="mb-5">
                                            <h4 class="text-[10px] uppercase tracking-widest font-bold text-data-slate mb-3">Shooting Splits</h4>
                                            <div class="grid grid-cols-3 gap-2 text-center">
                                                <div class="bg-white/5 rounded-lg p-2"><p class="text-[9px] uppercase text-data-slate">FG%</p><p class="text-base font-bold" x-text="formatPct(result.player_b.per_game_averages.fg_pct)"></p></div>
                                                <div class="bg-white/5 rounded-lg p-2"><p class="text-[9px] uppercase text-data-slate">3P%</p><p class="text-base font-bold" x-text="formatPct(result.player_b.per_game_averages.three_pct)"></p></div>
                                                <div class="bg-white/5 rounded-lg p-2"><p class="text-[9px] uppercase text-data-slate">FT%</p><p class="text-base font-bold" x-text="formatPct(result.player_b.per_game_averages.ft_pct)"></p></div>
                                            </div>
                                        </div>
                                        <template x-if="result.player_b.strengths && result.player_b.strengths.length">
                                            <div class="mb-5"><h4 class="text-[10px] uppercase tracking-widest font-bold text-green-400 mb-3">Strengths</h4>
                                                <ul class="space-y-1.5"><template x-for="s in result.player_b.strengths" :key="s"><li class="flex items-start gap-2 text-xs text-data-slate"><span class="mt-1.5 size-1.5 rounded-full strength-dot shrink-0"></span><span x-text="s"></span></li></template></ul></div>
                                        </template>
                                        <template x-if="result.player_b.weaknesses && result.player_b.weaknesses.length">
                                            <div><h4 class="text-[10px] uppercase tracking-widest font-bold text-red-400 mb-3">Areas for Improvement</h4>
                                                <ul class="space-y-1.5"><template x-for="w in result.player_b.weaknesses" :key="w"><li class="flex items-start gap-2 text-xs text-data-slate"><span class="mt-1.5 size-1.5 rounded-full weakness-dot shrink-0"></span><span x-text="w"></span></li></template></ul></div>
                                        </template>
                                    </div>
                                    <template x-if="result.player_b.peak_season">
                                        <div class="border-t border-white/5 px-6 py-4">
                                            <p class="text-[9px] uppercase tracking-widest font-bold text-data-slate mb-2">Peak Season</p>
                                            <div class="flex items-center gap-4 text-sm"><span class="text-blue-400 font-bold" x-text="result.player_b.peak_season.year"></span><span x-text="result.player_b.peak_season.ppg + ' PPG'"></span><span x-text="result.player_b.peak_season.rpg + ' RPG'"></span><span x-text="result.player_b.peak_season.apg + ' APG'"></span></div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="!isNba">
                                <div class="profile-gradient-b border border-blue-500/20 rounded-2xl overflow-hidden">
                                    <div class="p-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="text-xl font-display italic font-black uppercase" x-text="result.player_b.team?.name || nameB"></h3>
                                            <span class="text-[10px] uppercase font-bold px-2 py-1 rounded bg-white/10 text-data-slate">UCL</span>
                                        </div>
                                        {{-- Archetype --}}
                                        <template x-if="result.player_b.archetype">
                                            <div class="archetype-badge rounded-xl p-4 mb-5">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="text-lg" x-text="result.player_b.archetype.icon"></span>
                                                    <span class="font-display font-bold text-sm uppercase tracking-wide text-amber-300" x-text="result.player_b.archetype.primary"></span>
                                                </div>
                                                <p class="text-xs text-data-slate leading-relaxed" x-text="result.player_b.archetype.description"></p>
                                            </div>
                                        </template>
                                        {{-- Overall Stats --}}
                                        <h4 class="text-[10px] uppercase tracking-widest font-bold text-data-slate mb-3">Overall Record</h4>
                                        <div class="grid grid-cols-4 gap-2 mb-3">
                                            <div class="bg-white/5 rounded-lg p-2 text-center"><p class="text-[9px] uppercase text-data-slate">W</p><p class="text-lg font-display font-black text-green-400" x-text="result.player_b.stats?.wins || '0'"></p></div>
                                            <div class="bg-white/5 rounded-lg p-2 text-center"><p class="text-[9px] uppercase text-data-slate">D</p><p class="text-lg font-display font-black text-amber-400" x-text="result.player_b.stats?.draws || '0'"></p></div>
                                            <div class="bg-white/5 rounded-lg p-2 text-center"><p class="text-[9px] uppercase text-data-slate">L</p><p class="text-lg font-display font-black text-red-400" x-text="result.player_b.stats?.losses || '0'"></p></div>
                                            <div class="bg-white/5 rounded-lg p-2 text-center"><p class="text-[9px] uppercase text-data-slate">Pts</p><p class="text-lg font-display font-black text-blue-400" x-text="result.player_b.stats?.points || '0'"></p></div>
                                        </div>
                                        <div class="grid grid-cols-3 gap-2 mb-5">
                                            <div class="bg-white/5 rounded-lg p-2 text-center"><p class="text-[9px] uppercase text-data-slate">GF</p><p class="text-sm font-bold" x-text="result.player_b.stats?.goals_for || '0'"></p></div>
                                            <div class="bg-white/5 rounded-lg p-2 text-center"><p class="text-[9px] uppercase text-data-slate">GA</p><p class="text-sm font-bold" x-text="result.player_b.stats?.goals_against || '0'"></p></div>
                                            <div class="bg-white/5 rounded-lg p-2 text-center"><p class="text-[9px] uppercase text-data-slate">GD</p><p class="text-sm font-bold" :class="result.player_b.stats?.goal_difference >= 0 ? 'text-green-400' : 'text-red-400'" x-text="(result.player_b.stats?.goal_difference ?? 0) >= 0 ? '+' + result.player_b.stats.goal_difference : result.player_b.stats.goal_difference"></p></div>
                                        </div>
                                        {{-- Stage Breakdown --}}
                                        <template x-if="result.player_b.stage_breakdown">
                                            <div>
                                                <h4 class="text-[10px] uppercase tracking-widest font-bold text-data-slate mb-3">By Stage</h4>
                                                <div class="grid grid-cols-2 gap-2 mb-5">
                                                    <div class="bg-white/5 rounded-lg p-3" x-show="result.player_b.stage_breakdown.group_phase"><p class="text-[9px] uppercase tracking-widest text-data-slate mb-1">Group Phase</p><p class="text-xs"><span class="text-green-400 font-bold" x-text="result.player_b.stage_breakdown.group_phase.wins || 0"></span><span class="text-data-slate">W </span><span class="text-amber-400 font-bold" x-text="result.player_b.stage_breakdown.group_phase.draws || 0"></span><span class="text-data-slate">D </span><span class="text-red-400 font-bold" x-text="result.player_b.stage_breakdown.group_phase.losses || 0"></span><span class="text-data-slate">L</span></p><p class="text-[10px] text-data-slate mt-1">GF <span class="text-white" x-text="result.player_b.stage_breakdown.group_phase.goals_for || 0"></span> GA <span class="text-white" x-text="result.player_b.stage_breakdown.group_phase.goals_against || 0"></span></p></div>
                                                    <div class="bg-white/5 rounded-lg p-3" x-show="result.player_b.stage_breakdown.knockout"><p class="text-[9px] uppercase tracking-widest text-data-slate mb-1">Knockout</p><p class="text-xs"><span class="text-green-400 font-bold" x-text="result.player_b.stage_breakdown.knockout.wins || 0"></span><span class="text-data-slate">W </span><span class="text-amber-400 font-bold" x-text="result.player_b.stage_breakdown.knockout.draws || 0"></span><span class="text-data-slate">D </span><span class="text-red-400 font-bold" x-text="result.player_b.stage_breakdown.knockout.losses || 0"></span><span class="text-data-slate">L</span></p><p class="text-[10px] text-data-slate mt-1">GF <span class="text-white" x-text="result.player_b.stage_breakdown.knockout.goals_for || 0"></span> GA <span class="text-white" x-text="result.player_b.stage_breakdown.knockout.goals_against || 0"></span></p></div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- CL Head-to-Head Matches --}}
                <template x-if="!isNba && result.head_to_head && result.head_to_head.matches && result.head_to_head.matches.length > 0">
                    <div class="bg-court-dark border border-white/10 rounded-2xl overflow-hidden">
                        <div class="p-6">
                            <h3 class="text-lg font-display italic font-black uppercase mb-4">Head-to-Head</h3>
                            <div class="flex items-center gap-4 mb-6 text-center">
                                <div class="flex-1">
                                    <p class="text-xl font-display font-black text-hoop-orange" x-text="result.head_to_head.team_a_wins"></p>
                                    <p class="text-[9px] uppercase tracking-widest text-data-slate" x-text="nameA"></p>
                                </div>
                                <div>
                                    <p class="text-2xl font-display font-black text-data-slate">-</p>
                                    <p class="text-[9px] uppercase tracking-widest text-data-slate">Draws: <span class="text-white" x-text="result.head_to_head.draws"></span></p>
                                </div>
                                <div class="flex-1">
                                    <p class="text-xl font-display font-black text-blue-400" x-text="result.head_to_head.team_b_wins"></p>
                                    <p class="text-[9px] uppercase tracking-widest text-data-slate" x-text="nameB"></p>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <template x-for="(m, i) in result.head_to_head.matches" :key="i">
                                    <div class="bg-white/[0.02] rounded-lg p-3 flex items-center justify-between">
                                        <div class="flex items-center gap-3 min-w-0 flex-1">
                                            <span class="text-[10px] text-data-slate w-8" x-text="m.season_id"></span>
                                            <span class="text-[10px] text-data-slate truncate w-24" x-text="m.stage"></span>
                                            <span class="text-sm font-bold text-right flex-1" :class="m.home_team === nameA ? 'text-hoop-orange' : ''" x-text="m.home_team"></span>
                                            <span class="text-sm font-bold text-data-slate mx-1" x-text="m.home_score + '-' + m.away_score"></span>
                                            <span class="text-sm font-bold flex-1" :class="m.away_team === nameA ? 'text-hoop-orange' : ''" x-text="m.away_team"></span>
                                        </div>
                                        <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded ml-2 shrink-0"
                                            :class="m.winner === 'Draw' ? 'bg-amber-500/10 text-amber-400' : (m.winner === nameA || m.winner === nameB ? 'bg-green-500/10 text-green-400' : 'bg-white/5 text-data-slate')"
                                            x-text="m.winner === 'Draw' ? 'Draw' : (m.winner === m.home_team ? 'Home' : 'Away')"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Head-to-Head Stat Comparison with Bars --}}
                <template x-if="result.comparison">
                    <div class="bg-court-dark border border-white/10 rounded-2xl overflow-hidden">
                        <div class="p-6">
                            <h3 class="text-lg font-display italic font-black uppercase mb-6">Stat-by-Stat Comparison</h3>
                            <template x-for="(stats, category) in result.comparison.categories" :key="category">
                                <div class="mb-6">
                                    <h4 class="text-[10px] uppercase tracking-widest font-bold text-data-slate mb-3" x-text="category"></h4>
                                    <div class="space-y-3">
                                        <template x-for="stat in stats" :key="stat.label">
                                            <template x-if="stat.a !== null && stat.b !== null">
                                                <div class="bg-white/[0.02] rounded-lg p-3">
                                                    <div class="flex justify-between items-center mb-1.5">
                                                        <span class="text-xs text-data-slate" x-text="stat.label"></span>
                                                        <span class="text-[10px] text-data-slate/60" x-text="stat.unit ? stat.unit : ''"></span>
                                                    </div>
                                                    <div class="grid grid-cols-[80px_1fr_40px_1fr_80px] gap-2 items-center">
                                                        <span class="text-sm font-bold text-hoop-orange text-right" x-text="stat.a"></span>
                                                        <div class="h-2 bg-white/10 rounded-full overflow-hidden">
                                                            <div class="h-full bg-hoop-orange rounded-full bar-fill" :style="'width: ' + barPct(stat.a, stat.b, 'left') + '%'"></div>
                                                        </div>
                                                        <span class="text-[10px] text-data-slate text-center font-mono">vs</span>
                                                        <div class="h-2 bg-white/10 rounded-full overflow-hidden">
                                                            <div class="h-full bg-blue-500 rounded-full bar-fill" :style="'width: ' + barPct(stat.b, stat.a, 'right') + '%'"></div>
                                                        </div>
                                                        <span class="text-sm font-bold text-blue-400" x-text="stat.b"></span>
                                                    </div>
                                                </div>
                                            </template>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Career Stats Table (NBA only) --}}
                <template x-if="isNba && (result.player_a.season_stats || result.player_b.season_stats)">
                    <div class="grid md:grid-cols-2 gap-6">
                        <template x-if="result.player_a.season_stats">
                            <div class="bg-court-dark border border-white/10 rounded-2xl overflow-hidden">
                                <div class="p-4 sm:p-6">
                                    <h3 class="text-sm font-display italic font-black uppercase mb-4" x-text="result.player_a.player.name + ' — Season Log'"></h3>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-[11px] border-separate border-spacing-y-1">
                                            <thead><tr class="text-data-slate font-bold text-[9px] uppercase tracking-wider"><th class="px-2 py-1">Year</th><th class="px-2 py-1">Team</th><th class="px-2 py-1">GP</th><th class="px-2 py-1">PPG</th><th class="px-2 py-1">RPG</th><th class="px-2 py-1">APG</th><th class="px-2 py-1">FG%</th></tr></thead>
                                            <tbody>
                                                <template x-for="(row, i) in result.player_a.season_stats" :key="i">
                                                    <tr class="bg-white/[0.02] hover:bg-white/5 transition-colors">
                                                        <td class="px-2 py-1.5 font-bold" x-text="row.year"></td>
                                                        <td class="px-2 py-1.5" x-text="row.team || '-'"></td>
                                                        <td class="px-2 py-1.5" x-text="row.gp"></td>
                                                        <td class="px-2 py-1.5 font-bold text-hoop-orange" x-text="row.ppg"></td>
                                                        <td class="px-2 py-1.5" x-text="row.rpg"></td>
                                                        <td class="px-2 py-1.5" x-text="row.apg"></td>
                                                        <td class="px-2 py-1.5" x-text="row.fg_pct !== null ? (row.fg_pct * 100).toFixed(1) + '%' : '-'"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template x-if="result.player_b.season_stats">
                            <div class="bg-court-dark border border-white/10 rounded-2xl overflow-hidden">
                                <div class="p-4 sm:p-6">
                                    <h3 class="text-sm font-display italic font-black uppercase mb-4" x-text="result.player_b.player.name + ' — Season Log'"></h3>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-[11px] border-separate border-spacing-y-1">
                                            <thead><tr class="text-data-slate font-bold text-[9px] uppercase tracking-wider"><th class="px-2 py-1">Year</th><th class="px-2 py-1">Team</th><th class="px-2 py-1">GP</th><th class="px-2 py-1">PPG</th><th class="px-2 py-1">RPG</th><th class="px-2 py-1">APG</th><th class="px-2 py-1">FG%</th></tr></thead>
                                            <tbody>
                                                <template x-for="(row, i) in result.player_b.season_stats" :key="i">
                                                    <tr class="bg-white/[0.02] hover:bg-white/5 transition-colors">
                                                        <td class="px-2 py-1.5 font-bold" x-text="row.year"></td>
                                                        <td class="px-2 py-1.5" x-text="row.team || '-'"></td>
                                                        <td class="px-2 py-1.5" x-text="row.gp"></td>
                                                        <td class="px-2 py-1.5 font-bold text-blue-400" x-text="row.ppg"></td>
                                                        <td class="px-2 py-1.5" x-text="row.rpg"></td>
                                                        <td class="px-2 py-1.5" x-text="row.apg"></td>
                                                        <td class="px-2 py-1.5" x-text="row.fg_pct !== null ? (row.fg_pct * 100).toFixed(1) + '%' : '-'"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </template>
    </main>
</div>
@endsection

@push('head')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('compare', () => ({
        queryA: '',
        queryB: '',
        selectedA: null,
        selectedB: null,
        resultsA: [],
        resultsB: [],
        showDropdownA: false,
        showDropdownB: false,
        loading: false,
        result: null,
        error: null,

        get entityLabel() {
            return Alpine.store('sport').current === 'nba' ? 'Player' : 'Team';
        },

        get sport() {
            return Alpine.store('sport').current;
        },

        get nameA() {
            if (!this.result) return '';
            const a = this.result.player_a;
            return a.player?.name || a.team?.name || '';
        },

        get nameB() {
            if (!this.result) return '';
            const b = this.result.player_b;
            return b.player?.name || b.team?.name || '';
        },

        get isNba() {
            return this.result?.sport === 'nba' || !this.result?.sport;
        },

        barPct(val, max, side) {
            if (!val || !max) return 50;
            const pct = (val / Math.max(val, max)) * 100;
            return Math.max(5, Math.min(pct, 100));
        },

        formatPct(val) {
            if (val === null || val === undefined) return 'N/A';
            return (val * 100).toFixed(1) + '%';
        },

        searchEndpoint(query) {
            return this.sport === 'nba'
                ? '/api/players/search?q=' + encodeURIComponent(query)
                : '/api/teams/search?q=' + encodeURIComponent(query) + '&sport=champions';
        },

        async searchA() {
            this.selectedA = null;
            if (this.queryA.length < 1) { this.resultsA = []; return; }
            const res = await fetch(this.searchEndpoint(this.queryA));
            this.resultsA = await res.json();
            this.showDropdownA = true;
        },

        async searchB() {
            this.selectedB = null;
            if (this.queryB.length < 1) { this.resultsB = []; return; }
            const res = await fetch(this.searchEndpoint(this.queryB));
            this.resultsB = await res.json();
            this.showDropdownB = true;
        },

        selectA(p) {
            this.selectedA = p;
            this.queryA = p.label;
            this.resultsA = [];
            this.showDropdownA = false;
        },

        selectB(p) {
            this.selectedB = p;
            this.queryB = p.label;
            this.resultsB = [];
            this.showDropdownB = false;
        },

        clearA() {
            this.selectedA = null;
            this.queryA = '';
            this.resultsA = [];
            this.result = null;
        },

        clearB() {
            this.selectedB = null;
            this.queryB = '';
            this.resultsB = [];
            this.result = null;
        },

        async runComparison() {
            if (!this.selectedA || !this.selectedB) return;
            this.loading = true;
            this.result = null;
            this.error = null;

            try {
                const res = await fetch('/api/compare', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        player_a_id: this.selectedA.id,
                        player_b_id: this.selectedB.id,
                        sport: this.sport,
                    }),
                });
                if (!res.ok) {
                    const text = await res.text();
                    throw new Error('API error ' + res.status + ': ' + text.slice(0, 200));
                }
                this.result = await res.json();
            } catch (err) {
                this.error = err.message;
            } finally {
                this.loading = false;
            }
        },
    }));
});
</script>
@endpush
