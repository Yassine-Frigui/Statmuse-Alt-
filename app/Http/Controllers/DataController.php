<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\Player;
use App\Models\PlayerSeasonStat;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;

class DataController extends Controller
{
    private function seasonTypeFilter(Builder $q, ?string $seasonType, ?int $seasonId): void
    {
        if (!$seasonType || $seasonType === 'all' || !$seasonId) return;

        $stages = Game::where('season_id', $seasonId)
            ->select('stage')
            ->distinct()
            ->pluck('stage');

        if ($seasonType === 'regular_season') {
            $q->where('stage', 'Regular Season');
        } elseif ($seasonType === 'playoffs') {
            $q->where('stage', '!=', 'Regular Season');
        }
    }

    public function ranking()
    {
        $seasonYear = request('season');
        $metric = request('metric', 'points');
        $limit = request('limit', 10);
        $season = Season::where('year', $seasonYear)->first();

        $query = PlayerSeasonStat::when($season, fn($q) => $q->where('season_id', $season->id))
            ->orderByDesc($metric)
            ->limit($limit)
            ->with('player:id,first_name,last_name,nba_api_id');
        $data = $query->get();

        return response()->json($data);
    }

    public function index()
    {
        $seasons = Season::where('year', '>=', 2015)->orderBy('year', 'desc')->get();
        $selectedSeason = request('season', $seasons->first()?->year);
        $seasonType = request('season_type', 'all');

        $season = Season::where('year', $selectedSeason)->first();
        $teams = Team::whereNotNull('nba_api_id')->orderBy('name')->get();
        $selectedTeam = request('team');

        $gamesQuery = Game::where('season_id', $season?->id)
            ->when($selectedTeam, fn($q) => $q->where(fn($q) => $q
                ->where('home_team_id', $selectedTeam)
                ->orWhere('away_team_id', $selectedTeam)
            ))
            ->whereHas('gamePlayerStats', fn($q) => $q->where('points', '>', 0))
            ->with('homeTeam', 'awayTeam');

        $this->seasonTypeFilter($gamesQuery, $seasonType, $season?->id);

        $games = $gamesQuery->orderByDesc('date')->paginate(50);

        $selectedGameId = request('game');
        $selectedGame = null;
        $gameStats = collect();

        $gamePtsTotals = GamePlayerStat::whereIn('game_id', $games->pluck('id'))
            ->selectRaw('game_id, SUM(points) as total_pts')
            ->groupBy('game_id')
            ->pluck('total_pts', 'game_id');

        if ($selectedGameId) {
            $selectedGame = Game::with('homeTeam', 'awayTeam')->find($selectedGameId);
            if ($selectedGame) {
                $gameStats = GamePlayerStat::where('game_id', $selectedGame->id)
                    ->with('player:id,first_name,last_name,nba_api_id', 'team:id,full_name,abbreviation')
                    ->orderByDesc('points')
                    ->get();
            }
        }

        $seasonStatsQuery = PlayerSeasonStat::where('season_id', $season?->id)
            ->with('player:id,first_name,last_name');
        $seasonStats = $seasonStatsQuery->orderByDesc('points')->limit(20)->get();

        $seasonSummary = [];
        foreach ($seasons as $s) {
            $gQuery = Game::where('season_id', $s->id);
            $total = $gQuery->count();
            $withStats = (clone $gQuery)->whereHas('gamePlayerStats', fn($q) => $q->where('points', '>', 0))->count();
            $rs = (clone $gQuery)->where('stage', 'Regular Season')->count();
            $po = (clone $gQuery)->where('stage', '!=', 'Regular Season')->count();
            $totalPts = GamePlayerStat::whereHas('game', fn($q) => $q->where('season_id', $s->id))->sum('points');
            $gps = GamePlayerStat::whereHas('game', fn($q) => $q->where('season_id', $s->id))->count();
            $seasonSummary[$s->year] = [
                'total' => $total,
                'with_stats' => $withStats,
                'pct' => $total > 0 ? round($withStats / $total * 100, 1) : 0,
                'total_pts' => $totalPts,
                'players' => PlayerSeasonStat::where('season_id', $s->id)->count(),
                'games' => $gps,
                'rs' => $rs,
                'po' => $po,
            ];
        }

        $seasonTypeLabels = ['all' => 'All Games', 'regular_season' => 'Regular Season', 'playoffs' => 'Playoffs'];

        return view('data.index', compact(
            'seasons', 'selectedSeason', 'season',
            'teams', 'selectedTeam', 'selectedGameId', 'seasonType',
            'games', 'selectedGame', 'gameStats',
            'seasonStats', 'seasonSummary',
            'gamePtsTotals', 'seasonTypeLabels'
        ));
    }
}
