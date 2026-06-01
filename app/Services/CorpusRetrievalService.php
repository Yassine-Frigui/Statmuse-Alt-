<?php

namespace App\Services;

use App\Models\Championship;
use App\Models\CorpusEntry;
use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\Season;
use App\Models\Player;
use App\Models\PlayerSeasonStat;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CorpusRetrievalService
{
    public function retrieve(array $structuredQuery): Collection
    {
        $table = $structuredQuery['primary_table'] ?? 'players';

        $model = $this->resolveModel($table);
        if (!$model) {
            return collect();
        }

        $query = $model->query();

        if (($structuredQuery['select'] ?? null) && !in_array('*', $structuredQuery['select'])) {
            $query->select($structuredQuery['select']);
        }

        foreach ($structuredQuery['filters'] ?? [] as $filter) {
            $column = $filter['column'] ?? null;
            $operator = $filter['operator'] ?? '=';
            $value = $filter['value'] ?? null;

            if ($column) {
                $query->where($column, $operator, $value);
            }
        }

        if ($orderBy = $structuredQuery['order_by'] ?? null) {
            $query->orderBy($orderBy['column'], $orderBy['direction'] ?? 'desc');
        }

        if ($groupBy = $structuredQuery['group_by'] ?? null) {
            $query->groupBy($groupBy);
        }

        $limit = $structuredQuery['limit'] ?? 10;
        $query->limit($limit);

        return $query->get();
    }

    public function getRanking(string $metric, ?int $seasonId = null, int $limit = 10): Collection
    {
        return PlayerSeasonStat::query()
            ->select('player_id')
            ->selectRaw("SUM({$metric}) as total")
            ->when($seasonId, fn($q) => $q->where('season_id', $seasonId))
            ->groupBy('player_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->with('player')
            ->get();
    }

    public function getSingleGameScoringLeaders(?int $seasonYear = null, int $limit = 10, string $metric = 'points'): Collection
    {
        $metric = $this->normalizeStatMetric($metric);

        $query = GamePlayerStat::query()
            ->join('games', 'game_player_stats.game_id', '=', 'games.id')
            ->join('seasons', 'games.season_id', '=', 'seasons.id')
            ->join('players', 'game_player_stats.player_id', '=', 'players.id')
            ->leftJoin('teams', 'game_player_stats.team_id', '=', 'teams.id')
            ->select([
                'game_player_stats.id',
                'game_player_stats.game_id',
                'game_player_stats.player_id',
                'game_player_stats.team_id',
                'game_player_stats.points',
                'game_player_stats.rebounds',
                'game_player_stats.assists',
                'game_player_stats.steals',
                'game_player_stats.blocks',
                'games.date as game_date',
                'games.stage as game_stage',
                'seasons.year as season_year',
                'seasons.label as season_label',
                'players.first_name',
                'players.last_name',
                'players.position',
                'teams.name as team_name',
                'teams.abbreviation as team_abbreviation',
            ])
            ->orderByDesc('game_player_stats.' . $metric)
            ->orderBy('games.date', 'desc')
            ->orderBy('players.last_name')
            ->limit($limit);

        if ($seasonYear !== null) {
            $query->where('seasons.year', $seasonYear);
        }

        return $query->get();
    }

    private function normalizeStatMetric(string $metric): string
    {
        return in_array($metric, ['points', 'rebounds', 'assists', 'steals', 'blocks'], true)
            ? $metric
            : 'points';
    }

    public function latestSeasonYear(): ?int
    {
        return Season::max('year');
    }

    public function getPlayerInfo(string $name): Collection
    {
        return Player::where('first_name', 'LIKE', "%{$name}%")
            ->orWhere('last_name', 'LIKE', "%{$name}%")
            ->orWhere('first_name', 'LIKE', "%{$name}%")
            ->orWhere('last_name', 'LIKE', "%{$name}%")
            ->with(['seasonStats.team', 'seasonStats.season'])
            ->get();
    }

    public function getTeamInfo(string $name): Collection
    {
        return Team::where('name', 'LIKE', "%{$name}%")
            ->orWhere('city', 'LIKE', "%{$name}%")
            ->orWhere('abbreviation', $name)
            ->with(['championships.season'])
            ->get();
    }

    public function getChampionship(int $seasonYear): Collection
    {
        return Championship::whereHas('season', fn($q) => $q->where('year', $seasonYear))
            ->with(['championTeam', 'runnerUpTeam', 'mvpPlayer', 'season'])
            ->get();
    }

    public function searchCorpus(string $query): Collection
    {
        return CorpusEntry::where('content', 'LIKE', "%{$query}%")
            ->orWhere('title', 'LIKE', "%{$query}%")
            ->orWhere('category', $query)
            ->get();
    }

    private function resolveModel(string $table): ?Model
    {
        return match ($table) {
            'players' => new Player(),
            'teams' => new Team(),
            'seasons' => new \App\Models\Season(),
            'games' => new Game(),
            'game_player_stats' => new GamePlayerStat(),
            'championships' => new Championship(),
            'corpus_entries' => new CorpusEntry(),
            'player_season_stats' => new PlayerSeasonStat(),
            default => null,
        };
    }
}
