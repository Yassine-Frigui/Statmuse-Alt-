<x-app-layout>
<div class="flex flex-col items-center justify-start min-h-screen px-4 pt-24">
    <h1 class="text-5xl font-bold text-gray-900 tracking-tight mb-2">NBA Query Engine</h1>
    <p class="text-gray-500 mb-8">Ask anything about NBA stats</p>

    <form id="search-form" class="w-full max-w-2xl mb-8">
        @csrf
        <input type="text" id="search-input" autofocus
            class="w-full px-5 py-3.5 text-base border border-gray-300 rounded-xl shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none placeholder-gray-400"
            placeholder='Ask about NBA stats, players, teams...'>
        <div class="flex justify-center mt-4 gap-3">
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 text-sm">Search</button>
            <button type="button" id="lucky-btn" class="px-6 py-2.5 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 text-sm">I'm Feeling Lucky</button>
        </div>
    </form>

    <div id="results" class="w-full max-w-4xl hidden">
        <div class="mb-4 p-4 bg-white border border-gray-200 rounded-xl shadow-sm" id="answer-box"></div>
        <div class="mb-4 p-4 bg-white border border-gray-200 rounded-xl shadow-sm overflow-x-auto hidden" id="table-box">
            <table class="w-full text-sm">
                <thead><tr id="table-header" class="border-b border-gray-200"></tr></thead>
                <tbody id="table-body"></tbody>
            </table>
        </div>
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm hidden" id="error-box"></div>
    </div>

    <div class="flex gap-4 text-sm text-gray-400 mt-4 flex-wrap justify-center">
        <span class="cursor-pointer hover:text-gray-600 example-query">Most points in NBA Finals history</span>
        <span class="cursor-pointer hover:text-gray-600 example-query">Who won the 1998 NBA Finals?</span>
        <span class="cursor-pointer hover:text-gray-600 example-query">Tell me about Michael Jordan</span>
        <span class="cursor-pointer hover:text-gray-600 example-query">Explain the ABA-NBA merger</span>
    </div>
</div>

@push('scripts')
<script>
const form = document.getElementById('search-form');
const input = document.getElementById('search-input');
const results = document.getElementById('results');
const answerBox = document.getElementById('answer-box');
const tableBox = document.getElementById('table-box');
const tableHeader = document.getElementById('table-header');
const tableBody = document.getElementById('table-body');
const errorBox = document.getElementById('error-box');

const queries = [
    'Most points in NBA Finals history',
    'Who won the 1998 NBA Finals?',
    'Tell me about Michael Jordan',
    'Explain the ABA-NBA merger',
];

function render(data) {
    if (!data || data.length === 0) { tableBox.classList.add('hidden'); return; }
    const keys = Object.keys(data[0]);
    tableHeader.innerHTML = keys.map(k => `<th class="px-4 py-2 text-left font-semibold text-gray-600 text-xs uppercase">${k.replace(/_/g, ' ')}</th>`).join('');
    tableBody.innerHTML = data.map((row, i) => '<tr class="' + (i < data.length - 1 ? 'border-b border-gray-100' : '') + '">' + keys.map(k => '<td class="px-4 py-2 text-gray-800">' + (row[k] ?? '-') + '</td>').join('') + '</tr>').join('');
}

async function search(q) {
    q = q.trim();
    if (!q) return;
    results.classList.remove('hidden');
    answerBox.textContent = 'Searching...';
    errorBox.classList.add('hidden');

    try {
        const res = await fetch('/api/chatbot', { method:'POST', headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':document.querySelector('[name=_token]').value }, body:JSON.stringify({ message:q }) });
        if (!res.ok) { errorBox.textContent = 'Error processing request'; errorBox.classList.remove('hidden'); return; }
        const d = await res.json();
        answerBox.innerHTML = d.reply.replace(/\n/g, '<br>');
        if (d.data && d.data.length > 0) render(d.data);
    } catch(e) { errorBox.textContent = 'Network error'; errorBox.classList.remove('hidden'); }
}

form.addEventListener('submit', e => { e.preventDefault(); search(input.value); });
document.getElementById('lucky-btn').addEventListener('click', () => search(queries[Math.floor(Math.random()*queries.length)]));
document.querySelectorAll('.example-query').forEach(el => el.addEventListener('click', () => search(el.textContent.trim())));
input.addEventListener('keydown', e => { if (e.key === 'Enter') search(input.value); });
</script>
@endpush
</x-app-layout>
