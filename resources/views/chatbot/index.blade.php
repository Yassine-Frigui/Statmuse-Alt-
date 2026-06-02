@extends('layouts.nba')

@section('title', 'Natural Language Basketball Query')

@push('head')
    <meta name="description" content="Ask any basketball question in plain English. Powered by Gemini + Laravel + MySQL.">
    <meta property="og:title" content="Engine.NBA — NBA Query Engine">
    <meta property="og:description" content="Natural-language access to four decades of NBA stats and history.">
@endpush

@section('content')
<div x-data="chatbot" class="min-h-screen bg-court-black text-white">
    <main class="max-w-5xl mx-auto px-6 py-12 pb-24">
        <header class="mb-8">
            <h1 class="font-display italic font-black text-5xl md:text-6xl uppercase tracking-tight leading-none">
                Court <span class="text-hoop-orange">Intelligence</span>
            </h1>
            <p class="text-data-slate mt-3 max-w-2xl">
                Natural-language access to four decades of NBA box scores, championships, awards, and history. Powered by Gemini + Laravel.
            </p>
        </header>

        {{-- QueryInput --}}
        <form @submit.prevent="runQuery(questionInput); questionInput = ''" class="relative group">
            <div class="absolute -inset-1 bg-gradient-to-r from-hoop-orange/20 to-blue-500/20 rounded-2xl blur opacity-25 group-focus-within:opacity-100 transition duration-1000"></div>
            <div class="relative bg-court-dark border border-white/10 rounded-xl shadow-2xl overflow-hidden">
                <div class="flex items-center p-4 gap-4">
                    <div class="text-hoop-orange">
                        <div class="size-3 rounded-full bg-current" :class="{ 'animate-pulse': loading }"></div>
                    </div>
                    <input
                        x-ref="input"
                        x-model="questionInput"
                        type="text"
                        maxlength="500"
                        :disabled="loading"
                        class="bg-transparent border-none outline-none w-full text-lg placeholder:text-data-slate/50 font-medium disabled:opacity-60"
                        placeholder="Ask a basketball question (e.g. 'Compare Shaq and Hakeem in the 1995 Finals')"
                    />
                    <button
                        type="submit"
                        :disabled="loading || !(questionInput && questionInput.trim())"
                        class="bg-hoop-orange hover:bg-hoop-orange/90 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-2 px-6 rounded-lg text-sm transition-all transform active:scale-95"
                    >
                        <span x-show="!loading">RUN QUERY</span>
                        <span x-show="loading">RUNNING...</span>
                    </button>
                </div>
            </div>
        </form>

        {{-- Example chips --}}
        <template x-if="!result && !error">
            <div class="mt-6 flex flex-wrap gap-2">
                <template x-for="ex in examples" :key="ex">
                    <button
                        @click="runQuery(ex)"
                        :disabled="loading"
                        class="text-xs bg-white/5 hover:bg-white/10 disabled:opacity-40 border border-white/10 text-data-slate hover:text-white px-3 py-1.5 rounded-full transition-colors"
                        x-text="ex"
                    ></button>
                </template>
            </div>
        </template>

        {{-- PipelineMonitor --}}
        <div class="mt-8 grid grid-cols-2 gap-4">
            <template x-for="s in pipelineStages" :key="s.key">
                <div
                    class="bg-court-dark p-3 rounded-r transition-all"
                    :class="{
                        'border-l-2 border-hoop-orange': stageStatus(s.key) === 'active',
                        'border-l-2 border-green-500/60': stageStatus(s.key) === 'done',
                        'border-l-2 border-white/20 opacity-40': stageStatus(s.key) === 'pending'
                    }"
                >
                    <p
                        class="text-[10px] font-bold uppercase tracking-tighter mb-1"
                        :class="{
                            'text-hoop-orange': stageStatus(s.key) === 'active',
                            'text-green-500': stageStatus(s.key) === 'done',
                            'text-white/40': stageStatus(s.key) === 'pending'
                        }"
                        x-text="s.label"
                    ></p>
                    <p
                        class="text-xs truncate"
                        :class="{
                            'text-data-slate': stageStatus(s.key) === 'active',
                            'text-data-slate/60': stageStatus(s.key) !== 'active'
                        }"
                        x-text="stageStatus(s.key) === 'done' ? 'Complete' : (stageStatus(s.key) === 'active' ? s.detail : 'Pending...')"
                    ></p>
                </div>
            </template>
        </div>

        {{-- Error --}}
        <template x-if="error">
            <div class="mt-12 bg-red-950/30 border border-red-500/30 rounded-xl p-6">
                <h3 class="font-display italic font-bold text-xl uppercase text-red-400 mb-2">Pipeline Failure</h3>
                <p class="text-sm text-data-slate font-mono break-all" x-text="error"></p>
            </div>
        </template>

        {{-- ResultCard --}}
        <template x-if="result">
            <div class="mt-12 flex flex-col md:flex-row gap-8 items-start">
                <div class="flex-1 w-full min-w-0 bg-court-dark border border-white/5 rounded-2xl overflow-hidden">
                    <div class="p-8">
                        <div class="flex flex-col md:flex-row md:justify-between md:items-start mb-6 gap-4">
                            <div>
                                <h2 class="text-3xl font-display italic font-black uppercase leading-none tracking-tight">Query Result</h2>
                                <p class="text-hoop-orange font-medium mt-1 line-clamp-2" x-text="question"></p>
                            </div>
                            <template x-if="result.conversation_id !== undefined">
                                <div class="text-right shrink-0">
                                    <p class="text-xs uppercase font-bold text-data-slate tracking-widest">Conversation</p>
                                    <p class="font-display italic text-2xl font-bold" x-text="'#' + result.conversation_id"></p>
                                </div>
                            </template>
                        </div>

                        <p class="text-data-slate leading-relaxed mb-8 whitespace-pre-wrap" x-text="result.reply"></p>

                        <template x-if="rows.length > 0">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm border-separate border-spacing-y-2">
                                    <thead>
                                        <tr class="text-data-slate font-bold text-[10px] uppercase tracking-wider">
                                            <template x-for="h in headers" :key="h">
                                                <th class="px-4 py-2" x-text="h.replace(/_/g, ' ')"></th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(row, i) in rows" :key="i">
                                            <tr class="bg-white/[0.02] hover:bg-white/5 transition-colors">
                                                <template x-for="(h, j) in headers" :key="h">
                                                    <td
                                                        class="px-4 py-3 border-t border-b border-white/5"
                                                        :class="{
                                                            'rounded-l-lg border-l': j === 0,
                                                            'rounded-r-lg border-r': j === headers.length - 1,
                                                            'font-bold': typeof row[h] === 'number'
                                                        }"
                                                        x-text="formatCell(row[h])"
                                                    ></td>
                                                </template>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>
                    <div class="bg-white/5 border-t border-white/5 px-8 py-4 flex justify-between items-center">
                        <span class="text-xs text-data-slate">Data source: data.nba.com CDN + Historical CSV</span>
                        <template x-if="rows.length > 0">
                            <button @click="downloadCsv()" class="text-xs font-bold uppercase tracking-widest text-hoop-orange hover:underline">Download CSV</button>
                        </template>
                    </div>
                </div>

                <aside class="w-full md:w-64 space-y-6 shrink-0">
                    <template x-if="result.entities && result.entities.length > 0">
                        <div class="bg-court-dark border border-white/5 rounded-xl p-5">
                            <h4 class="text-[10px] font-bold uppercase tracking-widest text-data-slate mb-4">Entities Identified</h4>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="e in result.entities" :key="e">
                                    <span class="px-2 py-1 bg-white/5 border border-white/10 rounded text-[11px] font-medium text-white" x-text="e"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                    <template x-if="result.intent">
                        <div class="bg-court-dark border border-white/5 rounded-xl p-5">
                            <h4 class="text-[10px] font-bold uppercase tracking-widest text-data-slate mb-4">Intent</h4>
                            <code class="text-xs text-hoop-orange break-all" x-text="result.intent"></code>
                        </div>
                    </template>
                    <div class="bg-court-dark border border-white/5 rounded-xl p-5">
                        <h4 class="text-[10px] font-bold uppercase tracking-widest text-data-slate mb-4">What-If Scenarios</h4>
                        <ul class="space-y-3">
                            <li><a href="{{ route('scenarios.index') }}" class="text-xs text-hoop-orange hover:underline block cursor-pointer">Adjust for modern pace?</a></li>
                            <li><a href="{{ route('scenarios.index') }}" class="text-xs text-hoop-orange hover:underline block cursor-pointer">Compare with another era?</a></li>
                        </ul>
                    </div>
                </aside>
            </div>
        </template>

        {{-- EngineTrace --}}
        <template x-if="result && result.debug">
            <div class="mt-8 bg-court-dark border border-white/10 rounded-xl overflow-hidden">
                <button @click="showDebug = !showDebug" class="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-white/5 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="size-2 rounded-full" :class="result.debug.pipeline === 'local' ? 'bg-amber-500' : 'bg-green-500'"></div>
                        <span class="font-display text-sm font-bold uppercase tracking-wide">Engine Trace</span>
                        <span class="text-[10px] text-data-slate font-mono" x-text="'via ' + result.debug.pipeline"></span>
                    </div>
                    <svg class="size-4 text-data-slate transition-transform" :class="{ 'rotate-180': showDebug }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="showDebug" x-cloak class="border-t border-white/5 divide-y divide-white/5">
                    <template x-for="(step, i) in result.debug.steps" :key="i">
                        <div class="px-6 py-3">
                            <div class="flex items-start gap-3">
                                <span class="text-[10px] font-mono text-data-slate mt-0.5 shrink-0" x-text="'#' + i"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold text-hoop-orange font-mono" x-text="step.step"></p>
                                    <template x-if="step.error">
                                        <p class="text-[11px] text-red-400 font-mono mt-1 break-all" x-text="step.error"></p>
                                    </template>
                                    <template x-if="step.prompt_sent">
                                        <div class="mt-2">
                                            <p class="text-[10px] text-data-slate uppercase tracking-wider mb-1">Gemini Prompt:</p>
                                            <pre class="text-[11px] text-data-slate/80 font-mono whitespace-pre-wrap max-h-48 overflow-y-auto bg-black/20 rounded p-2" x-text="truncate(step.prompt_sent, 800)"></pre>
                                        </div>
                                    </template>
                                    <template x-if="step.raw_response">
                                        <div class="mt-2">
                                            <p class="text-[10px] text-data-slate uppercase tracking-wider mb-1">Gemini Response:</p>
                                            <pre class="text-[11px] text-data-slate/80 font-mono whitespace-pre-wrap max-h-48 overflow-y-auto bg-black/20 rounded p-2" x-text="truncate(step.raw_response, 600)"></pre>
                                        </div>
                                    </template>
                                    <template x-if="step.parsed">
                                        <div class="mt-2">
                                            <p class="text-[10px] text-data-slate uppercase tracking-wider mb-1">Parsed:</p>
                                            <pre class="text-[11px] text-green-400/80 font-mono whitespace-pre-wrap max-h-48 overflow-y-auto bg-black/20 rounded p-2" x-text="formatJson(step.parsed)"></pre>
                                        </div>
                                    </template>
                                    <template x-if="step.players">
                                        <p class="text-[11px] text-data-slate mt-1">Players: <span class="text-white" x-text="step.players.join(', ') || 'none'"></span></p>
                                    </template>
                                    <template x-if="step.teams">
                                        <p class="text-[11px] text-data-slate mt-1">Teams: <span class="text-white" x-text="step.teams.join(', ') || 'none'"></span></p>
                                    </template>
                                    <template x-if="step.type">
                                        <p class="text-[11px] text-data-slate mt-1">Type: <span class="text-white font-mono" x-text="step.type"></span></p>
                                    </template>
                                    <template x-if="step.table">
                                        <p class="text-[11px] text-data-slate mt-1">Table: <span class="text-white font-mono" x-text="step.table"></span></p>
                                    </template>
                                    <template x-if="step.metric">
                                        <p class="text-[11px] text-data-slate mt-1">Metric: <span class="text-white font-mono" x-text="step.metric"></span></p>
                                    </template>
                                    <template x-if="step.year !== undefined">
                                        <p class="text-[11px] text-data-slate mt-1">Year: <span class="text-white font-mono" x-text="step.year === null ? 'none detected' : step.year"></span></p>
                                    </template>
                                    <template x-if="step.row_count !== undefined">
                                        <p class="text-[11px] text-data-slate mt-1">Rows returned: <span class="text-white font-mono" x-text="step.row_count"></span></p>
                                    </template>
                                    <template x-if="step.query_structure">
                                        <div class="mt-2">
                                            <p class="text-[10px] text-data-slate uppercase tracking-wider mb-1">Query Structure:</p>
                                            <pre class="text-[11px] text-sky-400/80 font-mono whitespace-pre-wrap max-h-48 overflow-y-auto bg-black/20 rounded p-2" x-text="formatJson(step.query_structure)"></pre>
                                        </div>
                                    </template>
                                    <template x-if="step.sql">
                                        <div class="mt-2">
                                            <p class="text-[10px] text-data-slate uppercase tracking-wider mb-1">Generated SQL:</p>
                                            <pre class="text-[11px] text-emerald-400/80 font-mono whitespace-pre-wrap bg-black/20 rounded p-2" x-text="step.sql"></pre>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </main>

    {{-- StatusFooter --}}
    <footer class="fixed bottom-0 w-full bg-court-dark/80 backdrop-blur-lg border-t border-white/5 px-6 py-3 z-40" x-show="stage !== 'idle'">
        <div class="max-w-7xl mx-auto flex justify-between items-center text-[10px] font-bold tracking-widest text-data-slate">
            <div class="flex gap-6 uppercase">
                <span class="flex items-center gap-2">
                    <div class="size-2 rounded-full" :class="{ 'bg-red-500': stage === 'error', 'bg-green-500': stage !== 'error' }"></div>
                    <span x-text="stage === 'error' ? 'API Error' : 'API Operational'"></span>
                </span>
                <template x-if="latency !== null">
                    <span x-text="'Latency: ' + latency + 'ms'"></span>
                </template>
            </div>
            <div class="uppercase">&copy; 2024 Hardwood Engine Lab</div>
        </div>
    </footer>
