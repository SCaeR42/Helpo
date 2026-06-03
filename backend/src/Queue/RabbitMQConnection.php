<?php

declare(strict_types=1);

namespace App\Queue;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

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
        if ($this->connection === null) {
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
            try {
                $this->channel = $this->getConnection()->channel();
                $this->declareInfrastructure();
            } catch (\Throwable $e) {
                // Reset connection on error
                $this->connection = null;
                $this->channel = null;
                throw $e;
            }
        }

        return $this->channel;
    }

    /**
     * Dead-letter exchange and queue configuration.
     */
    public const DLX_EXCHANGE = 'helpo.dlx';
    public const DLX_ROUTING_KEY_SUFFIX = '.failed';
    public const MAX_RETRIES = 3;

    /**
     * Declare exchanges and queues.
     *
     * @return void
     */
    private function declareInfrastructure(): void
    {
        // Declare main exchange
        $this->channel->exchange_declare(
            exchange: 'helpo.direct',
            type: 'direct',
            passive: false,
            durable: true,
            auto_delete: false
        );

        // Declare dead-letter exchange
        $this->channel->exchange_declare(
            exchange: self::DLX_EXCHANGE,
            type: 'direct',
            passive: false,
            durable: true,
            auto_delete: false
        );

        // Declare queues with DLQ arguments
        foreach (self::QUEUES as $queueConfig) {
            $queueName = $queueConfig['queue'];
            $dlqQueueName = $queueName . '.dlq';
            $dlxRoutingKey = $queueConfig['routing_key'] . self::DLX_ROUTING_KEY_SUFFIX;

            // Declare DLQ (dead-letter queue)
            $this->channel->queue_declare(
                queue: $dlqQueueName,
                passive: false,
                durable: true,
                exclusive: false,
                auto_delete: false
            );

            // Bind DLQ to DLX
            $this->channel->queue_bind(
                queue: $dlqQueueName,
                exchange: self::DLX_EXCHANGE,
                routing_key: $dlxRoutingKey
            );

            // Declare main queue with DLQ arguments
            $arguments = new AMQPTable([
                'x-dead-letter-exchange' => self::DLX_EXCHANGE,
                'x-dead-letter-routing-key' => $dlxRoutingKey,
                'x-max-retries' => self::MAX_RETRIES,
            ]);

            $this->channel->queue_declare(
                queue: $queueName,
                passive: false,
                durable: true,
                exclusive: false,
                auto_delete: false,
                arguments: $arguments
            );

            // Bind main queue to main exchange
            $this->channel->queue_bind(
                queue: $queueName,
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
        
        try {
            $channel = $this->getChannel();
        } catch (\Throwable $e) {
            // If channel/connection fails, just log and return false
            return false;
        }

        $message = new AMQPMessage(
            json_encode($payload, JSON_THROW_ON_ERROR),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'timestamp' => time(),
            ]
        );

        try {
            $channel->basic_publish(
                msg: $message,
                exchange: $config['exchange'],
                routing_key: $config['routing_key']
            );
            return true;
        } catch (\Throwable $e) {
            // Reset channel on error
            $this->channel = null;
            return false;
        }
    }

    /**
     * Close connection.
     *
     * @return void
     */
    public function close(): void
    {
        try {
            if ($this->channel !== null) {
                $this->channel->close();
            }
        } catch (\Throwable $e) {
            // Channel may already be closed
        }
        
        try {
            if ($this->connection !== null) {
                $this->connection->close();
            }
        } catch (\Throwable $e) {
            // Connection may already be closed
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
