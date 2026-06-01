<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NBA Data Explorer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; }
        .stat-card { @apply bg-white rounded-lg shadow p-4 border border-gray-200; }
        .stat-value { @apply text-2xl font-bold text-gray-900; }
        .stat-label { @apply text-sm text-gray-500 uppercase tracking-wide; }
        .table-header { @apply px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50; }
        .table-cell { @apply px-3 py-2 text-sm text-gray-900 whitespace-nowrap; }
        .table-cell-num { @apply px-3 py-2 text-sm text-gray-900 whitespace-nowrap text-right font-mono; }
        .positive { @apply text-green-600; }
    </style>
</head>
<body class="bg-gray-50">
    <header class="bg-indigo-700 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">NBA Query Engine</h1>
                <p class="text-indigo-200 text-sm">Data Explorer — {{ number_format($games->total()) }} games with boxscores</p>
            </div>
            <div class="text-right text-indigo-200 text-sm">
                {{ number_format(App\Models\GamePlayerStat::count()) }} player-game entries
                <br>{{ number_format(App\Models\Player::count()) }} players
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Filters --}}
        <form method="GET" class="bg-white rounded-lg shadow p-4 mb-6 border border-gray-200 flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Season</label>
                <select name="season" onchange="this.form.submit()" class="rounded border-gray-300 text-sm py-1.5 px-3">
                    @foreach($seasons as $s)
                        <option value="{{ $s->year }}" {{ $s->year == $selectedSeason ? 'selected' : '' }}>
                            {{ $s->year }}-{{ substr($s->year + 1, -2) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Type</label>
                <select name="season_type" onchange="this.form.submit()" class="rounded border-gray-300 text-sm py-1.5 px-3">
                    @foreach($seasonTypeLabels as $val => $label)
                        <option value="{{ $val }}" {{ $val == $seasonType ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Team</label>
                <select name="team" onchange="this.form.submit()" class="rounded border-gray-300 text-sm py-1.5 px-3">
                    <option value="">All Teams</option>
                    @foreach($teams as $t)
                        <option value="{{ $t->id }}" {{ $t->id == $selectedTeam ? 'selected' : '' }}>
                            {{ $t->abbreviation }} — {{ $t->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="bg-indigo-600 text-white px-4 py-1.5 rounded text-sm hover:bg-indigo-700">Go</button>
            </div>
        </form>

        {{-- Season Summary --}}
        <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden mb-6">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Season Coverage</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead>
                        <tr>
                            <th class="table-header">Season</th>
                            <th class="table-header">Total</th>
                            <th class="table-header">RS</th>
                            <th class="table-header">PO</th>
                            <th class="table-header">W/ Stats</th>
                            <th class="table-header">%</th>
                            <th class="table-header">Total PTS</th>
                            <th class="table-header">Players</th>
                            <th class="table-header">GPS Rows</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($seasonSummary as $year => $summary)
                            <tr class="{{ $year == $selectedSeason ? 'bg-indigo-50' : '' }}">
                                <td class="table-cell font-medium">{{ $year }}-{{ substr($year + 1, -2) }}</td>
                                <td class="table-cell-num">{{ $summary['total'] }}</td>
                                <td class="table-cell-num text-green-600">{{ $summary['rs'] }}</td>
                                <td class="table-cell-num text-orange-600">{{ $summary['po'] }}</td>
                                <td class="table-cell-num">{{ $summary['with_stats'] }}</td>
                                <td class="table-cell-num {{ $summary['pct'] >= 90 ? 'text-green-600' : ($summary['pct'] >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                                    {{ $summary['pct'] }}%
                                </td>
                                <td class="table-cell-num">{{ number_format($summary['total_pts']) }}</td>
                                <td class="table-cell-num">{{ $summary['players'] }}</td>
                                <td class="table-cell-num">{{ number_format($summary['games']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            {{-- Season Top Scorers --}}
            <div class="lg:col-span-1 bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Top Scorers — {{ $selectedSeason }}</h2>
                </div>
                <div class="overflow-y-auto max-h-96">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="sticky top-0 bg-gray-50">
                            <tr>
                                <th class="table-header">#</th>
                                <th class="table-header">Player</th>
                                <th class="table-header text-right">PTS</th>
                                <th class="table-header text-right">GP</th>
                                <th class="table-header text-right">PPG</th>
                                <th class="table-header text-right">REB</th>
                                <th class="table-header text-right">AST</th>
                                <th class="table-header text-right">STL</th>
                                <th class="table-header text-right">BLK</th>
                                <th class="table-header text-right">FG%</th>
                                <th class="table-header text-right">3P%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($seasonStats as $i => $stat)
                                <tr>
                                    <td class="table-cell-num text-gray-400">{{ $i + 1 }}</td>
                                    <td class="table-cell max-w-32 truncate" title="{{ $stat->player?->full_name ?? "ID:{$stat->player_id}" }}">
                                        {{ $stat->player?->full_name ?? "ID:{$stat->player_id}" }}
                                    </td>
                                    <td class="table-cell-num font-medium">{{ number_format($stat->points) }}</td>
                                    <td class="table-cell-num">{{ $stat->games_played }}</td>
                                    <td class="table-cell-num">{{ $stat->games_played > 0 ? number_format($stat->points / $stat->games_played, 1) : '-' }}</td>
                                    <td class="table-cell-num">{{ number_format($stat->rebounds) }}</td>
                                    <td class="table-cell-num">{{ number_format($stat->assists) }}</td>
                                    <td class="table-cell-num">{{ number_format($stat->steals) }}</td>
                                    <td class="table-cell-num">{{ number_format($stat->blocks) }}</td>
                                    <td class="table-cell-num">{{ $stat->fg_pct !== null ? number_format($stat->fg_pct * 100, 1) : '-' }}</td>
                                    <td class="table-cell-num">{{ $stat->three_pct !== null ? number_format($stat->three_pct * 100, 1) : '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="table-cell text-gray-400 text-center py-4">No data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Games List --}}
            <div class="lg:col-span-2 bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900">Games</h2>
                    <span class="text-sm text-gray-500">{{ $games->firstItem() }}-{{ $games->lastItem() }} of {{ $games->total() }}</span>
                </div>
                <div class="overflow-y-auto max-h-96">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="sticky top-0 bg-gray-50">
                            <tr>
                                <th class="table-header">Date</th>
                                <th class="table-header">Matchup</th>
                                <th class="table-header text-right">Score</th>
                                <th class="table-header text-right">PTS</th>
                                <th class="table-header"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($games as $g)
                                @php
                                    $pts = $gamePtsTotals[$g->id] ?? 0;
                                    $actual = $g->home_score + $g->away_score;
                                    $match = $pts === $actual;
                                @endphp
                                <tr class="hover:bg-gray-50 {{ $g->id == $selectedGameId ? 'bg-indigo-50' : '' }}">
                                    <td class="table-cell">{{ \Carbon\Carbon::parse($g->date)->format('M j, Y') }}</td>
                                    <td class="table-cell">
                                        <span class="font-medium">{{ $g->homeTeam?->abbreviation ?? '?' }}</span>
                                        vs
                                        <span class="font-medium">{{ $g->awayTeam?->abbreviation ?? '?' }}</span>
                                    </td>
                                    <td class="table-cell-num font-medium">{{ $g->home_score }}-{{ $g->away_score }}</td>
                                    <td class="table-cell-num {{ $match ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $pts }}
                                        @if(!$match)
                                            <span class="text-red-500">({{ $actual }})</span>
                                        @endif
                                    </td>
                                    <td class="table-cell">
                                        <a href="?season={{ $selectedSeason }}&season_type={{ $seasonType }}&game={{ $g->id }}&team={{ $selectedTeam }}"
                                           class="text-indigo-600 hover:text-indigo-800 font-medium">
                                            Stats →
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="table-cell text-gray-400 text-center py-4">No games found</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($games->hasPages())
                    <div class="px-4 py-2 border-t border-gray-200">
                        {{ $games->appends(request()->query())->links() }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Game Detail --}}
        @if($selectedGame)
            <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden mb-6">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900">
                        Game Detail — {{ \Carbon\Carbon::parse($selectedGame->date)->format('M j, Y') }}
                        <span class="text-gray-500 font-normal">
                            {{ $selectedGame->homeTeam?->abbreviation }} {{ $selectedGame->home_score }}
                            vs {{ $selectedGame->awayTeam?->abbreviation }} {{ $selectedGame->away_score }}
                        </span>
                    </h2>
                    <span class="text-sm text-gray-500">{{ $gameStats->count() }} players</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead>
                            <tr>
                                <th class="table-header">Player</th>
                                <th class="table-header text-right">PTS</th>
                                <th class="table-header text-right">FGM</th>
                                <th class="table-header text-right">FGA</th>
                                <th class="table-header text-right">FG%</th>
                                <th class="table-header text-right">3PM</th>
                                <th class="table-header text-right">3PA</th>
                                <th class="table-header text-right">FTM</th>
                                <th class="table-header text-right">FTA</th>
                                <th class="table-header text-right">REB</th>
                                <th class="table-header text-right">AST</th>
                                <th class="table-header text-right">STL</th>
                                <th class="table-header text-right">BLK</th>
                                <th class="table-header text-right">TO</th>
                                <th class="table-header text-right">PF</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @php
                                $totals = (object)[
                                    'pts' => 0, 'fgm' => 0, 'fga' => 0,
                                    'fg3m' => 0, 'fg3a' => 0,
                                    'ftm' => 0, 'fta' => 0,
                                    'oreb' => 0, 'dreb' => 0,
                                    'ast' => 0, 'stl' => 0, 'blk' => 0,
                                    'to' => 0, 'pf' => 0,
                                ];
                            @endphp
                            @foreach($gameStats as $stat)
                                @php
                                    $totals->pts += $stat->points;
                                    $totals->fgm += $stat->fgm;
                                    $totals->fga += $stat->fga;
                                    $totals->fg3m += $stat->fg3m;
                                    $totals->fg3a += $stat->fg3a;
                                    $totals->ftm += $stat->ftm;
                                    $totals->fta += $stat->fta;
                                    $totals->oreb += $stat->offensive_rebounds;
                                    $totals->dreb += $stat->defensive_rebounds;
                                    $totals->ast += $stat->assists;
                                    $totals->stl += $stat->steals;
                                    $totals->blk += $stat->blocks;
                                    $totals->to += $stat->turnovers;
                                    $totals->pf += $stat->personal_fouls;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="table-cell font-medium max-w-40 truncate">
                                        <span class="text-gray-400 text-2xs">{{ $stat->team?->abbreviation }}</span>
                                        {{ $stat->player?->full_name ?? "Unknown (#{$stat->player_id})" }}
                                    </td>
                                    <td class="table-cell-num font-medium">{{ $stat->points }}</td>
                                    <td class="table-cell-num">{{ $stat->fgm }}</td>
                                    <td class="table-cell-num">{{ $stat->fga }}</td>
                                    <td class="table-cell-num">{{ $stat->fga > 0 ? number_format($stat->fgm / $stat->fga * 100, 1) : '-' }}</td>
                                    <td class="table-cell-num">{{ $stat->fg3m }}</td>
                                    <td class="table-cell-num">{{ $stat->fg3a }}</td>
                                    <td class="table-cell-num">{{ $stat->ftm }}</td>
                                    <td class="table-cell-num">{{ $stat->fta }}</td>
                                    <td class="table-cell-num">{{ $stat->offensive_rebounds + $stat->defensive_rebounds }}</td>
                                    <td class="table-cell-num">{{ $stat->assists }}</td>
                                    <td class="table-cell-num">{{ $stat->steals }}</td>
                                    <td class="table-cell-num">{{ $stat->blocks }}</td>
                                    <td class="table-cell-num">{{ $stat->turnovers }}</td>
                                    <td class="table-cell-num">{{ $stat->personal_fouls }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 font-semibold">
                            <tr>
                                <td class="table-cell">TOTALS</td>
                                <td class="table-cell-num">{{ $totals->pts }}</td>
                                <td class="table-cell-num">{{ $totals->fgm }}</td>
                                <td class="table-cell-num">{{ $totals->fga }}</td>
                                <td class="table-cell-num">{{ $totals->fga > 0 ? number_format($totals->fgm / $totals->fga * 100, 1) : '-' }}</td>
                                <td class="table-cell-num">{{ $totals->fg3m }}</td>
                                <td class="table-cell-num">{{ $totals->fg3a }}</td>
                                <td class="table-cell-num">{{ $totals->ftm }}</td>
                                <td class="table-cell-num">{{ $totals->fta }}</td>
                                <td class="table-cell-num">{{ $totals->oreb + $totals->dreb }}</td>
                                <td class="table-cell-num">{{ $totals->ast }}</td>
                                <td class="table-cell-num">{{ $totals->stl }}</td>
                                <td class="table-cell-num">{{ $totals->blk }}</td>
                                <td class="table-cell-num">{{ $totals->to }}</td>
                                <td class="table-cell-num">{{ $totals->pf }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="px-4 py-2 bg-gray-50 border-t border-gray-200 text-xs text-gray-500">
                    PBP reconstructed: {{ $totals->pts }} pts vs actual {{ $selectedGame->home_score + $selectedGame->away_score }} pts
                    @if($totals->pts === $selectedGame->home_score + $selectedGame->away_score)
                        <span class="text-green-600 font-medium">✓ MATCH</span>
                    @else
                        <span class="text-red-600 font-medium">✗ MISMATCH (diff: {{ ($selectedGame->home_score + $selectedGame->away_score) - $totals->pts }})</span>
                    @endif
                </div>
            </div>
        @endif

        {{-- Player Lookup --}}
        <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Player Lookup</h2>
            </div>
            <div class="p-4">
                <form method="GET" class="flex gap-2">
                    <input type="hidden" name="season" value="{{ $selectedSeason }}">
                    <input type="hidden" name="season_type" value="{{ $seasonType }}">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name..."
                           class="flex-1 rounded border-gray-300 text-sm px-3 py-1.5 border">
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-1.5 rounded text-sm hover:bg-indigo-700">Search</button>
                </form>

                @if($q = request('q'))
                    @php
                        $results = App\Models\Player::where('first_name', 'like', "%{$q}%")
                            ->orWhere('last_name', 'like', "%{$q}%")
                            ->orderBy('last_name')
                            ->limit(30)
                            ->get();
                    @endphp
                    @if($results->isNotEmpty())
                        <div class="mt-3 max-h-64 overflow-y-auto border rounded divide-y text-sm">
                            @foreach($results as $p)
                                @php
                                    $ptsSum = App\Models\PlayerSeasonStat::where('player_id', $p->id)->sum('points');
                                    $games = App\Models\PlayerSeasonStat::where('player_id', $p->id)->sum('games_played');
                                @endphp
                                <div class="px-3 py-2 hover:bg-gray-50 flex justify-between">
                                    <span class="font-medium">{{ $p->full_name }}</span>
                                    <span class="text-gray-500 text-xs">{{ number_format($ptsSum) }} PTS / {{ $games }} GP</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-2 text-sm text-gray-500">No players found.</p>
                    @endif
                @endif
            </div>
        </div>
    </main>
</body>
</html>
