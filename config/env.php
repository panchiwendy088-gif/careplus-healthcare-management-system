<?php

$envFile = dirname(__DIR__) . "/.env";

if (!file_exists($envFile)) {
    throw new RuntimeException(".env file not found.");
}

$variables = parse_ini_file($envFile);

if ($variables === false) {
    throw new RuntimeException("Unable to read .env file.");
}

foreach ($variables as $key => $value) {
    $_ENV[$key] = $value;
    putenv("$key=$value");
}