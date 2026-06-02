<?php

declare(strict_types=1);

namespace App\WebSocket;

use App\Queue\RabbitMQConnection;
use App\Utils\LoggerManager;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Потребитель очереди для WebSocket сервера.
 * 
 * Слушает очередь сообщений и рассылает новые сообщения
 * подключенным WebSocket клиентам.
 */
class WebSocketQueueConsumer
{
    private RabbitMQConnection $queue;
    private ChatWebSocketHandler $handler;
    private LoggerManager $logger;
    private bool $running = false;

    public function __construct(RabbitMQConnection $queue, ChatWebSocketHandler $handler, LoggerManager $logger)
    {
        $this->queue = $queue;
        $this->handler = $handler;
        $this->logger = $logger;
    }

    /**
     * Запустить потребление очереди (неблокирующее).
     * Вызывается в цикле event loop WebSocket сервера.
     */
    public function consume(): void
    {
        if (!$this->running) {
            $this->start();
        }

        // Получаем одно сообщение (неблокирующее)
        $channel = $this->queue->getChannel();
        $message = $channel->basic_get('message_queue', false);

        if ($message !== null) {
            $this->processMessage($message);
            $channel->basic_ack($message->getDeliveryTag());
        }
    }

    /**
     * Запустить потребление (однократная инициализация).
     */
    private function start(): void
    {
        $this->running = true;
        $this->logger->getLogger('app')->info('WebSocket queue consumer started');
    }

    /**
     * Обработать сообщение из очереди.
     */
    private function processMessage(AMQPMessage $message): void
    {
        try {
            $body = json_decode($message->getBody(), true);
            
            if (!isset($body['ticket_id'], $body['message_id'])) {
                return;
            }

            $ticketId = (int) $body['ticket_id'];
            
            // Формируем данные для отправки
            $messageData = [
                'id' => (string) $body['message_id'],
                'ticketId' => (string) $body['ticket_id'],
                'userId' => (string) ($body['user_id'] ?? '0'),
                'senderType' => strtoupper($body['sender_type'] ?? 'SYSTEM'),
                'content' => $body['content'] ?? '',
                'statusCode' => $body['status_code'] ?? null,
                'statusName' => $body['status_name'] ?? null,
                'createdAt' => $body['created_at'] ?? date('Y-m-d H:i:s'),
            ];

            // Исключаем отправителя (если он подключен через WS)
            $excludeConnId = isset($body['user_id']) ? (int) $body['user_id'] : null;

            $this->handler->broadcastMessage($ticketId, $messageData);
            
            $this->logger->getLogger('app')->info(
                "Broadcast message {$body['message_id']} to ticket {$ticketId} via WebSocket"
            );
        } catch (\Throwable $e) {
            $this->logger->getLogger('app')->error("Queue consumer error: " . $e->getMessage());
        }
    }
}
