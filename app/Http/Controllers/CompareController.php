<?php

namespace App\Http\Controllers;

use App\Services\PlayerProfileService;
use App\Services\TeamProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompareController extends Controller
{
    public function __construct(
        private PlayerProfileService $profileService,
        private TeamProfileService $teamProfileService,
    ) {}

    public function index()
    {
        return view('compare.index');
    }

    public function compare(Request $request): JsonResponse
    {
        $sport = $request->input('sport', 'nba');

        if ($sport === 'champions') {
            return $this->compareTeams($request);
        }

        return $this->comparePlayers($request);
    }

    private function comparePlayers(Request $request): JsonResponse
    {
        $request->validate([
            'player_a_id' => 'required|integer|exists:players,id',
            'player_b_id' => 'required|integer|exists:players,id',
        ]);

        try {
            $profileA = $this->profileService->generateProfile((int) $request->player_a_id);
            $profileB = $this->profileService->generateProfile((int) $request->player_b_id);

            $comparison = $this->buildPlayerComparison($profileA, $profileB);

            return response()->json([
                'player_a' => $profileA,
                'player_b' => $profileB,
                'comparison' => $comparison,
                'sport' => 'nba',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function compareTeams(Request $request): JsonResponse
    {
        $request->validate([
            'player_a_id' => 'required|integer|exists:cl_teams,id',
            'player_b_id' => 'required|integer|exists:cl_teams,id',
        ]);

        try {
            $teamAId = (int) $request->player_a_id;
            $teamBId = (int) $request->player_b_id;

            $profileA = $this->teamProfileService->generateProfile($teamAId);
            $profileB = $this->teamProfileService->generateProfile($teamBId);

            $headToHead = $this->teamProfileService->headToHead($teamAId, $teamBId);
            $comparison = $this->buildTeamComparison($profileA, $profileB);

            return response()->json([
                'player_a' => $profileA,
                'player_b' => $profileB,
                'head_to_head' => $headToHead,
                'comparison' => $comparison,
                'sport' => 'champions',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function buildPlayerComparison(array $a, array $b): array
    {
        $aStats = $a['per_game_averages'];
        $bStats = $b['per_game_averages'];
        $aTotals = $a['career_totals'];
        $bTotals = $b['career_totals'];

        $categories = [
            'Scoring' => [
                ['label' => 'Points Per Game', 'a' => $aStats['ppg'], 'b' => $bStats['ppg'], 'unit' => '', 'higher' => 'better'],
                ['label' => 'Total Points', 'a' => round($aTotals['points']), 'b' => round($bTotals['points']), 'unit' => '', 'higher' => 'better'],
                ['label' => 'FG %', 'a' => $aStats['fg_pct'] ? round($aStats['fg_pct'] * 100, 1) : null, 'b' => $bStats['fg_pct'] ? round($bStats['fg_pct'] * 100, 1) : null, 'unit' => '%', 'higher' => 'better'],
                ['label' => '3P %', 'a' => $aStats['three_pct'] ? round($aStats['three_pct'] * 100, 1) : null, 'b' => $bStats['three_pct'] ? round($bStats['three_pct'] * 100, 1) : null, 'unit' => '%', 'higher' => 'better'],
                ['label' => 'FT %', 'a' => $aStats['ft_pct'] ? round($aStats['ft_pct'] * 100, 1) : null, 'b' => $bStats['ft_pct'] ? round($bStats['ft_pct'] * 100, 1) : null, 'unit' => '%', 'higher' => 'better'],
            ],
            'Playmaking' => [
                ['label' => 'Assists Per Game', 'a' => $aStats['apg'], 'b' => $bStats['apg'], 'unit' => '', 'higher' => 'better'],
                ['label' => 'Total Assists', 'a' => round($aTotals['assists']), 'b' => round($bTotals['assists']), 'unit' => '', 'higher' => 'better'],
            ],
            'Rebounding' => [
                ['label' => 'Rebounds Per Game', 'a' => $aStats['rpg'], 'b' => $bStats['rpg'], 'unit' => '', 'higher' => 'better'],
                ['label' => 'Total Rebounds', 'a' => round($aTotals['rebounds']), 'b' => round($bTotals['rebounds']), 'unit' => '', 'higher' => 'better'],
            ],
            'Defense' => [
                ['label' => 'Steals Per Game', 'a' => $aStats['spg'], 'b' => $bStats['spg'], 'unit' => '', 'higher' => 'better'],
                ['label' => 'Blocks Per Game', 'a' => $aStats['bpg'], 'b' => $bStats['bpg'], 'unit' => '', 'higher' => 'better'],
                ['label' => 'Total Steals', 'a' => round($aTotals['steals']), 'b' => round($bTotals['steals']), 'unit' => '', 'higher' => 'better'],
                ['label' => 'Total Blocks', 'a' => round($aTotals['blocks']), 'b' => round($bTotals['blocks']), 'unit' => '', 'higher' => 'better'],
            ],
            'Experience' => [
                ['label' => 'Games Played', 'a' => $aTotals['games_played'], 'b' => $bTotals['games_played'], 'unit' => '', 'higher' => 'better'],
                ['label' => 'Seasons', 'a' => $a['seasons_played'], 'b' => $b['seasons_played'], 'unit' => '', 'higher' => 'better'],
            ],
        ];

        $winner = null;
        $aWins = 0;
        $bWins = 0;

        foreach ($categories as $group) {
            foreach ($group as $stat) {
                if ($stat['a'] === null || $stat['b'] === null) continue;
                if ($stat['a'] > $stat['b']) $aWins++;
                elseif ($stat['b'] > $stat['a']) $bWins++;
            }
        }

        if ($aWins > $bWins) $winner = 'a';
        elseif ($bWins > $aWins) $winner = 'b';

        return [
            'categories' => $categories,
            'winner' => $winner,
            'aWins' => $aWins,
            'bWins' => $bWins,
        ];
    }

    private function buildTeamComparison(array $a, array $b): array
    {
        $aStats = $a['stats'];
        $bStats = $b['stats'];

        $aWins = 0;
        $bWins = 0;

        $compare = function ($aVal, $bVal, $higherBetter = true) use (&$aWins, &$bWins) {
            if ($aVal === null || $bVal === null) return null;
            if ($higherBetter) {
                if ($aVal > $bVal) { $aWins++; return 'a'; }
                if ($bVal > $aVal) { $bWins++; return 'b'; }
            } else {
                if ($aVal < $bVal) { $aWins++; return 'a'; }
                if ($bVal < $aVal) { $bWins++; return 'b'; }
            }
            return null;
        };

        $categories = [
            'Performance' => [
                ['label' => 'Matches Played', 'a' => $aStats['matches_played'], 'b' => $bStats['matches_played'], 'unit' => '', 'winner' => $compare($aStats['matches_played'], $bStats['matches_played'])],
                ['label' => 'Wins', 'a' => $aStats['wins'], 'b' => $bStats['wins'], 'unit' => '', 'winner' => $compare($aStats['wins'], $bStats['wins'])],
                ['label' => 'Draws', 'a' => $aStats['draws'], 'b' => $bStats['draws'], 'unit' => '', 'winner' => $compare($aStats['draws'], $bStats['draws'])],
                ['label' => 'Points', 'a' => $aStats['points'], 'b' => $bStats['points'], 'unit' => '', 'winner' => $compare($aStats['points'], $bStats['points'])],
            ],
            'Goals' => [
                ['label' => 'Goals For', 'a' => $aStats['goals_for'], 'b' => $bStats['goals_for'], 'unit' => '', 'winner' => $compare($aStats['goals_for'], $bStats['goals_for'])],
                ['label' => 'Goals Against', 'a' => $aStats['goals_against'], 'b' => $bStats['goals_against'], 'unit' => '', 'winner' => $compare($aStats['goals_against'], $bStats['goals_against'], false)],
                ['label' => 'Goal Difference', 'a' => $aStats['goal_difference'], 'b' => $bStats['goal_difference'], 'unit' => '', 'winner' => $compare($aStats['goal_difference'], $bStats['goal_difference'])],
                ['label' => 'Avg Goals For', 'a' => $aStats['avg_goals_for'], 'b' => $bStats['avg_goals_for'], 'unit' => '', 'winner' => $compare($aStats['avg_goals_for'], $bStats['avg_goals_for'])],
            ],
        ];

        $winner = null;
        if ($aWins > $bWins) $winner = 'a';
        elseif ($bWins > $aWins) $winner = 'b';

        return [
            'categories' => $categories,
            'winner' => $winner,
            'aWins' => $aWins,
            'bWins' => $bWins,
        ];
    }
}
