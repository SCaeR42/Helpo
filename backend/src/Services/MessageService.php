<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Queue\RabbitMQConnection;
use App\Utils\LoggerManager;

/**
 * Message service for chat communication.
 */
class MessageService
{
    private Database $db;
    private RabbitMQConnection $queue;
    private LoggerManager $logger;

    public function __construct(Database $db, RabbitMQConnection $queue, LoggerManager $logger)
    {
        $this->db = $db;
        $this->queue = $queue;
        $this->logger = $logger;
    }

    /**
     * Send a message to a ticket chat.
     *
     * @param int $ticketId
     * @param int $userId
     * @param string $content
     * @return array
     */
    public function sendMessage(int $ticketId, int $userId, string $content): array
    {
        $logger = $this->logger->getLogger('app');

        // Verify ticket exists and belongs to user
        $ticket = $this->db->fetchOne(
            'SELECT id FROM tickets WHERE id = ? AND user_id = ?',
            [$ticketId, $userId],
            'ii'
        );

        if ($ticket === null) {
            throw new \RuntimeException('Ticket not found or access denied');
        }

        // Insert message
        $this->db->query(
            'INSERT INTO messages (ticket_id, user_id, sender_type, content) VALUES (?, ?, ?, ?)',
            [$ticketId, $userId, 'user', $content],
            'iiss'
        );

        $messageId = $this->db->getLastInsertId();

        // Publish to RabbitMQ
        $payload = [
            'message_id' => $messageId,
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'sender_type' => 'user',
            'content' => $content,
            'created_at' => date('c'),
        ];

        $this->queue->publish('message', $payload);
        $logger->info("Message sent: ID={$messageId}, Ticket={$ticketId}, User={$userId}");

        return $this->formatMessage([
            'id' => $messageId,
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'sender_type' => 'user',
            'content' => $content,
            'status_code' => null,
            'status_name' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get all messages for a ticket.
     *
     * @param int $ticketId
     * @param int $userId User ID for access control
     * @return array
     */
    public function getTicketMessages(int $ticketId, int $userId): array
    {
        // Verify access
        $ticket = $this->db->fetchOne(
            'SELECT id FROM tickets WHERE id = ? AND user_id = ?',
            [$ticketId, $userId],
            'ii'
        );

        if ($ticket === null) {
            throw new \RuntimeException('Ticket not found or access denied');
        }

        $messages = $this->db->fetchAll(
            'SELECT id, ticket_id, user_id, sender_type, content, status_code, status_name, created_at 
             FROM messages 
             WHERE ticket_id = ? 
             ORDER BY created_at ASC',
            [$ticketId],
            'i'
        );

        return array_map([$this, 'formatMessage'], $messages);
    }

    /**
     * Add a system message (used by workers).
     *
     * @param int $ticketId
     * @param string $content
     * @param string|null $statusCode
     * @param string|null $statusName
     * @return array
     */
    public function addSystemMessage(int $ticketId, string $content, ?string $statusCode = null, ?string $statusName = null): array
    {
        $this->db->query(
            'INSERT INTO messages (ticket_id, user_id, sender_type, content, status_code, status_name) 
             VALUES (?, 0, ?, ?, ?, ?)',
            [$ticketId, 'system', $content, $statusCode, $statusName],
            'issss'
        );

        return [
            'id' => (string) $this->db->getLastInsertId(),
            'ticketId' => (string) $ticketId,
            'userId' => '0',
            'senderType' => 'SYSTEM',
            'content' => $content,
            'statusCode' => $statusCode,
            'statusName' => $statusName,
            'createdAt' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Format message array for GraphQL response.
     *
     * @param array $message
     * @return array
     */
    private function formatMessage(array $message): array
    {
        return [
            'id' => (string) $message['id'],
            'ticketId' => (string) $message['ticket_id'],
            'userId' => (string) $message['user_id'],
            'senderType' => strtoupper($message['sender_type']),
            'content' => $message['content'],
            'statusCode' => $message['status_code'],
            'statusName' => $message['status_name'],
            'createdAt' => $message['created_at'],
        ];
    }
}
