<?php

declare(strict_types=1);

/**
 * WebSocket сервер для чата.
 * 
 * Запуск: php websocket_server.php
 * Порт: 8080 (настраивается через WS_PORT)
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Database\Database;
use App\Queue\RabbitMQConnection;
use App\Utils\LoggerManager;
use App\WebSocket\ChatWebSocketHandler;
use App\WebSocket\ConnectionManager;
use App\WebSocket\WebSocketQueueConsumer;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\Server;

// Загрузка .env
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Конфигурация
$wsPort = (int) ($_ENV['WS_PORT'] ?? 8080);
$jwtSecret = $_ENV['JWT_SECRET'] ?? '';

if (empty($jwtSecret)) {
    die("JWT_SECRET not configured\n");
}

// Инициализация
$db = Database::getInstance([
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
    'user' => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'name' => $_ENV['DB_NAME'] ?? 'helpo',
    'charset' => 'utf8mb4',
]);

$logger = new LoggerManager([
    'path' => $_ENV['LOG_PATH'] ?? __DIR__ . '/logs',
    'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
]);

$manager = new ConnectionManager();
$handler = new ChatWebSocketHandler($manager, $db, $jwtSecret, $logger);

// RabbitMQ consumer для рассылки сообщений через WebSocket
$queue = RabbitMQConnection::getInstance([
    'host' => $_ENV['RABBITMQ_HOST'] ?? '127.0.0.1',
    'port' => (int) ($_ENV['RABBITMQ_PORT'] ?? 5672),
    'user' => $_ENV['RABBITMQ_USER'] ?? 'guest',
    'password' => $_ENV['RABBITMQ_PASSWORD'] ?? 'guest',
    'vhost' => $_ENV['RABBITMQ_VHOST'] ?? '/',
]);

$queueConsumer = new WebSocketQueueConsumer($queue, $handler, $logger);

// Используем React Event Loop для интеграции с Ratchet
$loop = Loop::get();

// Создаём React Socket сервер
$socket = new Server('0.0.0.0:' . $wsPort, $loop);

// Таймер для проверки очереди (каждые 500мс)
$loop->addPeriodicTimer(0.5, function () use ($queueConsumer) {
    try {
        $queueConsumer->consume();
    } catch (\Throwable $e) {
        // Игнорируем ошибки потребления, логируем
    }
});

// Создаём Ratchet сервер с использованием event loop и socket
$server = new IoServer(
    new HttpServer(
        new WsServer($handler)
    ),
    $socket,
    $loop
);

echo "WebSocket server started on port {$wsPort}\n";
echo "Listening for connections...\n";

$server->run();
