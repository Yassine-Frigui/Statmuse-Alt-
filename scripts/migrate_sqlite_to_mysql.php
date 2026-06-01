<?php

// migrate_sqlite_to_mysql.php
// Usage:
// php migrate_sqlite_to_mysql.php --dry-run
// php migrate_sqlite_to_mysql.php --execute --preserve-ids

$opts = array_slice($argv, 1);
$flags = [];
foreach ($opts as $o) {
    if (str_starts_with($o, '--')) {
        $kv = explode('=', substr($o, 2), 2);
        $k = $kv[0];
        $v = $kv[1] ?? true;
        $flags[$k] = $v;
    }
}

$dryRun = isset($flags['dry-run']) || !isset($flags['execute']);
$preserveIds = isset($flags['preserve-ids']) ? filter_var($flags['preserve-ids'], FILTER_VALIDATE_BOOLEAN) : true;

// Utility: read .env to load DB_* values
function readEnv($path)
{
    if (!file_exists($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        // strip quotes
        if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
            $v = substr($v, 1, -1);
        }
        $env[$k] = $v;
    }
    return $env;
}

$projectRoot = dirname(__DIR__);
$envPath = $projectRoot . '/.env';
$env = readEnv($envPath);

$sqliteFile = $projectRoot . '/database/database.sqlite';
if (!file_exists($sqliteFile)) {
    echo "ERROR: SQLite file not found at {$sqliteFile}\n";
    exit(1);
}

$mysqlHost = $env['DB_HOST'] ?? '127.0.0.1';
$mysqlPort = $env['DB_PORT'] ?? '3306';
$mysqlDb = $env['DB_DATABASE'] ?? 'stat_muse';
$mysqlUser = $env['DB_USERNAME'] ?? 'root';
$mysqlPass = $env['DB_PASSWORD'] ?? '';

echo "Migration script\n";
echo "Dry run: " . ($dryRun ? 'YES' : 'NO') . "\n";
echo "Preserve IDs: " . ($preserveIds ? 'YES' : 'NO') . "\n";

echo "Using SQLite: {$sqliteFile}\n";
echo "Target MySQL: {$mysqlUser}@{$mysqlHost}:{$mysqlPort}/{$mysqlDb}\n\n";

try {
    $sqlite = new PDO('sqlite:' . $sqliteFile);
    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    echo "Failed to open sqlite: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    $dsn = "mysql:host={$mysqlHost};port={$mysqlPort};dbname={$mysqlDb};charset=utf8mb4";
    $mysql = new PDO($dsn, $mysqlUser, $mysqlPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    echo "Failed to connect to MySQL: " . $e->getMessage() . "\n";
    exit(1);
}

// Tables in dependency order (best-effort based on migrations)
$tables = [
    'seasons',
    'teams',
    'players',
    'coaches',
    'awards',
    'championships',
    'player_awards',
    'player_season_stats',
    'games',
    'game_player_stats',
    'team_season_coach',
    'corpus_entries',
    'ingestion_logs',
    'conversations',
    'what_if_scenarios'
];

// Validate which tables actually exist in sqlite
$existingTablesStmt = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table'");
$existing = $existingTablesStmt->fetchAll(PDO::FETCH_COLUMN);
$existing = array_map('strtolower', $existing);

$toProcess = array_values(array_filter($tables, fn($t) => in_array($t, $existing)));

if (empty($toProcess)) {
    echo "No known tables found in sqlite. Existing tables: " . implode(', ', $existing) . "\n";
    exit(1);
}

// Dry-run: report counts
echo "SQLite -> MySQL row counts (dry-run)\n";
$report = [];
foreach ($toProcess as $t) {
    $s = $sqlite->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    try {
        $m = $mysql->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    } catch (Throwable $e) {
        $m = 'N/A';
    }
    $report[$t] = ['sqlite' => (int)$s, 'mysql' => is_numeric($m) ? (int)$m : $m];
    echo str_pad($t, 25) . " sqlite: " . $s . "  mysql: " . $m . "\n";
}

if ($dryRun) {
    echo "\nDry-run complete. To execute the migration, re-run with --execute and optionally --preserve-ids=(1|0)\n";
    exit(0);
}

// Execute migration
echo "\nStarting migration (this will write to MySQL).\n";

try {
    // Disable foreign key checks in MySQL
    $mysql->exec('SET FOREIGN_KEY_CHECKS=0');
    $mysql->beginTransaction();

    foreach ($toProcess as $t) {
        echo "Processing table: {$t}\n";
        // fetch rows from sqlite
        $rows = $sqlite->query("SELECT * FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            echo "  no rows to copy\n";
            continue;
        }

        // get columns
        $cols = array_keys($rows[0]);
        $colList = implode(', ', array_map(fn($c) => "`{$c}`", $cols));
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));

        // Build ON DUPLICATE KEY UPDATE clause for upsert
        $updates = implode(', ', array_map(fn($c) => "`{$c}`=VALUES(`{$c}`)", $cols));

        $insertSql = "INSERT INTO `{$t}` ({$colList}) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE {$updates}";
        $stmt = $mysql->prepare($insertSql);

        $inserted = 0;
        foreach ($rows as $r) {
            $values = array_values($r);
            try {
                $stmt->execute($values);
                $inserted++;
            } catch (Throwable $e) {
                echo "  failed to insert row: " . $e->getMessage() . "\n";
            }
        }

        echo "  inserted/updated: {$inserted}\n";
    }

    $mysql->commit();
    $mysql->exec('SET FOREIGN_KEY_CHECKS=1');
    echo "Migration complete.\n";
} catch (Throwable $e) {
    $mysql->rollBack();
    echo "Migration failed: " . $e->getMessage() . "\n";
    $mysql->exec('SET FOREIGN_KEY_CHECKS=1');
    exit(1);
}

