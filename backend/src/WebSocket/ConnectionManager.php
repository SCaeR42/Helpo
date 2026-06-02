<?php

declare(strict_types=1);

namespace App\WebSocket;

use Ratchet\ConnectionInterface;

/**
 * Менеджер WebSocket-подключений.
 * 
 * Отслеживает активные подключения, группирует их по ticketId
 * и предоставляет методы для отправки сообщений конкретным клиентам.
 */
class ConnectionManager
{
    /**
     * Все активные подключения: spl_object_id => ConnectionInterface
     */
    private array $connections = [];

    /**
     * Подключения, сгруппированные по ticketId: ticketId => [spl_object_id => ConnectionInterface]
     */
    private array $ticketConnections = [];

    /**
     * Добавить подключение в менеджер.
     */
    public function add(ConnectionInterface $conn, int $userId, int $ticketId): void
    {
        $id = spl_object_id($conn);
        $this->connections[$id] = [
            'conn' => $conn,
            'userId' => $userId,
            'ticketId' => $ticketId,
        ];

        $this->ticketConnections[$ticketId][$id] = $conn;
    }

    /**
     * Удалить подключение из менеджера.
     */
    public function remove(ConnectionInterface $conn): void
    {
        $id = spl_object_id($conn);
        
        if (isset($this->connections[$id])) {
            $ticketId = $this->connections[$id]['ticketId'];
            unset($this->ticketConnections[$ticketId][$id]);
            unset($this->connections[$id]);
        }
    }

    /**
     * Отправить сообщение всем подключенным клиентам конкретного тикета,
     * кроме отправителя (если excludeConnId передан).
     */
    public function broadcastToTicket(int $ticketId, string $data, ?int $excludeConnId = null): void
    {
        if (!isset($this->ticketConnections[$ticketId])) {
            return;
        }

        foreach ($this->ticketConnections[$ticketId] as $id => $conn) {
            if ($excludeConnId !== null && $id === $excludeConnId) {
                continue;
            }
            $conn->send($data);
        }
    }

    /**
     * Отправить сообщение конкретному подключению.
     */
    public function sendTo(ConnectionInterface $conn, string $data): void
    {
        $conn->send($data);
    }

    /**
     * Получить количество подключений к конкретному тикету.
     */
    public function getConnectionCount(int $ticketId): int
    {
        return isset($this->ticketConnections[$ticketId])
            ? count($this->ticketConnections[$ticketId])
            : 0;
    }

    /**
     * Получить общее количество подключений.
     */
    public function getTotalConnections(): int
    {
        return count($this->connections);
    }
}
