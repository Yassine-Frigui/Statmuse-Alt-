<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerSeasonStat;
use Illuminate\Support\Facades\DB;

class PlayerProfileService
{
    private array $archetypeDefinitions = [
        'Primary Scorer' => [
            'conditions' => [
                'ppg' => ['>=', 22],
                'usage_score' => ['>=', 28],
            ],
        ],
        'Playmaking Point Guard' => [
            'conditions' => [
                'apg' => ['>=', 7],
                'position' => ['PG'],
            ],
        ],
        'Combo Guard' => [
            'conditions' => [
                'apg' => ['>=', 4],
                'ppg' => ['>=', 14],
                'position' => ['PG', 'SG'],
            ],
        ],
        '3-and-D Wing' => [
            'conditions' => [
                'three_pct' => ['>=', 0.35],
                'spg' => ['>=', 1.0],
                'position' => ['SG', 'SF'],
            ],
        ],
        'Stretch Big' => [
            'conditions' => [
                'three_pct' => ['>=', 0.34],
                'rpg' => ['>=', 6],
                'position' => ['PF', 'C'],
            ],
        ],
        'Two-Way Star' => [
            'conditions' => [
                'ppg' => ['>=', 20],
                'spg' => ['>=', 1.2],
                'bpg' => ['>=', 0.5],
            ],
        ],
        'Pure Shooter' => [
            'conditions' => [
                'fg_pct' => ['>=', 0.45],
                'three_pct' => ['>=', 0.38],
                'ft_pct' => ['>=', 0.82],
            ],
        ],
        'Glass Cleaner' => [
            'conditions' => [
                'rpg' => ['>=', 10],
                'position' => ['C', 'PF'],
            ],
        ],
        'Defensive Anchor' => [
            'conditions' => [
                'bpg' => ['>=', 1.8],
                'rpg' => ['>=', 8],
                'position' => ['C', 'PF'],
            ],
        ],
        'Floor General' => [
            'conditions' => [
                'apg' => ['>=', 8],
                'ppg' => ['<', 20],
            ],
        ],
        'Slasher' => [
            'conditions' => [
                'fg_pct' => ['>=', 0.48],
                'ppg' => ['>=', 15],
                'three_pct' => ['<', 0.35],
            ],
        ],
        'High-Usage Facilitator' => [
            'conditions' => [
                'apg' => ['>=', 5],
                'ppg' => ['>=', 20],
                'usage_score' => ['>=', 25],
            ],
        ],
    ];

    public function generateProfile(int $playerId): array
    {
        $player = Player::with(['seasonStats.season', 'awards.award'])->findOrFail($playerId);

        $careerStats = $this->calculateCareerStats($player);
        $perGame = $careerStats['per_game'];
        $totals = $careerStats['totals'];

        $archetype = $this->determineArchetype($perGame, $player->position);
        $strengths = $this->identifyStrengths($perGame, $player->position);
        $weaknesses = $this->identifyWeaknesses($perGame, $player->position);
        $scoutingReport = $this->generateScoutingReport($archetype, $strengths, $weaknesses, $perGame, $player);
        $peakSeason = $this->findPeakSeason($player);
        $advanced = $this->calculateAdvancedMetrics($careerStats, $player);

        return [
            'player' => [
                'id' => $player->id,
                'name' => $player->first_name . ' ' . $player->last_name,
                'first_name' => $player->first_name,
                'last_name' => $player->last_name,
                'position' => $player->position,
                'height' => $player->height,
                'weight' => $player->weight,
                'college' => $player->college,
                'drafted_year' => $player->drafted_year,
                'birth_date' => $player->birth_date,
            ],
            'career_totals' => $totals,
            'per_game_averages' => $perGame,
            'advanced' => $advanced,
            'archetype' => $archetype,
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'scouting_report' => $scoutingReport,
            'peak_season' => $peakSeason,
            'seasons_played' => $careerStats['seasons_count'],
            'years_active' => $careerStats['years_active'],
            'awards' => $this->formatAwards($player),
            'season_stats' => $this->formatSeasonStats($player),
        ];
    }

