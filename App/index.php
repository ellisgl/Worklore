<?php
$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_NAME'));
try {
    $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASSWORD'));
    $db = $pdo->query('SELECT version()')->fetchColumn();
} catch (PDOException $e) {
    $db = 'Connection failed: ' . $e->getMessage();
}
?>
<!doctype html>
<title>App</title>
<h1>It works</h1>
<p>PHP <?= PHP_VERSION ?></p>
<p><?= htmlspecialchars($db) ?></p>
