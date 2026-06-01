@extends('layouts.nba')

@section('title', 'Seasons')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-12">
    <div class="mb-8">
        <h1 class="font-display italic font-black text-4xl md:text-5xl uppercase tracking-tight">Seasons</h1>
        <p class="text-data-slate mt-2">{{ $seasons->total() }} NBA seasons</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($seasons as $season)
            <a href="{{ route('seasons.show', $season) }}" class="bg-court-dark border border-white/5 rounded-xl p-5 hover:border-hoop-orange/30 transition-all group">
                <h3 class="font-display text-xl font-bold uppercase tracking-tight group-hover:text-hoop-orange transition-colors">{{ $season->year }}–{{ substr($season->year + 1, -2) }}</h3>
                <p class="text-data-slate text-xs mt-1">{{ $season->label ?? 'NBA Season' }}</p>
                <div class="mt-3 text-xs text-data-slate">
                    @if($season->start_date)Starts: {{ \Carbon\Carbon::parse($season->start_date)->format('M j, Y') }}@endif
                </div>
            </a>
        @empty
            <div class="col-span-full text-center py-12 text-data-slate">No seasons found.</div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $seasons->links() }}
    </div>
</div>
@endsection
