<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$databaseHost = '127.0.0.1';
$databaseUser = 'root';
$databasePassword = '';
$databaseName = 'student_management';
$databasePort = 3306;

try {
    $db = new mysqli(
        $databaseHost,
        $databaseUser,
        $databasePassword,
        $databaseName,
        $databasePort
    );
    $db->set_charset('utf8mb4');
} catch (mysqli_sql_exception $error) {
    exit('Database connection failed. Start MySQL in XAMPP and import database.sql.');
}
