<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function index(Request $request)
    {
        $query = Player::query()->with('seasonStats');

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($position = $request->get('position')) {
            $query->where('position', $position);
        }

        if ($teamId = $request->get('team')) {
            $query->whereHas('seasonStats', fn($q) => $q->where('team_id', $teamId));
        }

        $players = $query->orderBy('last_name')->paginate(30);
        $teams = Team::orderBy('name')->get();
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];

        return view('players.index', compact('players', 'teams', 'positions'));
    }

    public function show(Player $player)
    {
        $player->load(['seasonStats.team', 'seasonStats.season', 'awards.award', 'gameStats.game' => function ($q) {
            $q->orderByDesc('date')->limit(20);
        }]);

        $careerTotals = (object) [
            'points' => $player->seasonStats->sum('points'),
            'rebounds' => $player->seasonStats->sum('rebounds'),
            'assists' => $player->seasonStats->sum('assists'),
            'games' => $player->seasonStats->sum('games_played'),
            'steals' => $player->seasonStats->sum('steals'),
            'blocks' => $player->seasonStats->sum('blocks'),
        ];

        return view('players.show', compact('player', 'careerTotals'));
    }
}