</div>
@endsection

@push('head')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('chatbot', () => ({
        stage: 'idle',
        question: '',
        result: null,
        error: null,
        latency: null,
        timer1: null,
        timer2: null,
        timer3: null,
        questionInput: '',
        showDebug: false,

        truncate(s, max) { return !s ? '' : s.length > max ? s.slice(0, max) + '...' : s; },

        formatJson(obj) { try { return JSON.stringify(obj, null, 2); } catch(e) { return String(obj); } },

        examples: [
            'Top 10 scorers all-time',
            'Who won the NBA championship in 1998?',
            'Compare Michael Jordan and LeBron James rings',
            'Lakers vs Celtics head to head 2023',
            'List of MVP winners in the 2010s',
        ],

        pipelineStages: [
            { key: 'analyzing', label: '1. Gemini Analysis', detail: 'Schema-aware query planning' },
            { key: 'formatting', label: '2. Formatter', detail: 'Building final reply' },
        ],

        get loading() {
            return this.stage !== 'idle' && this.stage !== 'done' && this.stage !== 'error';
        },

        get rows() {
            if (!this.result || !this.result.data) return [];
            return Array.isArray(this.result.data) ? this.result.data : [this.result.data];
        },

        get headers() {
            return this.rows.length > 0 ? Object.keys(this.rows[0]) : [];
        },

        stageStatus(key) {
            const order = ['analyzing', 'formatting', 'done'];
            const idx = order.indexOf(key);
            const activeIdx = order.indexOf(this.stage);
            if (this.stage === 'done') return 'done';
            if (this.stage === 'idle' || this.stage === 'error') return 'pending';
            if (idx === activeIdx) return 'active';
            if (activeIdx > idx) return 'done';
            return 'pending';
        },

        clearAllTimers() {
            if (this.timer1) { clearTimeout(this.timer1); this.timer1 = null; }
            if (this.timer2) { clearTimeout(this.timer2); this.timer2 = null; }
        },

        async runQuery(q) {
            this.clearAllTimers();
            this.question = q;
            this.result = null;
            this.error = null;
            this.stage = 'analyzing';

            this.timer1 = setTimeout(() => { this.stage = 'formatting'; }, 1000);

            const start = performance.now();
            try {
                const res = await fetch('/api/chatbot', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ message: q }),
                });
                if (!res.ok) {
                    const text = await res.text();
                    throw new Error('Chatbot API error ' + res.status + ': ' + text.slice(0, 200));
                }
                const data = await res.json();
                this.latency = Math.round(performance.now() - start);
                this.result = data;
                this.stage = 'done';
            } catch (err) {
                this.error = err.message || 'Unknown error';
                this.stage = 'error';
            } finally {
                this.clearAllTimers();
                this.questionInput = '';
            }
        },

        formatCell(v) {
            if (v === null || v === undefined) return '\u2014';
            if (typeof v === 'number') return Number.isInteger(v) ? v.toString() : v.toFixed(2);
            if (typeof v === 'object') return JSON.stringify(v);
            return String(v);
        },

        downloadCsv() {
            const rows = this.rows;
            if (!rows.length) return;
            const headers = Object.keys(rows[0]);
            const esc = (s) => JSON.stringify(s);
            const csv = [
                headers.map(esc).join(','),
                ...rows.map((r) => headers.map((h) => esc(this.formatCell(r[h]))).join(',')),
            ].join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'query-' + (this.result?.conversation_id ?? Date.now()) + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        },
    }));
});
</script>
@endpush
