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
use App\Middleware\CorsMiddleware;

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

// Add CORS middleware
$app->add(new CorsMiddleware([
    'origins' => ['http://localhost:5173', 'http://localhost:8000'],
    'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'headers' => ['Content-Type', 'Authorization'],
]));

// Add middleware
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

// Add error middleware
$app->addErrorMiddleware($config['app']['debug'], true, true);

// Helper to validate JWT token
$validateJwt = function ($request) use ($authService): array {
    $authHeader = $request->getHeaderLine('Authorization');
    
    if (empty($authHeader)) {
        return ['error' => 'Authorization header is required'];
    }
    
    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        return ['error' => 'Invalid Authorization header format'];
    }
    
    try {
        $payload = $authService->validateToken($matches[1]);
        return [
            'userId' => (int) $payload['sub'],
            'userLogin' => $payload['login'] ?? '',
        ];
    } catch (\InvalidArgumentException $e) {
        return ['error' => $e->getMessage()];
    }
};

// Helper to create error response
$createErrorResponse = function (string $message, int $status = 401): Response {
    $response = new Response();
    $response->getBody()->write(json_encode([
        'errors' => [['message' => $message]],
    ], JSON_THROW_ON_ERROR));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
};

// Helper to execute GraphQL queries
$executeGraphQL = function ($request, $response, $userId, $userLogin) use ($schema, $config, $appLogger) {
    // Get query from body or params
    if ($request->getMethod() === 'POST') {
        $body = $request->getParsedBody();
        $query = $body['query'] ?? '';
        $variables = $body['variables'] ?? [];
    } else {
        $params = $request->getQueryParams();
        $query = $params['query'] ?? '';
        $variables = isset($params['variables']) ? json_decode($params['variables'], true) : [];
    }
    
    $context = [
        'userId' => $userId,
        'userLogin' => $userLogin,
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
            null
        );
        
        $output = $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE | ($config['app']['debug'] ? DebugFlag::INCLUDE_TRACE : 0));
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
};

// === Routes ===

// Handle preflight OPTIONS requests
$app->options('/api/{routes:.+}', function ($request, $response) {
    return $response->withStatus(204);
});

// Health check (no auth required)
$app->get('/api/health', function ($request, $response) use ($config) {
    $response->getBody()->write(json_encode([
        'status' => 'ok',
        'timestamp' => date('c'),
        'version' => '1.0.0',
    ], JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// GraphQL endpoint - POST (with conditional auth)
$app->post('/api/graphql', function ($request, $response) use ($validateJwt, $createErrorResponse, $executeGraphQL) {
    $body = $request->getParsedBody();
    $query = $body['query'] ?? '';
    
    // Check if this is a login mutation (no auth required)
    $isLogin = stripos($query, 'login') !== false && stripos($query, 'mutation') !== false;
    
    if ($isLogin) {
        // No auth required for login
        return $executeGraphQL($request, $response, 0, '');
    }
    
    // Validate JWT for other operations
    $authResult = $validateJwt($request);
    if (isset($authResult['error'])) {
        return $createErrorResponse($authResult['error']);
    }
    
    return $executeGraphQL($request, $response, $authResult['userId'], $authResult['userLogin']);
});

// GraphQL endpoint - GET (with auth)
$app->get('/api/graphql', function ($request, $response) use ($validateJwt, $createErrorResponse, $executeGraphQL) {
    // Validate JWT for GET requests
    $authResult = $validateJwt($request);
    if (isset($authResult['error'])) {
        return $createErrorResponse($authResult['error']);
    }
    
    return $executeGraphQL($request, $response, $authResult['userId'], $authResult['userLogin']);
});

// Serve Swagger UI
$app->get('/api/docs', function ($request, $response) {
    $swaggerPath = __DIR__ . '/../docs/swagger.yaml';
    if (!file_exists($swaggerPath)) {
        $response->getBody()->write('Swagger documentation not found');
        return $response->withStatus(404);
    }
    $response->getBody()->write(file_get_contents($swaggerPath));
    return $response->withHeader('Content-Type', 'text/yaml');
});

// Run app
$app->run();
