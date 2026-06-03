@extends('layouts.nba')

@section('title', $scenario->name)

@section('content')
<div class="max-w-4xl mx-auto px-6 py-12">
    <a href="{{ route('scenarios.index') }}" class="text-data-slate hover:text-white text-xs font-bold uppercase tracking-widest transition-colors">&larr; All Scenarios</a>

    <div class="mt-4 bg-court-dark border border-white/5 rounded-2xl overflow-hidden">
        <div class="px-8 py-6 border-b border-white/5">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="font-display text-3xl font-black uppercase tracking-tight">{{ $scenario->name }}</h1>
                    <p class="text-data-slate text-sm mt-1">by {{ $scenario->user?->name ?? 'Anonymous' }} · {{ $scenario->created_at->format('M j, Y') }} @if($scenario->sport) · <span class="uppercase font-bold">{{ $scenario->sport === 'champions' ? 'UCL' : 'NBA' }}</span>@endif</p>
                </div>
                @if(auth()->check() && auth()->id() === $scenario->user_id)
                    <div class="flex gap-2">
                        <a href="{{ route('scenarios.edit', $scenario) }}" class="border border-white/10 text-data-slate hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-colors">Edit</a>
                        <form method="POST" action="{{ route('scenarios.destroy', $scenario) }}" onsubmit="return confirm('Delete this scenario?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="border border-red-500/30 text-red-400 hover:text-red-300 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors">Delete</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
        <div class="px-8 py-6 space-y-6">
            @if($scenario->description)
                <div>
                    <h3 class="font-display text-sm font-bold uppercase tracking-wide text-data-slate mb-2">Description</h3>
                    <p class="text-data-slate">{{ $scenario->description }}</p>
                </div>
            @endif

            <div>
                <h3 class="font-display text-sm font-bold uppercase tracking-wide text-data-slate mb-2">Base Query</h3>
                <pre class="bg-black/40 rounded-lg p-4 text-xs text-data-slate font-mono overflow-x-auto">{{ $scenario->base_query }}</pre>
            </div>

            @if($scenario->modifications)
                <div>
                    <h3 class="font-display text-sm font-bold uppercase tracking-wide text-data-slate mb-2">Modifications</h3>
                    <pre class="bg-black/40 rounded-lg p-4 text-xs text-data-slate font-mono overflow-x-auto">{{ is_string($scenario->modifications) ? $scenario->modifications : json_encode($scenario->modifications, JSON_PRETTY_PRINT) }}</pre>
                </div>
            @endif

            @if($scenario->result_data)
                <div>
                    <h3 class="font-display text-sm font-bold uppercase tracking-wide text-data-slate mb-2">Result Data</h3>
                    <pre class="bg-black/40 rounded-lg p-4 text-xs text-data-slate font-mono overflow-x-auto max-h-96 overflow-y-auto">{{ is_string($scenario->result_data) ? $scenario->result_data : json_encode($scenario->result_data, JSON_PRETTY_PRINT) }}</pre>
                </div>
            @endif

            <div class="flex items-center gap-2">
                <span class="text-xs text-data-slate">Status:</span>
                @if($scenario->is_public)
                    <span class="px-2 py-1 bg-green-500/10 border border-green-500/20 rounded text-[10px] text-green-400 font-medium">Public</span>
                @else
                    <span class="px-2 py-1 bg-white/5 border border-white/10 rounded text-[10px] text-data-slate font-medium">Private</span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
