@extends('layouts.nba')

@section('title', 'New Scenario')

@section('content')
<div class="max-w-3xl mx-auto px-6 py-12">
    <a href="{{ route('scenarios.index') }}" class="text-data-slate hover:text-white text-xs font-bold uppercase tracking-widest transition-colors">&larr; Scenarios</a>

    <div class="mt-4 bg-court-dark border border-white/5 rounded-2xl overflow-hidden">
        <div class="px-8 py-6 border-b border-white/5">
            <h1 class="font-display text-3xl font-black uppercase tracking-tight">New Scenario</h1>
            <p class="text-data-slate text-sm mt-1">Create an alternate-reality NBA stat comparison</p>
        </div>
        <form method="POST" action="{{ route('scenarios.store') }}" class="px-8 py-6 space-y-6">
            @csrf

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-data-slate mb-2">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="255" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-sm outline-none focus:border-hoop-orange/50 transition-colors" placeholder="e.g., Jordan's career with modern pace">
                @error('name') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-data-slate mb-2">Description (optional)</label>
                <textarea name="description" rows="3" maxlength="1000" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-sm outline-none focus:border-hoop-orange/50 transition-colors" placeholder="Explain what this scenario compares...">{{ old('description') }}</textarea>
                @error('description') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-data-slate mb-2">Base Query</label>
                <textarea name="base_query" rows="4" required class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-sm font-mono text-data-slate outline-none focus:border-hoop-orange/50 transition-colors" placeholder='e.g., {"intent_type": "player_info", "primary_table": "players", "filters": [{"column": "last_name", "operator": "LIKE", "value": "%Jordan%"}]}'>{{ old('base_query') }}</textarea>
                <p class="text-data-slate/60 text-[10px] mt-1">JSON structure matching the query transformation format</p>
                @error('base_query') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-data-slate mb-2">Modifications (optional JSON)</label>
                <textarea name="modifications" rows="3" class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-sm font-mono text-data-slate outline-none focus:border-hoop-orange/50 transition-colors" placeholder='e.g., {"pace": "modern", "era": "2024"}'>{{ old('modifications') }}</textarea>
                @error('modifications') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_public" id="is_public" value="1" {{ old('is_public') ? 'checked' : '' }} class="rounded border-white/10 bg-white/5">
                <label for="is_public" class="text-xs text-data-slate">Make this scenario public</label>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="bg-hoop-orange hover:bg-hoop-orange/90 text-white font-bold px-6 py-3 rounded-lg text-sm transition-all">Create Scenario</button>
                <a href="{{ route('scenarios.index') }}" class="border border-white/10 text-data-slate hover:text-white px-6 py-3 rounded-lg text-sm font-bold transition-colors">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
