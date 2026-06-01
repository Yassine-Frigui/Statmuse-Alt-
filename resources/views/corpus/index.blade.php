@extends('layouts.nba')

@section('title', 'Corpus')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-12">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
            <h1 class="font-display italic font-black text-4xl md:text-5xl uppercase tracking-tight">Knowledge Corpus</h1>
            <p class="text-data-slate mt-2">{{ $entries->total() }} articles about NBA history, rules, and events</p>
        </div>
        <form method="GET" class="flex gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search corpus..." class="bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-sm placeholder:text-data-slate/40 outline-none focus:border-hoop-orange/50 transition-colors w-48">
            <button type="submit" class="bg-hoop-orange hover:bg-hoop-orange/90 text-white font-bold px-4 rounded-lg text-xs transition-all">Search</button>
            @if(request()->anyFilled(['q', 'category']))
                <a href="{{ route('corpus.index') }}" class="border border-white/10 text-data-slate hover:text-white px-4 rounded-lg text-xs font-bold transition-colors flex items-center">Clear</a>
            @endif
        </form>
    </div>

    <form method="GET" class="flex flex-wrap gap-3 mb-8">
        <select name="category" onchange="this.form.submit()" class="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs text-data-slate outline-none">
            <option value="">All Categories</option>
            @foreach($categories as $c)
                <option value="{{ $c }}" {{ request('category') === $c ? 'selected' : '' }}>{{ $c }}</option>
            @endforeach
        </select>
        @if(request('q'))
            <input type="hidden" name="q" value="{{ request('q') }}">
        @endif
        <noscript><button type="submit" class="bg-hoop-orange text-white px-3 py-1 rounded text-xs">Filter</button></noscript>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @forelse($entries as $entry)
            <a href="{{ route('corpus.show', $entry) }}" class="bg-court-dark border border-white/5 rounded-xl p-5 hover:border-hoop-orange/30 transition-all group">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <h3 class="font-display text-lg font-bold uppercase tracking-tight group-hover:text-hoop-orange transition-colors">{{ $entry->title }}</h3>
                        <p class="text-xs text-data-slate/60 mt-1">{{ $entry->category ?? 'Uncategorized' }}</p>
                    </div>
                    <span class="px-2 py-1 bg-white/5 rounded text-[10px] text-data-slate font-medium uppercase">{{ $entry->category ?? 'N/A' }}</span>
                </div>
                <p class="mt-3 text-sm text-data-slate line-clamp-3">{{ Str::limit($entry->content, 200) }}</p>
                @if($entry->tags)
                    <div class="mt-3 flex flex-wrap gap-1">
                        @foreach(is_array($entry->tags) ? $entry->tags : (json_decode($entry->tags, true) ?? []) as $tag)
                            <span class="px-2 py-0.5 bg-white/5 rounded text-[10px] text-data-slate">{{ $tag }}</span>
                        @endforeach
                    </div>
                @endif
            </a>
        @empty
            <div class="col-span-full text-center py-12 text-data-slate">No entries found.</div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $entries->appends(request()->query())->links() }}
    </div>
</div>
@endsection
