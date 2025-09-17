<?php

use Illuminate\Foundation\Application;

try {
    require_once 'vendor/autoload.php';

    echo "Autoload successful\n";

    $app = new Application(
        $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
    );

    echo "Application created\n";

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}