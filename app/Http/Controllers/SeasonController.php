<?php

namespace App\Http\Controllers;

use App\Models\Season;
use App\Models\PlayerSeasonStat;
use App\Models\Championship;
use Illuminate\Http\Request;

class SeasonController extends Controller
{
    public function index()
    {
        $seasons = Season::orderByDesc('year')->paginate(20);
        return view('seasons.index', compact('seasons'));
    }

    public function show(Season $season)
    {
        $season->load('championship.championTeam', 'championship.runnerUpTeam', 'championship.mvpPlayer');

        $scoringLeaders = PlayerSeasonStat::where('season_id', $season->id)
            ->selectRaw('player_id, SUM(points) as total_pts, SUM(games_played) as gp')
            ->groupBy('player_id')
            ->orderByDesc('total_pts')
            ->limit(10)
            ->with('player')
            ->get();

        $assistLeaders = PlayerSeasonStat::where('season_id', $season->id)
            ->selectRaw('player_id, SUM(assists) as total_ast, SUM(games_played) as gp')
            ->groupBy('player_id')
            ->orderByDesc('total_ast')
            ->limit(10)
            ->with('player')
            ->get();

        $reboundLeaders = PlayerSeasonStat::where('season_id', $season->id)
            ->selectRaw('player_id, SUM(rebounds) as total_reb, SUM(games_played) as gp')
            ->groupBy('player_id')
            ->orderByDesc('total_reb')
            ->limit(10)
            ->with('player')
            ->get();

        return view('seasons.show', compact('season', 'scoringLeaders', 'assistLeaders', 'reboundLeaders'));
    }
}
