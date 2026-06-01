<?php

namespace App\Console\Commands;

use App\Models\Season;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NbaAggregateSeasonStats extends Command
{
    protected $signature = 'nba:aggregate-stats
        {--season= : Season year to aggregate}
        {--all : Aggregate all seasons}';

    protected $description = 'Rebuild player_season_stats from game_player_stats';

    public function handle(): int
    {
        $seasons = $this->resolveSeasons();

        if (empty($seasons)) {
            $this->error('No seasons found.');
            return 1;
        }

        foreach ($seasons as $year) {
            $this->info("Aggregating season {$year}...");
            $this->aggregateSeason($year);
        }

        $this->info('Done.');

        return 0;
    }

    private function aggregateSeason(int $seasonYear): void
    {
        $season = Season::where('year', $seasonYear)->first();
        if (!$season) {
            $this->warn("Season {$seasonYear} not found.");
            return;
        }

        DB::table('player_season_stats')->where('season_id', $season->id)->delete();

        $rows = DB::table('game_player_stats')
            ->join('games', 'game_player_stats.game_id', '=', 'games.id')
            ->where('games.season_id', $season->id)
            ->select(
                'game_player_stats.player_id',
                'game_player_stats.team_id',
                DB::raw('COUNT(*) as games_played'),
                DB::raw('SUM(game_player_stats.points) as points'),
                DB::raw('SUM(game_player_stats.rebounds) as rebounds'),
                DB::raw('SUM(game_player_stats.assists) as assists'),
                DB::raw('SUM(game_player_stats.steals) as steals'),
                DB::raw('SUM(game_player_stats.blocks) as blocks'),
                DB::raw('SUM(game_player_stats.fgm) as fgm'),
                DB::raw('SUM(game_player_stats.fga) as fga'),
                DB::raw('SUM(game_player_stats.fg3m) as fg3m'),
                DB::raw('SUM(game_player_stats.fg3a) as fg3a'),
                DB::raw('SUM(game_player_stats.ftm) as ftm'),
                DB::raw('SUM(game_player_stats.fta) as fta'),
                DB::raw('SUM(game_player_stats.minutes) as minutes'),
                DB::raw('SUM(game_player_stats.turnovers) as turnovers'),
                DB::raw('SUM(game_player_stats.personal_fouls) as personal_fouls'),
            )
            ->groupBy('game_player_stats.player_id', 'game_player_stats.team_id')
            ->get();

        $insertData = [];
        foreach ($rows as $row) {
            $insertData[] = [
                'player_id' => $row->player_id,
                'team_id' => $row->team_id,
                'season_id' => $season->id,
                'games_played' => $row->games_played,
                'points' => $row->points,
                'rebounds' => $row->rebounds,
                'assists' => $row->assists,
                'steals' => $row->steals,
                'blocks' => $row->blocks,
                'minutes' => $row->minutes ?? 0,
                'fg_pct' => $row->fga > 0 ? round($row->fgm / $row->fga, 3) : null,
                'three_pct' => $row->fg3a > 0 ? round($row->fg3m / $row->fg3a, 3) : null,
                'ft_pct' => $row->fta > 0 ? round($row->ftm / $row->fta, 3) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('player_season_stats')->insert($insertData);

        $this->line("  Inserted " . count($insertData) . " season stat rows.");
    }

    private function resolveSeasons(): array
    {
        if ($this->option('all')) {
            return [2015, 2016, 2017, 2018, 2019, 2020, 2021, 2022, 2023, 2024];
        }

        if ($this->option('season')) {
            return [(int)$this->option('season')];
        }

        $years = Season::whereHas('games')->orderBy('year')->pluck('year')->toArray();
        return array_filter($years, fn($y) => $y >= 2015);
    }
}
