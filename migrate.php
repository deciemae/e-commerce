<?php

require_once __DIR__ . '/includes/setup_guard.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$command = $argv[1] ?? '--status';
if (!in_array($command, ['--status', '--apply'], true)) {
    fwrite(STDERR, "Usage: php migrate.php [--status|--apply]\n");
    exit(1);
}

if ($command === '--apply') {
    requireCommandLineMigrationApproval();
}

require_once __DIR__ . '/config/db.php';

$migrationDirectory = __DIR__ . '/database/migrations';
$migrationFiles = glob($migrationDirectory . '/*.sql') ?: [];
sort($migrationFiles, SORT_STRING);

foreach ($migrationFiles as $migrationFile) {
    if (!preg_match('/^\d{12}_[a-z0-9_]+\.sql$/', basename($migrationFile))) {
        fwrite(STDERR, 'Invalid migration filename: ' . basename($migrationFile) . "\n");
        exit(1);
    }
}

$conn = getConnection();
$trackingTableExists = (int)($conn->query(
    "SELECT COUNT(*) AS total
     FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'"
)->fetch_assoc()['total'] ?? 0) === 1;

if ($command === '--apply' && !$trackingTableExists) {
    $trackingSql = 'CREATE TABLE schema_migrations (
        version VARCHAR(64) NOT NULL PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        checksum CHAR(64) NOT NULL,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

    if (!$conn->query($trackingSql)) {
        fwrite(STDERR, "Unable to create schema_migrations.\n");
        $conn->close();
        exit(1);
    }
    $trackingTableExists = true;
}

$applied = [];
if ($trackingTableExists) {
    $result = $conn->query('SELECT version, checksum FROM schema_migrations ORDER BY version ASC');
    foreach ($result ? $result->fetch_all(MYSQLI_ASSOC) : [] as $row) {
        $applied[(string)$row['version']] = (string)$row['checksum'];
    }
}

$pending = [];
foreach ($migrationFiles as $migrationFile) {
    $filename = basename($migrationFile);
    $version = substr($filename, 0, 12);
    $checksum = hash_file('sha256', $migrationFile);

    if (isset($applied[$version])) {
        if (!hash_equals($applied[$version], $checksum)) {
            fwrite(STDERR, "Applied migration checksum mismatch: {$filename}\n");
            $conn->close();
            exit(1);
        }
        continue;
    }

    $pending[] = compact('migrationFile', 'filename', 'version', 'checksum');
}

if ($command === '--status') {
    echo count($migrationFiles) . ' migration(s), ' . count($pending) . " pending.\n";
    foreach ($pending as $migration) {
        echo 'PENDING ' . $migration['filename'] . "\n";
    }
    $conn->close();
    exit(0);
}

foreach ($pending as $migration) {
    $sql = file_get_contents($migration['migrationFile']);
    if ($sql === false || trim($sql) === '') {
        fwrite(STDERR, 'Unable to read migration: ' . $migration['filename'] . "\n");
        $conn->close();
        exit(1);
    }

    if (!$conn->multi_query($sql)) {
        fwrite(STDERR, 'Migration failed: ' . $migration['filename'] . "\n");
        $conn->close();
        exit(1);
    }

    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());

    if ($conn->errno) {
        fwrite(STDERR, 'Migration failed: ' . $migration['filename'] . "\n");
        $conn->close();
        exit(1);
    }

    $stmt = $conn->prepare('INSERT INTO schema_migrations (version, filename, checksum) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $migration['version'], $migration['filename'], $migration['checksum']);
    if (!$stmt->execute()) {
        fwrite(STDERR, 'Unable to record migration: ' . $migration['filename'] . "\n");
        $stmt->close();
        $conn->close();
        exit(1);
    }
    $stmt->close();
    echo 'APPLIED ' . $migration['filename'] . "\n";
}

if (!$pending) {
    echo "No pending migrations.\n";
}

$conn->close();
