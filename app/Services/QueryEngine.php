<?php

namespace App\Services;

use App\Services\Contracts\LLMProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryEngine
{
    private const SCHEMA_DIR = 'schemas';
    private const DANGEROUS_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE',
        'TRUNCATE', 'EXEC', 'EXECUTE', 'REPLACE', 'GRANT', 'REVOKE',
    ];

    private array $trace = [];

    public function __construct(
        private LLMProvider $llm
    ) {}

    public function ask(string $sport, string $question): array
    {
        $this->trace = [
            'sport' => $sport,
            'question' => $question,
            'steps' => [],
            'provider' => basename(str_replace('\\', '/', get_class($this->llm))),
        ];

        $schema = $this->loadFile("{$sport}_schema.md");
        $rules = $this->loadFile("{$sport}_rules.md");
        $context = $this->loadFile("{$sport}_context.md");

        $this->trace['steps'][] = ['step' => 'files_loaded'];

        $allowedTables = $this->parseTableNames($schema);

        $prompt = $this->buildPrompt($schema, $rules, $context, $question);

        $this->trace['steps'][] = [
            'step' => 'llm_prompt',
            'prompt_length' => strlen($prompt),
        ];

        try {
            $rawResponse = $this->llm->generate($prompt);

            $this->trace['steps'][] = [
                'step' => 'llm_response',
                'raw_response' => $rawResponse,
            ];

            $parsed = $this->parseResponse($rawResponse);

            $this->trace['steps'][] = [
                'step' => 'parsed',
                'type' => $parsed['type'],
            ];

            if ($parsed['type'] === 'unanswerable') {
                return [
                    'reply' => $parsed['reply'],
                    'data' => [],
                    'sql' => null,
                ];
            }

            $sql = $parsed['sql'];
            $this->trace['steps'][] = ['step' => 'sql_clean', 'sql' => $sql];

            $this->validateSql($sql, $allowedTables);

            $this->trace['steps'][] = ['step' => 'sql_validated'];

            $data = DB::select($sql);
            $data = json_decode(json_encode($data), true);

            $this->trace['steps'][] = [
                'step' => 'query_executed',
                'row_count' => count($data),
            ];

            $reply = $this->buildReply($parsed['reply'], $data);

            return [
                'reply' => $reply,
                'data' => $data,
                'sql' => $sql,
            ];
        } catch (\Throwable $e) {
            Log::warning('QueryEngine: error', [
                'error' => $e->getMessage(),
                'sport' => $sport,
            ]);

            $this->trace['steps'][] = [
                'step' => 'error',
                'error' => $e->getMessage(),
            ];

            return [
                'reply' => 'I couldn\'t process that query. Try rephrasing your question.',
                'data' => [],
                'sql' => null,
            ];
        }
    }

    public function getTrace(): array
    {
        return $this->trace;
    }

    private function loadFile(string $filename): string
    {
        $path = base_path(self::SCHEMA_DIR . DIRECTORY_SEPARATOR . $filename);
        if (!file_exists($path)) {
            return '';
        }
        return file_get_contents($path);
    }

    private function parseTableNames(string $schemaContent): array
    {
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $schemaContent, $matches)) {
            $yaml = $matches[1];
            preg_match('/tables:\n((?:  - .*\n?)*)/', $yaml, $tableSection);
            if (!empty($tableSection[1])) {
                preg_match_all('/- (\w+)/', $tableSection[1], $tableMatches);
                return $tableMatches[1] ?? [];
            }
        }
        return [];
    }

    private function buildPrompt(string $schema, string $rules, string $context, string $question): string
    {
        $parts = [
            'You are a database analyst. Given a user\'s question and the database schema below, decide if the question can be answered with the available data.',
            '',
            '=== SCHEMA ===',
            $schema,
        ];

        if ($rules !== '') {
            $parts[] = '';
            $parts[] = '=== RULES ===';
            $parts[] = $rules;
        }

        if ($context !== '') {
            $parts[] = '';
            $parts[] = '=== CONTEXT ===';
            $parts[] = $context;
        }

        $parts[] = '';
        $parts[] = '=== QUESTION ===';
        $parts[] = $question;
        $parts[] = '';
        $parts[] = 'Respond with ONLY valid JSON in this exact format, nothing else:';
        $parts[] = '';
        $parts[] = 'If the question CAN be answered:';
        $parts[] = '{';
        $parts[] = '  "type": "query",';
        $parts[] = '  "sql": "your SQL query here",';
        $parts[] = '  "reply": "One sentence natural language answer template using the results"';
        $parts[] = '}';
        $parts[] = '';
        $parts[] = 'If the question CANNOT be answered (data not in our database, out of range, or unclear):';
        $parts[] = '{';
        $parts[] = '  "type": "unanswerable",';
        $parts[] = '  "reply": "Clear explanation of why and what the user could ask instead"';
        $parts[] = '}';
        $parts[] = '';
        $parts[] = 'Rules for SQL:';
        $parts[] = '- Use ONLY table names from the SCHEMA section above';
        $parts[] = '- Use table.column notation everywhere';
        $parts[] = '- Return a single SELECT query (or WITH ... SELECT)';
        $parts[] = '- No INSERT, UPDATE, DELETE, DROP, ALTER, CREATE, TRUNCATE, EXEC';
        $parts[] = '- Limit results to a reasonable number (default 10)';

        return implode("\n", $parts);
    }

    private function parseResponse(string $raw): array
    {
        $raw = trim($raw);

        if (preg_match('/```(?:json)?\s*\n?(.*?)```/is', $raw, $m)) {
            $raw = trim($m[1]);
        }

        $parsed = json_decode($raw, true);

        if (!is_array($parsed) || !isset($parsed['type'])) {
            $parsed = $this->tryExtractJson($raw);
        }

        if (!is_array($parsed) || !isset($parsed['type'])) {
            return [
                'type' => 'unanswerable',
                'reply' => 'I couldn\'t understand the response. Please try rephrasing your question.',
            ];
        }

        if ($parsed['type'] === 'query') {
            $sql = $this->cleanSql($parsed['sql'] ?? '');
            $reply = $parsed['reply'] ?? 'Here are the results:';
            return ['type' => 'query', 'sql' => $sql, 'reply' => $reply];
        }

        return [
            'type' => 'unanswerable',
            'reply' => $parsed['reply'] ?? 'I don\'t have data to answer that question.',
        ];
    }

    private function tryExtractJson(string $text): ?array
    {
        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded) && isset($decoded['type'])) {
                return $decoded;
            }
        }
        return null;
    }

    private function cleanSql(string $raw): string
    {
        $sql = trim($raw);

        if (preg_match('/```(?:sql)?\s*(\n?.*?)```/is', $sql, $m)) {
            $sql = trim($m[1]);
        }

        $sql = preg_replace('/^(?:#|--).*$/m', '', $sql);
        $sql = trim($sql);

        if (!preg_match('/^(SELECT|WITH)\b/is', $sql)) {
            if (preg_match('/\b(SELECT\b.+?)(?:;?\s*$|(?=\n\s*\n))/is', $sql, $m)) {
                $sql = trim($m[1]);
            } elseif (preg_match('/\b(WITH\b.+?)(?:;?\s*$|(?=\n\s*\n))/is', $sql, $m)) {
                $sql = trim($m[1]);
            }
        }

        $sql = rtrim($sql, ";\n\r\t ");
        $sql .= ';';

        return $sql;
    }

    private function validateSql(string $sql, array $allowedTables): void
    {
        $upper = strtoupper($sql);

        $firstWord = strtoupper(trim(preg_split('/\s+/', trim($sql))[0] ?? ''));
        if (!in_array($firstWord, ['SELECT', 'WITH'], true)) {
            throw new \InvalidArgumentException('SQL must start with SELECT or WITH');
        }

        foreach (self::DANGEROUS_KEYWORDS as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/', $upper)) {
                throw new \InvalidArgumentException("SQL contains forbidden keyword: {$keyword}");
            }
        }

        preg_match_all('/\b(?:FROM|JOIN)\s+`?(\w+)`?\b/i', $sql, $matches);
        $usedTables = array_unique($matches[1]);

        foreach ($usedTables as $table) {
            $baseTable = explode(' as ', strtolower($table))[0];
            $baseTable = explode(' AS ', $baseTable)[0];
            $baseTable = trim($baseTable);

            if (!in_array($baseTable, $allowedTables, true)) {
                throw new \InvalidArgumentException("Table not in schema allowlist: {$baseTable}");
            }
        }
    }

    private function buildReply(string $replyTemplate, array $data): string
    {
        if (empty($data)) {
            return $replyTemplate . "\n\nNo results found.";
        }

        $headers = array_keys($data[0]);
        $table = '';

        $table .= '| ' . implode(' | ', array_map(
            fn($h) => str_replace('_', ' ', ucfirst(strtolower((string) $h))),
            $headers
        )) . " |\n";

        $table .= '| ' . implode(' | ', array_fill(0, count($headers), '---')) . " |\n";

        $displayLimit = min(count($data), 20);
        for ($i = 0; $i < $displayLimit; $i++) {
            $vals = array_map(fn($h) => $this->formatCell($data[$i][$h] ?? ''), $headers);
            $table .= '| ' . implode(' | ', $vals) . " |\n";
        }

        if (count($data) > $displayLimit) {
            $table .= "\n*Showing {$displayLimit} of " . count($data) . " results*\n";
        }

        return $replyTemplate . "\n\n" . $table;
    }

    private function formatCell(mixed $value): string
    {
        if ($value === null || $value === '') return '-';
        if (is_float($value)) return number_format($value, 2, '.', '');
        if (is_int($value)) return number_format($value);
        return (string) $value;
    }
}
