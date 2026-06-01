@extends('layouts.nba')

@section('title', $corpusEntry->title)

@section('content')
<div class="max-w-4xl mx-auto px-6 py-12">
    <a href="{{ route('corpus.index') }}" class="text-data-slate hover:text-white text-xs font-bold uppercase tracking-widest transition-colors">&larr; Corpus</a>

    <div class="mt-4 bg-court-dark border border-white/5 rounded-2xl overflow-hidden">
        <div class="px-8 py-6 border-b border-white/5">
            <div class="flex items-center gap-2 mb-2">
                <span class="px-2 py-1 bg-white/5 rounded text-[10px] text-data-slate font-medium uppercase">{{ $corpusEntry->category ?? 'Uncategorized' }}</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-black uppercase tracking-tight">{{ $corpusEntry->title }}</h1>
        </div>
        <div class="px-8 py-6">
            <div class="text-data-slate leading-relaxed whitespace-pre-wrap">{{ $corpusEntry->content }}</div>

            @if($corpusEntry->tags)
                <div class="mt-8 pt-6 border-t border-white/5">
                    <h3 class="font-display text-sm font-bold uppercase tracking-wide mb-3">Tags</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach(is_array($corpusEntry->tags) ? $corpusEntry->tags : (json_decode($corpusEntry->tags, true) ?? []) as $tag)
                            <span class="px-3 py-1 bg-white/5 border border-white/10 rounded-full text-xs text-data-slate">{{ $tag }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
