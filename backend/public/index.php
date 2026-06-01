<?php

declare(strict_types=1);

/**
 * Application Entry Point
 * 
 * Bootstraps Slim Framework application with all dependencies.
 */

use Slim\Factory\AppFactory;
use Slim\Psr7\Response;
use GraphQL\GraphQL;
use GraphQL\Error\DebugFlag;
use App\Database\Database;
use App\Queue\RabbitMQConnection;
use App\Utils\LoggerManager;
use App\Services\AuthService;
use App\Services\TicketService;
use App\Services\MessageService;
use App\GraphQL\SchemaBuilder;
use App\Middleware\JwtMiddleware;

// Load autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/../config/app.php';

// Initialize dependencies
$loggerManager = new LoggerManager($config['logging']);
$appLogger = $loggerManager->getLogger('app');

try {
    $db = Database::getInstance($config['database']);
    $queue = RabbitMQConnection::getInstance($config['rabbitmq']);
} catch (\Throwable $e) {
    $appLogger->error("Failed to initialize dependencies: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Service unavailable']);
    exit;
}

// Initialize services
$authService = new AuthService($db, $config['jwt'], $loggerManager);
$ticketService = new TicketService($db, $queue, $loggerManager);
$messageService = new MessageService($db, $queue, $loggerManager);

// Build GraphQL schema
$schemaBuilder = new SchemaBuilder([
    'auth' => $authService,
    'ticket' => $ticketService,
    'message' => $messageService,
]);
$schema = $schemaBuilder->build();

// Create Slim app
$app = AppFactory::create();

// Add middleware
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

// Add error middleware
$app->addErrorMiddleware($config['app']['debug'], true, true);

// JWT Middleware (applied selectively)
$jwtMiddleware = new JwtMiddleware($authService);

// === Routes ===

// Health check (no auth required)
$app->get('/api/health', function ($request, $response) use ($config) {
    $response->getBody()->write(json_encode([
        'status' => 'ok',
        'timestamp' => date('c'),
        'version' => '1.0.0',
    ], JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// GraphQL endpoint
$app->post('/api/graphql', function ($request, $response) use ($schema, $config, $appLogger, $authService) {
    $body = $request->getParsedBody();
    $query = $body['query'] ?? '';
    $variables = $body['variables'] ?? [];
    
    // Check if this is a login mutation (no auth required)
    $isLogin = strpos($query, 'mutation') !== false && strpos($query, 'login') !== false;
    
    // Get user context from request attributes (set by JWT middleware if applied)
    $context = [
        'userId' => $request->getAttribute('userId', 0),
        'userLogin' => $request->getAttribute('userLogin', ''),
    ];

    try {
        $result = GraphQL::executeQuery(
            $schema,
            $query,
            null,
            $context,
            $variables,
            null,
            null,
            DebugFlag::INCLUDE_DEBUG_MESSAGE | ($config['app']['debug'] ? DebugFlag::INCLUDE_TRACE : 0)
        );
        
        $output = $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE);
    } catch (\Throwable $e) {
        $appLogger->error("GraphQL Error: " . $e->getMessage());
        $output = [
            'errors' => [
                ['message' => $config['app']['debug'] ? $e->getMessage() : 'Internal server error'],
            ],
        ];
    }

    $response->getBody()->write(json_encode($output, JSON_THROW_ON_ERROR));
    return $response->withHeader('Content-Type', 'application/json');
})->add($jwtMiddleware);

// Serve Swagger UI (static files would be in public/docs)
$app->get('/api/docs', function ($request, $response) {
    $response->getBody()->write(file_get_contents(__DIR__ . '/../docs/swagger.yaml'));
    return $response->withHeader('Content-Type', 'text/yaml');
});

// Run app
$app->run();
