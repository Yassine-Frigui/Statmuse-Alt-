<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TeamProfileService
{
    public function generateProfile(int $teamId): array
    {
        $team = DB::table('cl_teams')->find($teamId);
        if (!$team) {
            throw new \InvalidArgumentException("Team not found: {$teamId}");
        }

        return [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
            'stats' => $this->computeOverallStats($teamId),
            'stage_breakdown' => $this->computeStageBreakdown($teamId),
            'home_away' => $this->computeHomeAway($teamId),
            'archetype' => $this->determineArchetype($teamId),
        ];
    }

    public function headToHead(int $teamAId, int $teamBId): array
    {
        $matches = DB::select("
            SELECT m.season_id, m.stage, m.matchday, m.status,
                   home.name AS home_team, m.home_score,
                   away.name AS away_team, m.away_score,
                   CASE WHEN m.home_score > m.away_score THEN home.name
                        WHEN m.away_score > m.home_score THEN away.name
                        ELSE 'Draw' END AS winner
            FROM cl_matches m
            JOIN cl_teams home ON m.home_team_id = home.id
            JOIN cl_teams away ON m.away_team_id = away.id
            WHERE ((m.home_team_id = ? AND m.away_team_id = ?)
                   OR (m.home_team_id = ? AND m.away_team_id = ?))
              AND m.status IN ('FINISHED', 'AET', 'PEN')
            ORDER BY m.season_id, m.matchday
        ", [$teamAId, $teamBId, $teamBId, $teamAId]);

        $teamAWins = 0;
        $teamBWins = 0;
        $draws = 0;
        $teamAGoals = 0;
        $teamBGoals = 0;

        foreach ($matches as $m) {
            if ($m->home_team === $m->winner) {
                if ($m->home_team_id == $teamAId || $m->away_team_id == $teamAId) {
                    $teamAWins++;
                } else {
                    $teamBWins++;
                }
            } elseif ($m->away_team === $m->winner) {
                if ($m->home_team_id == $teamAId || $m->away_team_id == $teamAId) {
                    $teamAWins++;
                } else {
                    $teamBWins++;
                }
            } else {
                $draws++;
            }
        }

        return [
            'matches' => $matches,
            'team_a_wins' => $teamAWins,
            'team_b_wins' => $teamBWins,
            'draws' => $draws,
        ];
    }

    private function computeOverallStats(int $teamId): array
    {
        $rows = DB::select("
            SELECT
                COUNT(*) AS matches_played,
                SUM(CASE
                    WHEN m.home_team_id = ? AND m.home_score > m.away_score THEN 1
                    WHEN m.away_team_id = ? AND m.away_score > m.home_score THEN 1
                    ELSE 0
                END) AS wins,
                SUM(CASE
                    WHEN m.home_team_id = ? AND m.home_score = m.away_score THEN 1
                    WHEN m.away_team_id = ? AND m.away_score = m.home_score THEN 1
                    ELSE 0
                END) AS draws,
                SUM(CASE
                    WHEN m.home_team_id = ? AND m.home_score < m.away_score THEN 1
                    WHEN m.away_team_id = ? AND m.away_score < m.home_score THEN 1
                    ELSE 0
                END) AS losses,
                SUM(CASE WHEN m.home_team_id = ? THEN m.home_score ELSE 0 END +
                    CASE WHEN m.away_team_id = ? THEN m.away_score ELSE 0 END) AS goals_for,
                SUM(CASE WHEN m.home_team_id = ? THEN m.away_score ELSE 0 END +
                    CASE WHEN m.away_team_id = ? THEN m.home_score ELSE 0 END) AS goals_against,
                ROUND(AVG(CASE WHEN m.home_team_id = ? THEN m.home_score ELSE m.away_score END), 2) AS avg_goals_for,
                ROUND(AVG(CASE WHEN m.home_team_id = ? THEN m.away_score ELSE m.home_score END), 2) AS avg_goals_against
            FROM cl_matches m
            WHERE (m.home_team_id = ? OR m.away_team_id = ?)
              AND m.status IN ('FINISHED', 'AET', 'PEN')
        ", array_fill(0, 14, $teamId));

        $stats = (array) ($rows[0] ?? []);
        $stats['goal_difference'] = ($stats['goals_for'] ?? 0) - ($stats['goals_against'] ?? 0);
        $stats['points'] = ($stats['wins'] ?? 0) * 3 + ($stats['draws'] ?? 0);
        $stats['win_pct'] = $stats['matches_played'] > 0
            ? round(($stats['wins'] / $stats['matches_played']) * 100, 1)
            : 0;

        return $stats;
    }

    private function computeStageBreakdown(int $teamId): array
    {
        $rows = DB::select("
            SELECT
                CASE WHEN m.stage IN ('Qualifying', 'Group Stage') THEN 'group_phase'
                     ELSE 'knockout' END AS phase,
                COUNT(*) AS matches_played,
                SUM(CASE
                    WHEN m.home_team_id = ? AND m.home_score > m.away_score THEN 1
                    WHEN m.away_team_id = ? AND m.away_score > m.home_score THEN 1
                    ELSE 0
                END) AS wins,
                SUM(CASE
                    WHEN m.home_team_id = ? AND m.home_score = m.away_score THEN 1
                    WHEN m.away_team_id = ? AND m.away_score = m.home_score THEN 1
                    ELSE 0
                END) AS draws,
                SUM(CASE
                    WHEN m.home_team_id = ? AND m.home_score < m.away_score THEN 1
                    WHEN m.away_team_id = ? AND m.away_score < m.home_score THEN 1
                    ELSE 0
                END) AS losses,
                SUM(CASE WHEN m.home_team_id = ? THEN m.home_score ELSE 0 END +
                    CASE WHEN m.away_team_id = ? THEN m.away_score ELSE 0 END) AS goals_for,
                SUM(CASE WHEN m.home_team_id = ? THEN m.away_score ELSE 0 END +
                    CASE WHEN m.away_team_id = ? THEN m.home_score ELSE 0 END) AS goals_against
            FROM cl_matches m
            WHERE (m.home_team_id = ? OR m.away_team_id = ?)
              AND m.status IN ('FINISHED', 'AET', 'PEN')
            GROUP BY phase
            ORDER BY phase
        ", array_fill(0, 12, $teamId));

        $result = ['group_phase' => null, 'knockout' => null];
        foreach ($rows as $row) {
            $r = (array) $row;
            $r['goal_difference'] = ($r['goals_for'] ?? 0) - ($r['goals_against'] ?? 0);
            $r['win_pct'] = $r['matches_played'] > 0
                ? round(($r['wins'] / $r['matches_played']) * 100, 1)
                : 0;
            $result[$r['phase']] = $r;
        }

        return $result;
    }

    private function computeHomeAway(int $teamId): array
    {
        $rows = DB::select("
            SELECT
                CASE WHEN m.home_team_id = ? THEN 'home' ELSE 'away' END AS venue,
                COUNT(*) AS matches_played,
                SUM(CASE
                    WHEN m.home_team_id = ? AND m.home_score > m.away_score THEN 1
                    WHEN m.away_team_id = ? AND m.away_score > m.home_score THEN 1
                    ELSE 0
                END) AS wins,
                SUM(CASE
                    WHEN m.home_team_id = ? AND m.home_score = m.away_score THEN 1
                    WHEN m.away_team_id = ? AND m.away_score = m.home_score THEN 1
                    ELSE 0
                END) AS draws,
                SUM(CASE
                    WHEN m.home_team_id = ? AND m.home_score < m.away_score THEN 1
                    WHEN m.away_team_id = ? AND m.away_score < m.home_score THEN 1
                    ELSE 0
                END) AS losses,
                SUM(CASE WHEN m.home_team_id = ? THEN m.home_score ELSE m.away_score END) AS goals_for,
                SUM(CASE WHEN m.home_team_id = ? THEN m.away_score ELSE m.home_score END) AS goals_against
            FROM cl_matches m
            WHERE (m.home_team_id = ? OR m.away_team_id = ?)
              AND m.status IN ('FINISHED', 'AET', 'PEN')
            GROUP BY venue
            ORDER BY venue
        ", array_fill(0, 11, $teamId));

        $result = ['home' => null, 'away' => null];
        foreach ($rows as $row) {
            $r = (array) $row;
            $r['goal_difference'] = ($r['goals_for'] ?? 0) - ($r['goals_against'] ?? 0);
            $result[$r['venue']] = $r;
        }

        return $result;
    }

    private function determineArchetype(int $teamId): array
    {
        $stats = $this->computeOverallStats($teamId);
        $stage = $this->computeStageBreakdown($teamId);
        $venue = $this->computeHomeAway($teamId);

        $labels = [];
        $primary = 'Balanced Contender';
        $description = 'A well-rounded team with no extreme statistical tendencies.';
        $icon = '⚖️';

        $avgGF = $stats['avg_goals_for'] ?? 0;
        $avgGA = $stats['avg_goals_against'] ?? 0;
        $winPct = $stats['win_pct'] ?? 0;
        $gd = $stats['goal_difference'] ?? 0;

        if ($avgGF >= 2.5) {
            $labels[] = 'Goal Machine';
            $primary = 'Goal Machine';
            $description = 'Relentless attacking force that regularly scores 3+ goals per match.';
            $icon = '⚡';
        } elseif ($avgGF >= 1.8) {
            $labels[] = 'Free-Scoring';
        }

        if ($avgGA < 0.8 && $gd > 20) {
            $labels[] = 'Defensive Wall';
            $primary = 'Defensive Wall';
            $description = 'Impregnable defense that suffocates opponents and concedes very few goals.';
            $icon = '🛡️';
        } elseif ($avgGA < 1.0) {
            $labels[] = 'Solid Defense';
            if ($primary === 'Balanced Contender') {
                $primary = 'Defensive Solidity';
                $description = 'Well-organized defensive unit that is difficult to break down.';
                $icon = '🧱';
            }
        }

        if ($winPct >= 65) {
            $labels[] = 'Dominant';
            if ($primary === 'Balanced Contender' || $primary === 'Goal Machine') {
                $primary = 'Dominant Force';
                $description = 'Consistently overpowering opponents with a relentless winning mentality.';
                $icon = '👑';
            }
        } elseif ($winPct >= 55) {
            $labels[] = 'Strong';
        }

        if ($stage['group_phase'] && $stage['knockout']) {
            $groupWinPct = $stage['group_phase']['win_pct'] ?? 0;
            $koWinPct = $stage['knockout']['win_pct'] ?? 0;

            if ($groupWinPct > $koWinPct + 10) {
                $labels[] = 'Group Stage Dominator';
                $description = 'Dominant in group play but struggles to maintain that level in knockout pressure.';
                if ($primary !== 'Defensive Wall' && $primary !== 'Dominant Force') {
                    $primary = 'Group Stage Dominator';
                    $icon = '📊';
                }
            } elseif ($koWinPct > $groupWinPct + 10) {
                $labels[] = 'Knockout Specialist';
                $description = 'Rises to the occasion in high-stakes knockout matches with clutch performances.';
                if ($primary !== 'Defensive Wall' && $primary !== 'Dominant Force' && $primary !== 'Goal Machine') {
                    $primary = 'Knockout Specialist';
                    $icon = '🏆';
                }
            }
        }

        if ($venue['home'] && $venue['away']) {
            $homeWinPct = $venue['home']['win_pct'] ?? 0;
            $awayWinPct = $venue['away']['win_pct'] ?? 0;

            if ($homeWinPct > $awayWinPct + 20) {
                $labels[] = 'Home Fortress';
                if ($primary === 'Balanced Contender') {
                    $primary = 'Home Fortress';
                    $description = 'Almost unbeatable at home but significantly less effective on the road.';
                    $icon = '🏰';
                }
            } elseif ($awayWinPct > $homeWinPct + 10) {
                $labels[] = 'Road Warriors';
                if ($primary === 'Balanced Contender') {
                    $primary = 'Road Warriors';
                    $description = 'Thrives away from home with remarkable consistency on foreign soil.';
                    $icon = '🛣️';
                }
            }
        }

        return [
            'primary' => $primary,
            'labels' => $labels,
            'description' => $description,
            'icon' => $icon,
            'win_pct' => $winPct,
            'avg_goals_for' => $avgGF,
            'avg_goals_against' => $avgGA,
            'goal_difference' => $gd,
        ];
    }
}
