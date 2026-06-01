<?php

declare(strict_types=1);

namespace App\Queue;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;

/**
 * RabbitMQ connection and channel manager.
 * 
 * Provides singleton connection with lazy channel creation.
 */
class RabbitMQConnection
{
    private static ?self $instance = null;
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;
    private array $config;

    /**
     * Queue and exchange configuration
     */
    public const QUEUES = [
        'ticket' => [
            'queue' => 'ticket_queue',
            'exchange' => 'helpo.direct',
            'routing_key' => 'ticket.create',
        ],
        'message' => [
            'queue' => 'message_queue',
            'exchange' => 'helpo.direct',
            'routing_key' => 'message.send',
        ],
        'status' => [
            'queue' => 'status_queue',
            'exchange' => 'helpo.direct',
            'routing_key' => 'status.update',
        ],
    ];

    private function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get singleton instance.
     *
     * @param array|null $config
     * @return self
     */
    public static function getInstance(?array $config = null): self
    {
        if (self::$instance === null) {
            if ($config === null) {
                throw new \RuntimeException('RabbitMQ config required for first initialization');
            }
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * Get or create AMQP connection.
     *
     * @return AMQPStreamConnection
     */
    private function getConnection(): AMQPStreamConnection
    {
        if ($this->connection === null || $this->connection->isClosed()) {
            $this->connection = new AMQPStreamConnection(
                host: $this->config['host'],
                port: $this->config['port'],
                user: $this->config['user'],
                password: $this->config['password'],
                vhost: $this->config['vhost'] ?? '/'
            );
        }

        return $this->connection;
    }

    /**
     * Get or create AMQP channel.
     *
     * @return AMQPChannel
     */
    public function getChannel(): AMQPChannel
    {
        if ($this->channel === null) {
            $this->channel = $this->getConnection()->channel();
            $this->declareInfrastructure();
        }

        return $this->channel;
    }

    /**
     * Declare exchanges and queues.
     *
     * @return void
     */
    private function declareInfrastructure(): void
    {
        // Declare exchange
        $this->channel->exchange_declare(
            exchange: 'helpo.direct',
            type: 'direct',
            passive: false,
            durable: true,
            auto_delete: false
        );

        // Declare queues
        foreach (self::QUEUES as $queueConfig) {
            $this->channel->queue_declare(
                queue: $queueConfig['queue'],
                passive: false,
                durable: true,
                exclusive: false,
                auto_delete: false
            );

            $this->channel->queue_bind(
                queue: $queueConfig['queue'],
                exchange: 'helpo.direct',
                routing_key: $queueConfig['routing_key']
            );
        }
    }

    /**
     * Publish a message to the specified queue.
     *
     * @param string $queueKey Queue key (ticket, message, status)
     * @param array $payload Message payload
     * @return bool
     */
    public function publish(string $queueKey, array $payload): bool
    {
        if (!isset(self::QUEUES[$queueKey])) {
            throw new \InvalidArgumentException("Unknown queue key: {$queueKey}");
        }

        $config = self::QUEUES[$queueKey];
        $channel = $this->getChannel();

        $message = new AMQPMessage(
            json_encode($payload, JSON_THROW_ON_ERROR),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'timestamp' => time(),
            ]
        );

        $channel->basic_publish(
            msg: $message,
            exchange: $config['exchange'],
            routing_key: $config['routing_key']
        );

        return true;
    }

    /**
     * Close connection.
     *
     * @return void
     */
    public function close(): void
    {
        if ($this->channel !== null && !$this->channel->isClosed()) {
            $this->channel->close();
        }
        
        if ($this->connection !== null && !$this->connection->isClosed()) {
            $this->connection->close();
        }
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserialization.
     */
    public function __wakeup()
    {
        throw new \RuntimeException("Cannot unserialize singleton");
    }

    /**
     * Destructor - close connection.
     */
    public function __destruct()
    {
        $this->close();
    }
}
