<?php
$db = __DIR__ . '/../database/database.sqlite';
if (!file_exists($db)) {
    echo "NO_SQLITE_FILE\n";
    exit(0);
}
try {
    $pdo = new PDO('sqlite:' . $db);
    $stmt = $pdo->prepare('SELECT p.id AS player_id, p.first_name, p.last_name, ps.season_id, s.year as season_year, s.label as season_label, ps.points, ps.rebounds, ps.assists FROM players p JOIN player_season_stats ps ON ps.player_id = p.id JOIN seasons s ON ps.season_id = s.id WHERE p.last_name LIKE ? ORDER BY s.year DESC LIMIT 50');
    $stmt->execute(['%Doncic%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
}
