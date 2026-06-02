<?php

declare(strict_types=1);

/**
 * Application Configuration
 * 
 * Loads environment variables and returns application config array.
 */

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->safeLoad();

return [
    'app' => [
        'env' => $_ENV['APP_ENV'] ?? 'development',
        'debug' => (bool) ($_ENV['APP_DEBUG'] ?? false),
        'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
    ],

    'database' => [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
        'name' => $_ENV['DB_NAME'] ?? 'helpo',
        'user' => $_ENV['DB_USER'] ?? 'helpo_user',
        'password' => $_ENV['DB_PASSWORD'] ?? 'helpo_password',
        'charset' => 'utf8mb4',
    ],

    'rabbitmq' => [
        'host' => $_ENV['RABBITMQ_HOST'] ?? 'localhost',
        'port' => (int) ($_ENV['RABBITMQ_PORT'] ?? 5672),
        'user' => $_ENV['RABBITMQ_USER'] ?? 'guest',
        'password' => $_ENV['RABBITMQ_PASSWORD'] ?? 'guest',
        'vhost' => $_ENV['RABBITMQ_VHOST'] ?? '/',
    ],

    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'] ?? 'change-me-in-production',
        'ttl' => (int) ($_ENV['JWT_TTL'] ?? 86400),
        'issuer' => 'helpo-api',
        'algorithm' => 'HS256',
    ],

    'logging' => [
        'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
        'path' => $_ENV['LOG_PATH'] ?? dirname(__DIR__) . '/logs',
    ],
];
