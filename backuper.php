<?php

function main(): void {
    echo "Starting database backup process...\n";
    $user = get_current_user();
    echo "Running as user: " . $user . "\n";
    $accessInfo = json_decode(file_get_contents('access.json'), true);
    if ($accessInfo === null) {
        throw new Exception("Failed to read access.json: " . json_last_error_msg());
    }
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to parse access.json: " . json_last_error_msg());
    }
    if (empty($accessInfo)) {
        throw new Exception("No database access information found in access.json.");
    }
    echo "Access information loaded successfully.\n";
    echo "Found " . count($accessInfo) . " databases to back up.\n";
    $cnfPath = '/home/' . $user . '/.backup.cnf';
    if (!file_exists($cnfPath)) {
        throw new Exception("Backup configuration file" . $cnfPath . "does not exist.");
    }
    echo "Backup configuration file found.\n";
    echo "Starting backup for each database...\n";

    foreach ($accessInfo as $db) {
        $port = $db['port'];
        $host = $db['host'];
        $dbName = $db['db_name'];

        try {
            backupDatabase($port, $host, $dbName, $cnfPath);
        } catch (Exception $e) {
            echo "Error backing up {$dbName}: {$e->getMessage()}\n";
        }
    }
}

function backupDatabase(int $port, string $host, string $dbName, string $cnfPath): void {
    echo "Backing up database: {$dbName} on host: {$host} at port: {$port}...\n";
    $command = sprintf(
        'mysqldump --defaults-extra-file=%s -P %d -h %s %s --no-tablespaces',
        $cnfPath,
        $port,
        escapeshellarg($host),
        escapeshellarg($dbName)
    );

    $datestamp = date('Y-m-d');
    $backupFile = "{$dbName}_{$datestamp}.sql.gz";

    $fullCommand = "{$command} | gzip > " . escapeshellarg($backupFile);
    echo "Executing command: {$fullCommand}\n";
    try {
        exec($fullCommand, $output, $returnCode);
    } catch (Exception $e) {
        throw new Exception("Failed to execute backup command: " . $e->getMessage());
    }

    if ($returnCode !== 0) {
        throw new Exception("Backup command failed with code {$returnCode}");
    }

    echo "Backup for database: {$dbName} completed: {$backupFile}.\n";
}

try {
    main();
} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
    exit(1);
}
