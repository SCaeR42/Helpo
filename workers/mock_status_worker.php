<?php

declare(strict_types=1);

/**
 * Mock Status Worker
 * 
 * Processes tickets from the queue and simulates status changes
 * with mock data. For MVP, generates 5 status updates with
 * 1-minute intervals and 2 status changes.
 * 
 * Usage: php workers/mock_status_worker.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use App\Database\Database;
use App\Services\MessageService;
use App\Utils\LoggerManager;

// Load configuration
$config = require __DIR__ . '/../config/app.php';

// Initialize dependencies
$loggerManager = new LoggerManager($config['logging']);
$logger = $loggerManager->getLogger('queue');
$db = Database::getInstance($config['database']);
$messageService = new MessageService($db, null, $loggerManager);

// Mock status sequence for MVP
$MOCK_STATUSES = [
    [
        'code' => 'processing',
        'name' => 'В обработке',
        'message' => 'Запрос передан специалисту',
    ],
    [
        'code' => 'processing',
        'name' => 'В обработке',
        'message' => 'Начат анализ проблемы',
    ],
    [
        'code' => 'in_progress',
        'name' => 'В работе',
        'message' => 'Проблема идентифицирована',
    ],
    [
        'code' => 'in_progress',
        'name' => 'В работе',
        'message' => 'Подготовка решения',
    ],
    [
        'code' => 'completed',
        'name' => 'Завершён',
        'message' => 'Обращение успешно закрыто',
    ],
];

/**
 * Update ticket status in database.
 *
 * @param int $ticketId
 * @param string $statusCode
 * @param string $statusName
 * @return void
 */
function updateTicketStatus(int $ticketId, string $statusCode, string $statusName): void
{
    global $db;
    $db->query(
        'UPDATE tickets SET status_code = ?, status_name = ? WHERE id = ?',
        [$statusCode, $statusName, $ticketId],
        'ssi'
    );
}

/**
 * Add log entry for ticket status change.
 *
 * @param int $ticketId
 * @param string $statusCode
 * @param string $statusName
 * @param string $message
 * @return void
 */
function addTicketLog(int $ticketId, string $statusCode, string $statusName, string $message): void
{
    global $db;
    $db->query(
        'INSERT INTO ticket_logs (ticket_id, status_code, status_name, message) VALUES (?, ?, ?, ?)',
        [$ticketId, $statusCode, $statusName, $message],
        'isss'
    );
}

/**
 * Process a single ticket with mock status updates.
 *
 * @param int $ticketId
 * @return void
 */
function processTicket(int $ticketId): void
{
    global $logger, $messageService, $MOCK_STATUSES;
    
    $logger->info("Starting mock processing for ticket: {$ticketId}");
    
    foreach ($MOCK_STATUSES as $index => $status) {
        // Wait 1 minute between steps (for production; use shorter delay for testing)
        $sleepTime = getenv('MOCK_DELAY') ? (int) getenv('MOCK_DELAY') : 60;
        $logger->info("Step {$index}/5: Waiting {$sleepTime}s before status update");
        sleep($sleepTime);
        
        // Update ticket status
        updateTicketStatus($ticketId, $status['code'], $status['name']);
        
        // Add log entry
        addTicketLog($ticketId, $status['code'], $status['name'], $status['message']);
        
        // Add system message to chat
        $messageService->addSystemMessage(
            $ticketId,
            $status['message'],
            $status['code'],
            $status['name']
        );
        
        $logger->info("Step {$index}/5: Status updated to {$status['code']} - {$status['message']}");
    }
    
    $logger->info("Mock processing completed for ticket: {$ticketId}");
}

/**
 * Message callback for RabbitMQ consumer.
 *
 * @param AMQPMessage $message
 * @return void
 */
function processMessage(AMQPMessage $message): void
{
    global $logger;
    
    $body = json_decode($message->getBody(), true);
    $ticketId = $body['ticket_id'] ?? null;
    
    if ($ticketId === null) {
        $logger->error("Invalid message received: missing ticket_id");
        $message->ack();
        return;
    }
    
    $logger->info("Received ticket from queue: {$ticketId}");
    
    try {
        processTicket((int) $ticketId);
        $message->ack();
    } catch (\Throwable $e) {
        $logger->error("Error processing ticket {$ticketId}: " . $e->getMessage());
        $message->nack();
    }
}

// Connect to RabbitMQ
$rabbitConfig = $config['rabbitmq'];
$connection = new AMQPStreamConnection(
    $rabbitConfig['host'],
    $rabbitConfig['port'],
    $rabbitConfig['user'],
    $rabbitConfig['password'],
    $rabbitConfig['vhost'] ?? '/'
);

$channel = $connection->channel();

// Declare queue
$channel->queue_declare(
    queue: 'ticket_queue',
    passive: false,
    durable: true,
    exclusive: false,
    auto_delete: false
);

$logger->info("Mock Status Worker started. Waiting for messages...");

// Start consuming messages
$channel->basic_consume(
    queue: 'ticket_queue',
    consumer_tag: 'mock_status_worker',
    no_ack: false,
    callback: 'processMessage'
);

// Keep the worker running
try {
    while ($channel->is_consuming()) {
        $channel->wait();
    }
} catch (\Throwable $e) {
    $logger->error("Worker stopped: " . $e->getMessage());
} finally {
    $channel->close();
    $connection->close();
}