    private function calculateCareerStats(Player $player): array
    {
        $seasonStats = $player->seasonStats;

        $totals = [
            'games_played' => (int) $seasonStats->sum('games_played'),
            'points' => (float) $seasonStats->sum('points'),
            'rebounds' => (float) $seasonStats->sum('rebounds'),
            'assists' => (float) $seasonStats->sum('assists'),
            'steals' => (float) $seasonStats->sum('steals'),
            'blocks' => (float) $seasonStats->sum('blocks'),
            'minutes' => (float) $seasonStats->sum('minutes'),
            'fg_pct' => null,
            'three_pct' => null,
            'ft_pct' => null,
        ];

        $weightedFg = 0;
        $weightedThree = 0;
        $weightedFt = 0;
        $totalFgAttempts = 0;
        $totalThreeAttempts = 0;
        $totalFtAttempts = 0;

        foreach ($seasonStats as $stat) {
            if ($stat->fg_pct !== null) {
                $pointsFromFg = $stat->fg_pct * $stat->points;
                $weightedFg += $pointsFromFg;
                $totalFgAttempts += $stat->points > 0 ? 1 : 0;
            }
            if ($stat->three_pct !== null) {
                $weightedThree += $stat->three_pct;
                $totalThreeAttempts++;
            }
            if ($stat->ft_pct !== null) {
                $weightedFt += $stat->ft_pct;
                $totalFtAttempts++;
            }
        }

        $totals['fg_pct'] = $totalFgAttempts > 0 ? round($weightedFg / ($seasonStats->sum('points') ?: 1), 3) : null;
        $totals['three_pct'] = $totalThreeAttempts > 0 ? round($weightedThree / $totalThreeAttempts, 3) : null;
        $totals['ft_pct'] = $totalFtAttempts > 0 ? round($weightedFt / $totalFtAttempts, 3) : null;

        $gp = max($totals['games_played'], 1);

        $perGame = [
            'ppg' => round($totals['points'] / $gp, 1),
            'rpg' => round($totals['rebounds'] / $gp, 1),
            'apg' => round($totals['assists'] / $gp, 1),
            'spg' => round($totals['steals'] / $gp, 1),
            'bpg' => round($totals['blocks'] / $gp, 1),
            'mpg' => round($totals['minutes'] / $gp, 1),
            'fg_pct' => $totals['fg_pct'],
            'three_pct' => $totals['three_pct'],
            'ft_pct' => $totals['ft_pct'],
        ];

        $seasons = $seasonStats->pluck('season.year')->filter()->unique()->sort();
        $yearsActive = $seasons->isNotEmpty() ? ($seasons->max() - $seasons->min()) : 0;

        return [
            'totals' => $totals,
            'per_game' => $perGame,
            'seasons_count' => $seasonStats->count(),
            'years_active' => $yearsActive,
        ];
    }

    private function calculateAdvancedMetrics(array $careerStats, Player $player): array
    {
        $perGame = $careerStats['per_game'];
        $totals = $careerStats['totals'];

        $usageScore = ($perGame['ppg'] * 0.4) + ($perGame['apg'] * 0.3) + ($perGame['rpg'] * 0.2) + ($perGame['spg'] * 0.1);
        $efficiencyScore = ($perGame['fg_pct'] ?? 0) * 100 + ($perGame['ppp'] ?? 0);
        $twoWayScore = ($perGame['spg'] + $perGame['bpg']) * ($perGame['ppg'] / 10);

        $per36 = [];
        $mpg = $perGame['mpg'];
        if ($mpg > 0 && $totals['minutes'] > 0) {
            $per36 = [
                'pts' => round($totals['points'] / $totals['minutes'] * 36, 1),
                'reb' => round($totals['rebounds'] / $totals['minutes'] * 36, 1),
                'ast' => round($totals['assists'] / $totals['minutes'] * 36, 1),
                'stl' => round($totals['steals'] / $totals['minutes'] * 36, 1),
                'blk' => round($totals['blocks'] / $totals['minutes'] * 36, 1),
            ];
        }

        return [
            'usage_score' => round($usageScore, 1),
            'efficiency_score' => round($efficiencyScore, 1),
            'two_way_score' => round($twoWayScore, 1),
            'per_36' => $per36,
        ];
    }

