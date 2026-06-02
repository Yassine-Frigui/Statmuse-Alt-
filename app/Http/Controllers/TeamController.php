<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $query = Team::query()->withCount(['championships', 'homeGames', 'awayGames']);

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('abbreviation', 'like', "%{$search}%");
            });
        }

        if ($conference = $request->get('conference')) {
            $query->where('conference', $conference);
        }

        $teams = $query->orderBy('name')->get();
        $conferences = ['Eastern', 'Western'];

        return view('teams.index', compact('teams', 'conferences'));
    }

    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $teams = Team::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
              ->orWhere('city', 'like', "%{$query}%")
              ->orWhere('abbreviation', 'like', "%{$query}%");
        })
            ->select('id', 'name', 'city', 'abbreviation', 'conference')
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'label' => "{$t->city} {$t->name} ({$t->abbreviation})",
                'conference' => $t->conference,
            ]);

        return response()->json($teams);
    }

    public function show(Team $team)
    {
        $team->load(['championships.season', 'homeGames' => function ($q) {
            $q->with(['homeTeam', 'awayTeam'])->orderByDesc('date')->limit(20);
        }]);

        $seasonStats = $team->playerSeasonStats()
            ->selectRaw('season_id, SUM(points) as pts, SUM(rebounds) as reb, SUM(assists) as ast, SUM(games_played) as gp')
            ->groupBy('season_id')
            ->orderByDesc('season_id')
            ->with('season')
            ->limit(10)
            ->get();

        return view('teams.show', compact('team', 'seasonStats'));
    }
}
