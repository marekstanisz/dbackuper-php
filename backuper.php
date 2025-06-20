<?php

function main(): void {
    echo "Starting database backup process...\n";
    $accessInfo = json_decode(file_get_contents('access.json'), true);

    foreach ($accessInfo as $db) {
        $port = $db['port'];
        $host = $db['host'];
        $dbName = $db['db_name'];

        try {
            backupDatabase($port, $host, $user, $password, $dbName);
            pruneBackupFiles($dbName, $numberOfBackups);
        } catch (Exception $e) {
            echo "Error backing up {$dbName}: {$e->getMessage()}\n";
        }
    }
}

function pruneBackupFiles(string $dbName, int $numberOfBackups): void {
    $backupFilePattern = "{$dbName}_*.sql.gz";
    $files = glob($backupFilePattern);
    sort($files);

    while (count($files) > $numberOfBackups) {
        $fileToRemove = array_shift($files);
        echo "Removing old backup file: {$fileToRemove}\n";
        unlink($fileToRemove);
    }
}

function backupDatabase(int $port, string $host, string $user, string $password, string $dbName): void {
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
