<?php

namespace App\Http\Controllers;

use App\Models\WhatIfScenario;
use Illuminate\Http\Request;

class ScenarioController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except(['index', 'show']);
    }

    public function index(Request $request)
    {
        $query = WhatIfScenario::with('user');

        if ($search = $request->get('q')) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        if ($request->user() && $request->get('mine')) {
            $query->where('user_id', $request->user()->id);
        } else {
            $query->where('is_public', true);
        }

        $scenarios = $query->orderByDesc('created_at')->paginate(20);
        return view('scenarios.index', compact('scenarios'));
    }

    public function create()
    {
        return view('scenarios.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'base_query' => 'required|string',
            'modifications' => 'nullable|json',
            'is_public' => 'boolean',
        ]);

        $data['user_id'] = $request->user()->id;
        $data['is_public'] = $data['is_public'] ?? false;

        $scenario = WhatIfScenario::create($data);
        return redirect()->route('scenarios.show', $scenario);
    }

    public function show(WhatIfScenario $scenario)
    {
        if (!$scenario->is_public && $scenario->user_id !== auth()->id()) {
            abort(403);
        }
        $scenario->load('user');
        return view('scenarios.show', compact('scenario'));
    }

    public function edit(WhatIfScenario $scenario)
    {
        if ($scenario->user_id !== auth()->id()) {
            abort(403);
        }
        return view('scenarios.edit', compact('scenario'));
    }

    public function update(Request $request, WhatIfScenario $scenario)
    {
        if ($scenario->user_id !== auth()->id()) {
            abort(403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'base_query' => 'required|string',
            'modifications' => 'nullable|json',
            'is_public' => 'boolean',
        ]);

        $scenario->update($data);
        return redirect()->route('scenarios.show', $scenario);
    }

    public function destroy(WhatIfScenario $scenario)
    {
        if ($scenario->user_id !== auth()->id()) {
            abort(403);
        }
        $scenario->delete();
        return redirect()->route('scenarios.index');
    }
}
