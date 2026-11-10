<?php

require_once __DIR__ . "/env.php";

$host = $_ENV["DB_HOST"];
$database = $_ENV["DB_NAME"];
$username = $_ENV["DB_USER"];
$password = $_ENV["DB_PASSWORD"];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $host,
        $username,
        $password,
        $database
    );

    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    error_log("CarePlus database error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to connect to the database. Please try again later.");
}