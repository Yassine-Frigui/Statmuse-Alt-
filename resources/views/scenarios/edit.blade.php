@extends('layouts.nba')

@section('title', 'Edit Scenario')

@section('content')
<div class="max-w-3xl mx-auto px-6 py-12">
    <a href="{{ route('scenarios.show', $scenario) }}" class="text-data-slate hover:text-white text-xs font-bold uppercase tracking-widest transition-colors">&larr; Back to Scenario</a>

    <div class="mt-4 bg-court-dark border border-white/5 rounded-2xl overflow-hidden">
        <div class="px-8 py-6 border-b border-white/5">
            <h1 class="font-display text-3xl font-black uppercase tracking-tight">Edit Scenario</h1>
        </div>
        <form method="POST" action="{{ route('scenarios.update', $scenario) }}" class="px-8 py-6 space-y-6">
            @csrf @method('PUT')

            <div class="flex gap-4">
                <div class="flex-1">
                    <label class="block text-xs font-bold uppercase tracking-wider text-data-slate mb-2">Name</label>
                    <input type="text" name="name" value="{{ old('name', $scenario->name) }}" required maxlength="255" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-sm outline-none focus:border-hoop-orange/50 transition-colors">
                    @error('name') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="w-32">
                    <label class="block text-xs font-bold uppercase tracking-wider text-data-slate mb-2">Sport</label>
                    <select name="sport" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-3 text-sm outline-none focus:border-hoop-orange/50 transition-colors">
                        <option value="nba" {{ old('sport', $scenario->sport ?? 'nba') === 'nba' ? 'selected' : '' }}>NBA</option>
                        <option value="champions" {{ old('sport', $scenario->sport) === 'champions' ? 'selected' : '' }}>Champions League</option>
                    </select>
                    @error('sport') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-data-slate mb-2">Description (optional)</label>
                <textarea name="description" rows="3" maxlength="1000" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-sm outline-none focus:border-hoop-orange/50 transition-colors">{{ old('description', $scenario->description) }}</textarea>
                @error('description') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-data-slate mb-2">Base Query</label>
                <textarea name="base_query" rows="4" required class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-sm font-mono text-data-slate outline-none focus:border-hoop-orange/50 transition-colors">{{ old('base_query', $scenario->base_query) }}</textarea>
                @error('base_query') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-data-slate mb-2">Modifications (optional JSON)</label>
                <textarea name="modifications" rows="3" class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-sm font-mono text-data-slate outline-none focus:border-hoop-orange/50 transition-colors">{{ old('modifications', is_string($scenario->modifications) ? $scenario->modifications : json_encode($scenario->modifications)) }}</textarea>
                @error('modifications') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_public" id="is_public" value="1" {{ old('is_public', $scenario->is_public) ? 'checked' : '' }} class="rounded border-white/10 bg-white/5">
                <label for="is_public" class="text-xs text-data-slate">Make this scenario public</label>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="bg-hoop-orange hover:bg-hoop-orange/90 text-white font-bold px-6 py-3 rounded-lg text-sm transition-all">Update Scenario</button>
                <a href="{{ route('scenarios.show', $scenario) }}" class="border border-white/10 text-data-slate hover:text-white px-6 py-3 rounded-lg text-sm font-bold transition-colors">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
