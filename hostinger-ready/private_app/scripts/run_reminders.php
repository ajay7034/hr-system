<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Config\Env;
use App\Core\Database;
use App\Services\ReminderService;

require dirname(__DIR__) . '/bootstrap/autoload.php';

Env::load(dirname(__DIR__));
$config = AppConfig::all();
$pdo = Database::connect($config['db']);

$service = new ReminderService($pdo);
$result = $service->generate();

echo json_encode([
    'success' => true,
    'generated_at' => date(DATE_ATOM),
    'result' => $result,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
