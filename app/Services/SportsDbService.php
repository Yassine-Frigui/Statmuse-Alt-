<?php

namespace App\Services;

use App\Models\ClMatch;
use App\Models\ClSeason;
use App\Models\ClTeam;
use App\Services\Clients\SportsDbClient;
use Illuminate\Support\Facades\Log;

class SportsDbService
{
    private SportsDbClient $client;

    private array $roundLabels = [
        1 => 'Group Stage',
        2 => 'Group Stage',
        3 => 'Group Stage',
        4 => 'Group Stage',
        5 => 'Group Stage',
        6 => 'Group Stage',
        7 => 'Round of 16',
        8 => 'Quarter-finals',
        9 => 'Semi-finals',
        200 => 'Final',
        400 => 'Qualifying',
    ];

    public function __construct(SportsDbClient $client)
    {
        $this->client = $client;
    }

    public function getAvailableSeasons(): array
    {
        $info = $this->client->getLeagueInfo();
        $league = $info['leagues'][0] ?? [];

        if (empty($league['strSeason'])) {
            return [];
        }

        return explode(',', $league['strSeason']);
    }

    public function ingestSeason(string $seasonLabel): array
    {
        $seasonLabel = $this->normalizeSeason($seasonLabel);
        $seasonYear = (int) substr($seasonLabel, 0, 4);


        $stats = [
            'season' => $seasonLabel,
            'matches_upserted' => 0,
            'teams_upserted' => 0,
            'rounds_found' => 0,
            'rounds_empty' => 0,
            'errors' => 0,
        ];

        $season = ClSeason::updateOrCreate(
            ['id' => $seasonYear],
            [
                'name' => $seasonLabel,
                'start_date' => "{$seasonYear}-07-01",
                'end_date' => ($seasonYear + 1) . '-06-30',
                'current_matchday' => null,
                'winner_team_id' => null,
            ]
        );

        $rounds = $this->discoverRounds($seasonLabel);

        foreach ($rounds as $round) {
            try {
                $data = $this->client->getEventsByRound($round, $seasonLabel);
                $events = $data['events'] ?? [];

                if (empty($events)) {
                    $stats['rounds_empty']++;
                    continue;
                }

                $stats['rounds_found']++;

                foreach ($events as $e) {
                    $this->upsertMatch($e, $season, $round, $stats);
                }
            } catch (\RuntimeException $e) {
                $stats['errors']++;
                Log::warning("Skipping round {$round} for {$seasonLabel}: {$e->getMessage()}");
                throw $e;
            }
        }

        return $stats;
    }

    private function discoverRounds(string $season): array
    {
        $rounds = range(1, 6);

        $extraRounds = [7, 8, 9, 200, 400];
        foreach ($extraRounds as $r) {
            try {
                $data = $this->client->getEventsByRound($r, $season);
                if (!empty($data['events'])) {
                    $rounds[] = $r;
                }
            } catch (\RuntimeException) {
                return $rounds;
            }
        }

        return $rounds;
    }

    private function upsertMatch(array $e, ClSeason $season, int $round, array &$stats): void
    {
        $homeTeamId = $e['idHomeTeam'] ?? null;
        $awayTeamId = $e['idAwayTeam'] ?? null;

        if (!$homeTeamId || !$awayTeamId) return;

        $this->ensureTeam($e['strHomeTeam'] ?? '', (int) $homeTeamId, $e['strHomeTeamBadge'] ?? null, $stats);
        $this->ensureTeam($e['strAwayTeam'] ?? '', (int) $awayTeamId, $e['strAwayTeamBadge'] ?? null, $stats);

        $matchId = (int) ($e['idEvent'] ?? 0);
        if (!$matchId) return;

        $utcDate = null;
        if (!empty($e['dateEvent'])) {
            $time = $e['strTime'] ?? '';
            $time = preg_replace('/[^0-9:]/', '', $time);
            $parts = explode(':', $time);
            if (count($parts) > 3) array_pop($parts);
            $time = implode(':', $parts);
            $utcDate = $e['dateEvent'] . ($time ? ' ' . $time : ' 00:00:00');
        }

        ClMatch::updateOrCreate(
            ['id' => $matchId],
            [
                'season_id' => $season->id,
                'utc_date' => $utcDate,
                'status' => match ($e['strStatus'] ?? '') {
                    'FT' => 'FINISHED',
                    'SCHEDULED', '' => 'SCHEDULED',
                    default => $e['strStatus'],
                },
                'matchday' => $this->mapMatchday($round),
                'stage' => $this->roundLabels[$round] ?? null,
                'group_name' => $e['strGroup'] ?? null,
                'home_team_id' => (int) $homeTeamId,
                'away_team_id' => (int) $awayTeamId,
                'home_score' => $e['intHomeScore'] !== '' ? (int) $e['intHomeScore'] : null,
                'away_score' => $e['intAwayScore'] !== '' ? (int) $e['intAwayScore'] : null,
                'winner' => null,
                'duration' => 'REGULAR',
            ]
        );

        $stats['matches_upserted']++;
    }

    private function mapMatchday(int $round): int
    {
        if ($round >= 1 && $round <= 6) return $round;
        if ($round === 400) return 0;
        return $round;
    }

    private function ensureTeam(string $name, int $id, ?string $badge, array &$stats): void
    {
        ClTeam::withoutEvents(function () use ($name, $id, $badge, &$stats) {
            if (!$name) return;
            $team = ClTeam::find($id);
            if (!$team) {
                ClTeam::create([
                    'id' => $id,
                    'name' => $name,
                    'crest_url' => $badge,
                ]);
                $stats['teams_upserted']++;
            }
        });
    }

    private function normalizeSeason(string $label): string
    {
        if (preg_match('/^\d{4}$/', $label)) {
            return $label . '-' . ($label + 1);
        }

        if (preg_match('/^\d{4}-\d{2}$/', $label)) {
            $year = (int) substr($label, 0, 4);
            return $year . '-' . ($year + 1);
        }

        if (preg_match('/^\d{4}-\d{4}$/', $label)) {
            return $label;
        }

        return $label;
    }
}
