<?php

function main(): void {
    echo "Starting database backup process...\n";
    echo "Running as user: " . get_current_user() . "\n";
    $accessInfo = json_decode(file_get_contents('access.json'), true);
    if ($accessInfo === null) {
        echo "Error reading access.json: " . json_last_error_msg() . "\n";
        throw new Exception("Failed to read access.json: " . json_last_error_msg());
    }
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Error parsing access.json: " . json_last_error_msg() . "\n";
        throw new Exception("Failed to parse access.json: " . json_last_error_msg());
    }
    if (empty($accessInfo)) {
        echo "No database access information found in access.json.\n";
        throw new Exception("No database access information found in access.json.");
    }
    echo "Access information loaded successfully.\n";
    echo "Found " . count($accessInfo) . " databases to back up.\n";
    if (!file_exists('~/.backup.cnf')) {
        echo "Backup configuration file ~/.backup.cnf does not exist.\n";
        throw new Exception("Backup configuration file ~/.backup.cnf does not exist.");
    }
    echo "Backup configuration file found.\n";
    echo "Starting backup for each database...\n";

    foreach ($accessInfo as $db) {
        $port = $db['port'];
        $host = $db['host'];
        $dbName = $db['db_name'];

        try {
            backupDatabase($port, $host, $dbName);
        } catch (Exception $e) {
            echo "Error backing up {$dbName}: {$e->getMessage()}\n";
        }
    }
}

function backupDatabase(int $port, string $host, string $dbName): void {
    echo "Backing up database: {$dbName} on host: {$host} at port: {$port}...\n";
    $command = sprintf(
        'mysqldump --defaults-extra-file=~/.backup.cnf -P %d -h %s %s --no-tablespaces',
        $port,
        escapeshellarg($host),
        escapeshellarg($dbName)
    );

    $datestamp = date('Y-m-d');
    $backupFile = "{$dbName}_{$datestamp}.sql.gz";

    $fullCommand = "{$command} | gzip > " . escapeshellarg($backupFile);

    exec($fullCommand, $output, $returnCode);

    if ($returnCode !== 0) {
        throw new Exception("Backup command failed with code {$returnCode}");
    }

    echo "Backup for database: {$dbName} completed: {$backupFile}.\n";
}

main();
