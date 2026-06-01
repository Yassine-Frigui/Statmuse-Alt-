<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ResponseFormatterService
{
    public function __construct(
        private GeminiService $gemini
    ) {}

    public function format(array $structuredQuery, Collection $data, string $originalQuestion): string
    {
        if ($data->isEmpty()) {
            return "No data found for your query. Try rephrasing or asking about NBA players, teams, seasons, or championships.";
        }

        $systemPrompt = $this->buildSystemPrompt();
        try {
            $response = $this->gemini->format($systemPrompt, [
                'structured_query' => $structuredQuery,
                'data' => $data->toArray(),
            ], $originalQuestion);

            // Defensive: ensure response is a string
            if (!is_string($response) || trim($response) === '') {
                throw new \RuntimeException('Empty formatter response');
            }

            return $response;
        } catch (\Throwable $e) {
            Log::warning('ResponseFormatterService: Gemini format failed, falling back to local formatter', [
                'error' => $e->getMessage(),
                'structured_query' => $structuredQuery,
            ]);

            // Create a simple table fallback. Use first row keys as columns.
            $rows = $data->map(fn($r) => (array) $r)->toArray();
            $columns = [];
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
            }

            $title = $structuredQuery['intent_type'] ?? 'Results';

            return $this->formatSimple($title, $rows, $columns);
        }
    }

    public function formatSimple(string $title, array $rows, array $columns): string
    {
        $output = "**{$title}**\n\n";

        $header = implode(' | ', array_map(fn($col) => str_replace('_', ' ', ucfirst($col)), $columns));
        $separator = implode(' | ', array_fill(0, count($columns), '---'));
        $output .= "| {$header} |\n| {$separator} |\n";

        foreach ($rows as $row) {
            $values = array_map(fn($col) => $row[$col] ?? '-', $columns);
            $output .= '| ' . implode(' | ', $values) . " |\n";
        }

        return $output;
    }

    private function buildSystemPrompt(): string
    {
        return <<<PROMPT
You are an NBA data formatter. Present the retrieved data in a clear, natural, and engaging way.

Rules:
- Be concise but informative
- Use Markdown for structure (bold for names/numbers, lists for multiple items)
- Include relevant context (year, team, competition)
- If the data is a ranking, present it as a numbered list
- If comparing players/teams, highlight the key differences
- Never add information that isn't in the provided data
- Keep responses under 200 words unless the data is complex

Format stats naturally:
- Points: "23.5 PPG"
- Rebounds: "10.2 RPG"
- Assists: "8.1 APG"
- Percentages: "47.5% FG, 36.2% 3PT"
PROMPT;
    }
}
