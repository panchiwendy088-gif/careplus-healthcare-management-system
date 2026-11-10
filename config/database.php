
<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Database configuration
$host = "localhost";
$username = "careplus_user";
$password = "1234";
$database = "careplus";
$port = 3306;

try {
    // Connect to MariaDB/MySQL
    $conn = new mysqli(
        $host,
        $username,
        $password,
        $database,
        $port
    );

    // Use UTF-8 character encoding
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    // Log the actual error privately
    error_log("CarePlus database connection failed: " . $e->getMessage());

    // Show a generic message to visitors
    http_response_code(500);
    exit("Unable to connect to the database. Please try again later.");
}
