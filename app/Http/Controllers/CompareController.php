<?php

namespace App\Http\Controllers;

use App\Services\PlayerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompareController extends Controller
{
    public function __construct(
        private PlayerProfileService $profileService
    ) {}

    public function index()
    {
        return view('compare.index');
    }

    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'player_a_id' => 'required|integer|exists:players,id',
            'player_b_id' => 'required|integer|exists:players,id',
        ]);

        try {
            $profileA = $this->profileService->generateProfile((int) $request->player_a_id);
            $profileB = $this->profileService->generateProfile((int) $request->player_b_id);

            $comparison = $this->buildComparison($profileA, $profileB);

            return response()->json([
                'player_a' => $profileA,
                'player_b' => $profileB,
                'comparison' => $comparison,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function buildComparison(array $a, array $b): array
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
}
