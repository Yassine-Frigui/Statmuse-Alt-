<?php

namespace App\Http\Controllers;

use App\Models\Championship;
use App\Models\Team;
use Illuminate\Http\Request;

class ChampionshipController extends Controller
{
    public function index(Request $request)
    {
        $query = Championship::with(['championTeam', 'runnerUpTeam', 'mvpPlayer', 'season']);

        if ($teamId = $request->get('team')) {
            $query->where('champion_team_id', $teamId)
                  ->orWhere('runner_up_team_id', $teamId);
        }

        if ($decade = $request->get('decade')) {
            $start = (int) $decade;
            $end = $start + 9;
            $query->whereHas('season', fn($q) => $q->whereBetween('year', [$start, $end]));
        }

        $championships = $query->orderByDesc(
            Season::select('year')->whereColumn('id', 'championships.season_id')
        )->paginate(20);

        $teams = Team::orderBy('name')->get();
        $decades = [1950, 1960, 1970, 1980, 1990, 2000, 2010, 2020];

        return view('championships.index', compact('championships', 'teams', 'decades'));
    }
}
