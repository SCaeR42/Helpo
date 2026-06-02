<?php

declare(strict_types=1);

namespace App\WebSocket;

use App\Database\Database;
use App\Utils\LoggerManager;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\MessageComponentInterface;

/**
 * WebSocket обработчик для чата.
 * 
 * Протокол:
 * 1. Клиент подключается к ws://host:port/ws/chat?token=JWT
 * 2. Клиент отправляет JSON: {"action": "subscribe", "ticketId": 123}
 * 3. Сервер отправляет последние 10 сообщений: {"type": "history", "messages": [...]}
 * 4. При новых сообщениях сервер отправляет: {"type": "message", "message": {...}}
 */
class ChatWebSocketHandler implements MessageComponentInterface
{
    private ConnectionManager $manager;
    private Database $db;
    private string $jwtSecret;
    private LoggerManager $logger;

    public function __construct(ConnectionManager $manager, Database $db, string $jwtSecret, LoggerManager $logger)
    {
        $this->manager = $manager;
        $this->db = $db;
        $this->jwtSecret = $jwtSecret;
        $this->logger = $logger;
    }

    /**
     * Подключение клиента.
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        // Парсим токен из query string
        $token = $this->extractToken($conn);
        
        if ($token === null) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Token required']));
            $conn->close();
            return;
        }

        // Валидируем JWT токен
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            $userId = (int) $decoded->sub;
            
            // Сохраняем userId в conn для последующего использования
            $conn->userId = $userId;
            
            $this->logger->getLogger('app')->info("WebSocket connected: userId={$userId}, connId=" . spl_object_id($conn));
        } catch (\Exception $e) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Invalid token']));
            $conn->close();
        }
    }

    /**
     * Получение сообщения от клиента.
     */
    public function onMessage(ConnectionInterface $conn, $msg): void
    {
        $connId = spl_object_id($conn);
        
        // В Ratchet $msg — это объект Message, приводим к строке
        $msgStr = (string) $msg;
        
        $this->logger->getLogger('app')->debug(
            "onMessage: connId={$connId}, msg=" . substr($msgStr, 0, 200)
        );

        if (!isset($conn->userId)) {
            $this->logger->getLogger('app')->warning(
                "onMessage: userId not set for connId={$connId}"
            );
            return;
        }

        try {
            $data = json_decode($msgStr, true);
            
            if (!isset($data['action'])) {
                $this->logger->getLogger('app')->warning(
                    "onMessage: no action in message, connId={$connId}"
                );
                return;
            }

            $this->logger->getLogger('app')->debug(
                "onMessage: action={$data['action']}, connId={$connId}"
            );

            match ($data['action']) {
                'subscribe' => $this->handleSubscribe($conn, (int) ($data['ticketId'] ?? 0)),
                'ping' => $conn->send(json_encode(['type' => 'pong'])),
                default => null,
            };
        } catch (\Throwable $e) {
            $this->logger->getLogger('app')->error("WebSocket message error: " . $e->getMessage());
        }
    }

    /**
     * Отключение клиента.
     */
    public function onClose(ConnectionInterface $conn): void
    {
        $this->manager->remove($conn);
        $this->logger->getLogger('app')->info("WebSocket disconnected: connId=" . spl_object_id($conn));
    }

    /**
     * Ошибка соединения.
     */
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $this->logger->getLogger('app')->error("WebSocket error: " . $e->getMessage());
        $conn->close();
    }

    /**
     * Подписка на тикет.
     */
    private function handleSubscribe(ConnectionInterface $conn, int $ticketId): void
    {
        $connId = spl_object_id($conn);
        $this->logger->getLogger('app')->debug(
            "handleSubscribe: connId={$connId}, ticketId={$ticketId}, userId=" . ($conn->userId ?? 'null')
        );

        if ($ticketId <= 0 || !isset($conn->userId)) {
            $this->logger->getLogger('app')->warning(
                "Invalid subscribe: ticketId={$ticketId}, userId=" . ($conn->userId ?? 'null')
            );
            $conn->send(json_encode(['type' => 'error', 'message' => 'Invalid ticketId']));
            return;
        }

        $userId = $conn->userId;

        // Проверяем, что пользователь имеет доступ к тику
        $ticket = $this->db->fetchOne(
            'SELECT id FROM tickets WHERE id = ? AND user_id = ?',
            [$ticketId, $userId],
            'ii'
        );

        if ($ticket === null) {
            $this->logger->getLogger('app')->warning(
                "Access denied: userId={$userId}, ticketId={$ticketId}"
            );
            $conn->send(json_encode(['type' => 'error', 'message' => 'Access denied to ticket']));
            return;
        }

        // Добавляем подключение в менеджер
        $this->manager->add($conn, $userId, $ticketId);

        // Загружаем последние 10 сообщений
        $messages = $this->getLatestMessages($ticketId, 10);
        $this->logger->getLogger('app')->debug(
            "Loaded " . count($messages) . " messages for ticket {$ticketId}"
        );

        // Отправляем историю
        $historyData = json_encode([
            'type' => 'history',
            'ticketId' => $ticketId,
            'messages' => $messages,
        ]);
        $conn->send($historyData);
        $this->logger->getLogger('app')->info(
            "User {$userId} subscribed to ticket {$ticketId}, sent " . count($messages) . " messages"
        );
    }

    /**
     * Получить последние N сообщений для тикета.
     */
    private function getLatestMessages(int $ticketId, int $limit): array
    {
        $messages = $this->db->fetchAll(
            'SELECT id, ticket_id, user_id, sender_type, content, status_code, status_name, created_at 
             FROM messages 
             WHERE ticket_id = ? 
             ORDER BY created_at DESC 
             LIMIT ?',
            [$ticketId, $limit],
            'ii'
        );

        // Обратный порядок для хронологии (старые -> новые)
        $messages = array_reverse($messages);

        return array_map(function ($msg) {
            return [
                'id' => (string) $msg['id'],
                'ticketId' => (string) $msg['ticket_id'],
                'userId' => (string) $msg['user_id'],
                'senderType' => strtoupper($msg['sender_type']),
                'content' => $msg['content'],
                'statusCode' => $msg['status_code'],
                'statusName' => $msg['status_name'],
                'createdAt' => $msg['created_at'],
            ];
        }, $messages);
    }

    /**
     * Извлечь JWT токен из query string подключения.
     */
    private function extractToken(ConnectionInterface $conn): ?string
    {
        if (!isset($conn->httpRequest)) {
            return null;
        }

        $query = $conn->httpRequest->getUri()->getQuery();
        parse_str($query, $params);

        return $params['token'] ?? null;
    }

    /**
     * Отправить новое сообщение всем подписчикам тикета.
     * Этот метод вызывается из MessageService при отправке сообщения.
     */
    public function broadcastMessage(int $ticketId, array $message, ?int $excludeConnId = null): void
    {
        $data = json_encode([
            'type' => 'message',
            'ticketId' => $ticketId,
            'message' => $message,
        ]);

        $this->manager->broadcastToTicket($ticketId, $data, $excludeConnId);
    }
}
