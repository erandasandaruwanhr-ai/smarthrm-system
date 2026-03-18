<?php
require_once 'config/config.php';

// Set backup directory with today's date
$backupDate = date('Y-m-d');
$backupDir = "C:\\Users\\Eranda Sandaruwan\\Desktop\\project0\\SmartHRM_Backup_$backupDate";
$sourceDir = "C:\\laragon\\www\\pbpictures\\smarthrmjiffy";

echo "<h1>SmartHRM System Backup - $backupDate</h1>";
echo "<p>Starting comprehensive backup process...</p>";

try {
    // Create main backup directory
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0777, true);
        echo "<p>✓ Created backup directory: $backupDir</p>";
    }

    // Create subdirectories
    $subdirs = [
        'system_files',
        'database_backups',
        'database_structure_only',
        'logs'
    ];

    foreach ($subdirs as $subdir) {
        $fullPath = "$backupDir\\$subdir";
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0777, true);
            echo "<p>✓ Created subdirectory: $subdir</p>";
        }
    }

    echo "<br><h3>1. Backing up System Files</h3>";

    // Copy system files (excluding unnecessary files)
    $excludePatterns = [
        '.git',
        'node_modules',
        'vendor',
        '.env',
        'logs',
        'cache',
        'tmp'
    ];

    echo "<p>Copying system files (this may take a few minutes)...</p>";

    // Use robocopy for Windows file copying
    $robocopyCmd = "robocopy \"$sourceDir\" \"$backupDir\\system_files\" /E /XD .git node_modules vendor logs cache tmp /XF *.log *.cache /R:1 /W:1";

    // Execute the copy command
    exec($robocopyCmd . " 2>&1", $output, $returnCode);

    // Robocopy return codes: 0-7 are success, 8+ are errors
    if ($returnCode < 8) {
        echo "<p>✓ System files copied successfully</p>";
    } else {
        echo "<p>⚠️ File copy completed with warnings</p>";
    }

    echo "<br><h3>2. Creating Database Backups</h3>";

    $db = new Database();
    $timestamp = date('Y-m-d_H-i-s');

    // Full database backup with data
    $fullBackupFile = "$backupDir\\database_backups\\smarthrm_db_full_$timestamp.sql";
    $structureBackupFile = "$backupDir\\database_structure_only\\smarthrm_db_structure_$timestamp.sql";

    echo "<p>Creating full database backup with all data...</p>";

    // Get all tables
    $tables = $db->fetchAll("SHOW TABLES");
    $sqlDump = "-- SmartHRM Database Full Backup\n";
    $sqlDump .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
    $sqlDump .= "-- Database: smarthrm_db\n\n";
    $sqlDump .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $sqlDump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $sqlDump .= "SET AUTOCOMMIT = 0;\n";
    $sqlDump .= "START TRANSACTION;\n\n";

    $structureDump = $sqlDump; // Start structure dump the same way

    foreach ($tables as $table) {
        $tableName = array_values($table)[0];
        echo "<p>Processing table: $tableName</p>";

        // Get CREATE TABLE statement
        $createTable = $db->fetch("SHOW CREATE TABLE `$tableName`");
        $createStatement = $createTable['Create Table'];

        // Add to both dumps
        $sqlDump .= "-- Table structure for table `$tableName`\n";
        $sqlDump .= "DROP TABLE IF EXISTS `$tableName`;\n";
        $sqlDump .= $createStatement . ";\n\n";

        $structureDump .= "-- Table structure for table `$tableName`\n";
        $structureDump .= "DROP TABLE IF EXISTS `$tableName`;\n";
        $structureDump .= $createStatement . ";\n\n";

        // Get data for full backup only
        $rows = $db->fetchAll("SELECT * FROM `$tableName`");
        if (!empty($rows)) {
            $sqlDump .= "-- Dumping data for table `$tableName`\n";

            foreach ($rows as $row) {
                $values = array_map(function($value) use ($db) {
                    if ($value === null) return 'NULL';
                    return "'" . addslashes($value) . "'";
                }, array_values($row));

                $columns = array_keys($row);
                $sqlDump .= "INSERT INTO `$tableName` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
            }
            $sqlDump .= "\n";
        }
    }

    $sqlDump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $sqlDump .= "COMMIT;\n";

    $structureDump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $structureDump .= "COMMIT;\n";

    // Write full backup
    file_put_contents($fullBackupFile, $sqlDump);
    echo "<p>✓ Full database backup created: " . basename($fullBackupFile) . "</p>";

    // Write structure-only backup
    file_put_contents($structureBackupFile, $structureDump);
    echo "<p>✓ Structure-only backup created: " . basename($structureBackupFile) . "</p>";

    echo "<br><h3>3. Creating Backup Summary</h3>";

    // Create backup summary file
    $summaryFile = "$backupDir\\backup_summary.txt";
    $summary = "SmartHRM System Backup Summary\n";
    $summary .= "================================\n\n";
    $summary .= "Backup Date: $backupDate\n";
    $summary .= "Backup Time: " . date('H:i:s') . "\n";
    $summary .= "Source Directory: $sourceDir\n";
    $summary .= "Backup Directory: $backupDir\n\n";

    $summary .= "Contents:\n";
    $summary .= "- system_files/: Complete source code and configuration files\n";
    $summary .= "- database_backups/: Full database with all data\n";
    $summary .= "- database_structure_only/: Database structure without data\n";
    $summary .= "- logs/: This backup log\n\n";

    // Get database statistics
    $totalTables = count($tables);
    $totalRecords = 0;
    foreach ($tables as $table) {
        $tableName = array_values($table)[0];
        $count = $db->fetch("SELECT COUNT(*) as count FROM `$tableName`");
        $totalRecords += $count['count'];
    }

    $summary .= "Database Statistics:\n";
    $summary .= "- Total Tables: $totalTables\n";
    $summary .= "- Total Records: $totalRecords\n\n";

    $summary .= "File Sizes:\n";
    if (file_exists($fullBackupFile)) {
        $size = round(filesize($fullBackupFile) / 1024, 2);
        $summary .= "- Full Database Backup: {$size} KB\n";
    }
    if (file_exists($structureBackupFile)) {
        $size = round(filesize($structureBackupFile) / 1024, 2);
        $summary .= "- Structure-Only Backup: {$size} KB\n";
    }

    file_put_contents($summaryFile, $summary);

    // Create this log file
    $logFile = "$backupDir\\logs\\backup_log_$timestamp.txt";
    $logContent = ob_get_clean();
    ob_start();

    echo $logContent;
    file_put_contents($logFile, strip_tags($logContent));

    echo "<br><h3>✅ Backup Completed Successfully!</h3>";
    echo "<p><strong>Backup Location:</strong> $backupDir</p>";
    echo "<p><strong>Contents:</strong></p>";
    echo "<ul>";
    echo "<li>Complete system files in <code>system_files/</code></li>";
    echo "<li>Full database backup in <code>database_backups/</code></li>";
    echo "<li>Structure-only backup in <code>database_structure_only/</code></li>";
    echo "<li>Backup summary and logs in root and <code>logs/</code></li>";
    echo "</ul>";

    echo "<p><strong>Database Statistics:</strong></p>";
    echo "<ul>";
    echo "<li>Total Tables: $totalTables</li>";
    echo "<li>Total Records: $totalRecords</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<br><h3>❌ Backup Error</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check file permissions and try again.</p>";
}

?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    line-height: 1.6;
}
h1, h3 { color: #333; }
p { margin: 5px 0; }
code {
    background: #f4f4f4;
    padding: 2px 4px;
    border-radius: 3px;
}
ul { margin: 10px 0; }
</style>