    private function determineArchetype(array $perGame, ?string $position): array
    {
        $usageScore = ($perGame['ppg'] * 0.4) + ($perGame['apg'] * 0.3) + ($perGame['rpg'] * 0.2) + ($perGame['spg'] * 0.1);
        $scores = [];

        foreach ($this->archetypeDefinitions as $archetype => $def) {
            $met = 0;
            $total = count($def['conditions']);

            foreach ($def['conditions'] as $key => $condition) {
                if ($key === 'position') {
                    if (in_array($position, $condition)) $met++;
                    continue;
                }

                $op = $condition[0] ?? '>=';
                $val = $condition[1] ?? $condition[0] ?? 0;

                $actual = $key === 'usage_score' ? $usageScore : ($perGame[$key] ?? 0);

                $match = match ($op) {
                    '>=' => $actual >= $val,
                    '>' => $actual > $val,
                    '<' => $actual < $val,
                    '<=' => $actual <= $val,
                    default => false,
                };

                if ($match) $met++;
            }

            $scores[$archetype] = $total > 0 ? $met / $total : 0;
        }

        arsort($scores);
        $primary = array_key_first($scores);

        $primaryScore = $scores[$primary] ?? 0;
        $secondary = null;

        foreach ($scores as $arch => $score) {
            if ($arch !== $primary && $score >= 0.5) {
                $secondary = $arch;
                break;
            }
        }

        $icon = match (true) {
            str_contains($primary, 'Scorer') || str_contains($primary, 'Shooter') => '🎯',
            str_contains($primary, 'Playmaking') || str_contains($primary, 'General') || str_contains($primary, 'Facilitator') => '🎮',
            str_contains($primary, '3-and-D') || str_contains($primary, 'Two-Way') => '🔒',
            str_contains($primary, 'Stretch') => '📏',
            str_contains($primary, 'Glass') || str_contains($primary, 'Anchor') => '🧱',
            str_contains($primary, 'Slasher') || str_contains($primary, 'Combo') => '💥',
            default => '⭐',
        };

        $description = match ($primary) {
            'Primary Scorer' => 'A go-to offensive option capable of creating shots and carrying the scoring load.',
            'Playmaking Point Guard' => 'A floor leader who excels at setting up teammates and controlling the offense.',
            'Combo Guard' => 'A versatile guard who can both score and create for others.',
            '3-and-D Wing' => 'A perimeter defender who spaces the floor with reliable three-point shooting.',
            'Stretch Big' => 'A frontcourt player who pulls defenders away from the basket with outside shooting.',
            'Two-Way Star' => 'An elite contributor on both ends of the floor — scores and defends at a high level.',
            'Pure Shooter' => 'A marksman with exceptional accuracy from all areas of the floor.',
            'Glass Cleaner' => 'A dominant rebounder who controls the boards on both ends.',
            'Defensive Anchor' => 'A rim-protecting presence who anchors the defense and controls the paint.',
            'Floor General' => 'A pure point guard who prioritizes playmaking and running the offense.',
            'Slasher' => 'An athletic wing who attacks the rim and finishes with authority.',
            'High-Usage Facilitator' => 'A primary ball-handler who combines high-volume scoring with playmaking.',
            default => 'Versatile Contributor',
        };

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'confidence' => round($primaryScore * 100),
            'icon' => $icon,
            'description' => $description,
        ];
    }

    private function identifyStrengths(array $perGame, ?string $position): array
    {
        $strengths = [];

        if (($perGame['ppg'] ?? 0) >= 25) $strengths[] = 'Elite scoring — can take over games offensively';
        elseif (($perGame['ppg'] ?? 0) >= 20) $strengths[] = 'High-level scoring threat';
        elseif (($perGame['ppg'] ?? 0) >= 15) $strengths[] = 'Reliable scoring option';

        if (($perGame['apg'] ?? 0) >= 8) $strengths[] = 'Elite playmaker — creates easy looks for teammates';
        elseif (($perGame['apg'] ?? 0) >= 5) $strengths[] = 'Strong playmaker with good court vision';

        if (($perGame['rpg'] ?? 0) >= 12) $strengths[] = 'Dominant rebounder — controls the glass';
        elseif (($perGame['rpg'] ?? 0) >= 8) $strengths[] = 'Strong rebounder for position';

        if (($perGame['spg'] ?? 0) >= 2.0) $strengths[] = 'Elite perimeter defender — disruptive with steals';
        elseif (($perGame['spg'] ?? 0) >= 1.5) $strengths[] = 'Active hands — generates turnovers';

        if (($perGame['bpg'] ?? 0) >= 2.5) $strengths[] = 'Elite shot-blocker — erases shots at the rim';
        elseif (($perGame['bpg'] ?? 0) >= 1.5) $strengths[] = 'Good rim protector';

        if (($perGame['fg_pct'] ?? 0) >= 0.55) $strengths[] = 'Highly efficient scorer — excellent shot selection';
        elseif (($perGame['fg_pct'] ?? 0) >= 0.48) $strengths[] = 'Efficient scorer';

        if (($perGame['three_pct'] ?? 0) >= 0.40) $strengths[] = 'Elite three-point shooter — gravity stretches defenses';
        elseif (($perGame['three_pct'] ?? 0) >= 0.36) $strengths[] = 'Reliable three-point shooter';

        if (($perGame['ft_pct'] ?? 0) >= 0.85) $strengths[] = 'Excellent free-throw shooter — clutch at the line';
        elseif (($perGame['ft_pct'] ?? 0) >= 0.80) $strengths[] = 'Solid free-throw shooter';

        if (in_array($position, ['PG', 'SG']) && ($perGame['apg'] ?? 0) >= 6) $strengths[] = 'True point guard — runs the offense effectively';
        if (in_array($position, ['C', 'PF']) && ($perGame['bpg'] ?? 0) >= 1.5) $strengths[] = 'Paint protector — alters shots around the rim';

        return array_slice($strengths, 0, 5);
    }

    private function identifyWeaknesses(array $perGame, ?string $position): array
    {
        $weaknesses = [];

        if (($perGame['three_pct'] ?? 1) < 0.30 && in_array($position, ['SG', 'SF', 'PG'])) {
            $weaknesses[] = 'Below-average three-point shooter — spacing liability';
        } elseif (($perGame['three_pct'] ?? 1) < 0.25 && in_array($position, ['C', 'PF'])) {
            $weaknesses[] = 'Limited range — cannot stretch the floor';
        }

        if (($perGame['fg_pct'] ?? 1) < 0.40 && ($perGame['ppg'] ?? 0) >= 15) {
            $weaknesses[] = 'Volume scorer with below-average efficiency';
        }

        if (($perGame['ft_pct'] ?? 1) < 0.65) {
            $weaknesses[] = 'Poor free-throw shooter — hack-a-Shaq target late in games';
        }

        if (($perGame['apg'] ?? 10) < 2 && in_array($position, ['PG'])) {
            $weaknesses[] = 'Limited playmaker — not a natural facilitator';
        } elseif (($perGame['apg'] ?? 10) < 3 && in_array($position, ['SG', 'SF'])) {
            $weaknesses[] = 'Below-average passer — can miss open teammates';
        }

        if (($perGame['rpg'] ?? 10) < 3 && in_array($position, ['PF', 'C'])) {
            $weaknesses[] = 'Below-average rebounder for position';
        } elseif (($perGame['rpg'] ?? 10) < 2 && in_array($position, ['SF'])) {
            $weaknesses[] = 'Needs to contribute more on the boards';
        }

        if (($perGame['spg'] ?? 10) < 0.5 && in_array($position, ['PG', 'SG'])) {
            $weaknesses[] = 'Lacks defensive disruption — few steals generated';
        }

        if (($perGame['bpg'] ?? 10) < 0.3 && in_array($position, ['C', 'PF'])) {
            $weaknesses[] = 'Limited rim protection — not a deterrent at the basket';
        }

        if (($perGame['mpg'] ?? 40) < 22 && ($perGame['ppg'] ?? 0) >= 15) {
            $weaknesses[] = 'Productive in limited minutes — stamina or defensive concerns';
        }

        return array_slice($weaknesses, 0, 4);
    }

    private function generateScoutingReport(
        array $archetype,
        array $strengths,
        array $weaknesses,
        array $perGame,
        Player $player
    ): string {
        $name = $player->first_name . ' ' . $player->last_name;
        $position = $player->position ?? 'N/A';

        $report = "**Archetype:** {$archetype['primary']}";
        if ($archetype['secondary']) {
            $report .= " / {$archetype['secondary']}";
        }
        $report .= "\n\n";

        $report .= "A {$position} who ";
        $report .= match (true) {
            ($perGame['ppg'] ?? 0) >= 25 => "is a primary offensive weapon, ",
            ($perGame['ppg'] ?? 0) >= 20 => "is a proven scorer, ",
            ($perGame['apg'] ?? 0) >= 7 => "dictates the tempo as a floor general, ",
            ($perGame['rpg'] ?? 0) >= 10 => "controls the paint with authority, ",
            default => "contributes on both ends of the floor, ",
        };

        $report .= "averaging **{$perGame['ppg']} PPG** / **{$perGame['rpg']} RPG** / **{$perGame['apg']} APG** ";
        $report .= "over his career.\n\n";

        $report .= "**Strengths:**\n";
        foreach ($strengths as $s) {
            $report .= "- {$s}\n";
        }
        $report .= "\n";

        if (!empty($weaknesses)) {
            $report .= "**Areas for Improvement:**\n";
            foreach ($weaknesses as $w) {
                $report .= "- {$w}\n";
            }
            $report .= "\n";
        }

        $report .= "**Statistical Profile:**\n";
        $report .= "- Shooting: {$this->formatPct($perGame['fg_pct'])} FG% / {$this->formatPct($perGame['three_pct'])} 3P% / {$this->formatPct($perGame['ft_pct'])} FT%\n";
        $report .= "- Per 36 min: {$perGame['ppg']} pts / {$perGame['rpg']} reb / {$perGame['apg']} ast\n";
        $report .= "- Efficiency: {$this->formatPct($perGame['fg_pct'])} TS% (true shooting)\n";

        return $report;
    }

    private function findPeakSeason(Player $player): ?array
    {
        $peak = null;
        $peakScore = 0;

        foreach ($player->seasonStats as $stat) {
            $season = $stat->season;
            if (!$season) continue;

            $gp = max($stat->games_played, 1);
            $ppg = $stat->points / $gp;
            $score = $ppg + ($stat->rebounds / $gp) * 0.5 + ($stat->assists / $gp) * 0.5;

            if ($score > $peakScore) {
                $peakScore = $score;
                $peak = [
                    'year' => $season->year,
                    'team' => $stat->team?->name,
                    'gp' => $stat->games_played,
                    'ppg' => round($ppg, 1),
                    'rpg' => round($stat->rebounds / $gp, 1),
                    'apg' => round($stat->assists / $gp, 1),
                    'fg_pct' => $stat->fg_pct,
                    'three_pct' => $stat->three_pct,
                ];
            }
        }

        return $peak;
    }

    private function formatAwards(Player $player): array
    {
        return $player->awards->map(fn($a) => [
            'name' => $a->award?->name,
            'year' => $a->season?->year,
        ])->filter(fn($a) => $a['name'] !== null)->values()->toArray();
    }

    private function formatSeasonStats(Player $player): array
    {
        return $player->seasonStats->map(function ($stat) {
            $gp = max($stat->games_played, 1);
            return [
                'year' => $stat->season?->year,
                'team' => $stat->team?->name,
                'gp' => $stat->games_played,
                'ppg' => round($stat->points / $gp, 1),
                'rpg' => round($stat->rebounds / $gp, 1),
                'apg' => round($stat->assists / $gp, 1),
                'spg' => round($stat->steals / $gp, 1),
                'bpg' => round($stat->blocks / $gp, 1),
                'fg_pct' => $stat->fg_pct,
                'three_pct' => $stat->three_pct,
                'ft_pct' => $stat->ft_pct,
            ];
        })->sortByDesc('year')->values()->toArray();
    }

    private function formatPct(?float $value): string
    {
        if ($value === null) return 'N/A';
        return number_format($value * 100, 1) . '%';
    }
}
