<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $players = Player::where(function ($q) use ($query) {
            $q->where('first_name', 'like', "%{$query}%")
              ->orWhere('last_name', 'like', "%{$query}%")
              ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ["%{$query}%"]);
        })
            ->select('id', 'first_name', 'last_name', 'position')
            ->orderBy('last_name')
            ->limit(10)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'label' => "{$p->first_name} {$p->last_name}",
                'position' => $p->position,
            ]);

        return response()->json($players);
    }
}
