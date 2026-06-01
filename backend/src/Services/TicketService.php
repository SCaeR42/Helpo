<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Queue\RabbitMQConnection;
use App\Utils\LoggerManager;

/**
 * Ticket service for managing support requests.
 */
class TicketService
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
     * Section mapping (GraphQL enum -> DB value)
     */
    private const SECTION_MAP = [
        'GENERAL' => 'general',
        'SUBSCRIPTION' => 'subscription',
        'ACCOUNT' => 'account',
        'ERROR' => 'error',
        'FEATURE' => 'feature',
    ];

    /**
     * Create a new ticket and publish to queue.
     *
     * @param int $userId
     * @param string $subject
     * @param string $section GraphQL enum value
     * @param string|null $comment
     * @return array
     */
    public function createTicket(int $userId, string $subject, string $section, ?string $comment = null): array
    {
        $logger = $this->logger->getLogger('app');
        $dbSection = self::SECTION_MAP[$section] ?? 'general';

        // Insert ticket into database
        $this->db->query(
            'INSERT INTO tickets (user_id, subject, section, comment, status_code, status_name) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $subject, $dbSection, $comment, 'pending', 'Ожидает обработки'],
            'isssss'
        );

        $ticketId = $this->db->getLastInsertId();

        // Publish to RabbitMQ
        $payload = [
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'subject' => $subject,
            'section' => $dbSection,
            'comment' => $comment,
            'created_at' => date('c'),
        ];

        $this->queue->publish('ticket', $payload);
        $logger->info("Ticket created: ID={$ticketId}, User={$userId}");

        return $this->getTicketById($ticketId);
    }

    /**
     * Get all tickets for a user.
     *
     * @param int $userId
     * @return array
     */
    public function getUserTickets(int $userId): array
    {
        $tickets = $this->db->fetchAll(
            'SELECT id, user_id, subject, section, comment, status_code, status_name, created_at, updated_at 
             FROM tickets 
             WHERE user_id = ? 
             ORDER BY created_at DESC',
            [$userId],
            'i'
        );

        return array_map([$this, 'formatTicket'], $tickets);
    }

    /**
     * Get a single ticket by ID.
     *
     * @param int $ticketId
     * @return array|null
     */
    public function getTicketById(int $ticketId): ?array
    {
        $ticket = $this->db->fetchOne(
            'SELECT id, user_id, subject, section, comment, status_code, status_name, created_at, updated_at 
             FROM tickets 
             WHERE id = ?',
            [$ticketId],
            'i'
        );

        return $ticket ? $this->formatTicket($ticket) : null;
    }

    /**
     * Get ticket status with latest message.
     *
     * @param int $ticketId
     * @return array{code: string, name: string, message: string|null}
     */
    public function getTicketStatus(int $ticketId): array
    {
        $ticket = $this->getTicketById($ticketId);

        if ($ticket === null) {
            throw new \RuntimeException("Ticket not found: {$ticketId}");
        }

        // Get latest status message from logs
        $latestLog = $this->db->fetchOne(
            'SELECT message FROM ticket_logs WHERE ticket_id = ? ORDER BY created_at DESC LIMIT 1',
            [$ticketId],
            'i'
        );

        return [
            'code' => $ticket['statusCode'],
            'name' => $ticket['statusName'],
            'message' => $latestLog['message'] ?? null,
        ];
    }

    /**
     * Format ticket array for GraphQL response.
     *
     * @param array $ticket
     * @return array
     */
    private function formatTicket(array $ticket): array
    {
        // Reverse section mapping
        $reverseMap = array_flip(self::SECTION_MAP);
        $section = $reverseMap[$ticket['section']] ?? 'GENERAL';

        return [
            'id' => (string) $ticket['id'],
            'userId' => (string) $ticket['user_id'],
            'subject' => $ticket['subject'],
            'section' => $section,
            'comment' => $ticket['comment'],
            'statusCode' => $ticket['status_code'],
            'statusName' => $ticket['status_name'],
            'createdAt' => $ticket['created_at'],
            'updatedAt' => $ticket['updated_at'],
        ];
    }
}
