<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $sport = $request->get('sport', 'nba');

        if ($sport === 'champions') {
            $teams = DB::table('cl_teams')
                ->where('name', 'like', "%{$query}%")
                ->select('id', 'name')
                ->orderBy('name')
                ->limit(10)
                ->get()
                ->map(fn($t) => [
                    'id' => $t->id,
                    'label' => $t->name,
                ]);
            return response()->json($teams);
        }

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
}
