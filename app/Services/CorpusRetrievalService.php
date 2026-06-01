<?php

namespace App\Services;

use App\Models\Championship;
use App\Models\CorpusEntry;
use App\Models\Game;
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
            'championships' => new Championship(),
            'corpus_entries' => new CorpusEntry(),
            'player_season_stats' => new PlayerSeasonStat(),
            default => null,
        };
    }
}
