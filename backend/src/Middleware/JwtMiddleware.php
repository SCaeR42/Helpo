<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response;

/**
 * JWT Authentication Middleware.
 * 
 * Validates JWT token from Authorization header
 * and adds user context to the request.
 */
class JwtMiddleware implements MiddlewareInterface
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Process incoming request.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return Response
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            return $this->unauthorizedResponse('Authorization header is required');
        }

        // Extract token from "Bearer <token>"
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $this->unauthorizedResponse('Invalid Authorization header format. Use: Bearer <token>');
        }

        $token = $matches[1];

        try {
            $payload = $this->authService->validateToken($token);
        } catch (\InvalidArgumentException $e) {
            return $this->unauthorizedResponse($e->getMessage());
        }

        // Add user context to request
        $request = $request->withAttribute('userId', (int) $payload['sub']);
        $request = $request->withAttribute('userLogin', $payload['login'] ?? '');
        $request = $request->withAttribute('jwtPayload', $payload);

        return $handler->handle($request);
    }

    /**
     * Create unauthorized response.
     *
     * @param string $message
     * @return Response
     */
    private function unauthorizedResponse(string $message): Response
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'errors' => [
                [
                    'message' => $message,
                    'extensions' => [
                        'code' => 'UNAUTHENTICATED',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
