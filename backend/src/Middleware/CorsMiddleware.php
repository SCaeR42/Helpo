<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response;

/**
 * CORS Middleware.
 * 
 * Adds CORS headers to allow requests from the frontend.
 */
class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins;
    private array $allowedMethods;
    private array $allowedHeaders;

    public function __construct(array $config = [])
    {
        $this->allowedOrigins = $config['origins'] ?? ['http://localhost:5173'];
        $this->allowedMethods = $config['methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
        $this->allowedHeaders = $config['headers'] ?? ['Content-Type', 'Authorization'];
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): Response
    {
        $origin = $request->getHeaderLine('Origin');
        
        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response();
            
            if (in_array($origin, $this->allowedOrigins)) {
                $response = $response
                    ->withHeader('Access-Control-Allow-Origin', $origin)
                    ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
                    ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
                    ->withHeader('Access-Control-Allow-Credentials', 'true')
                    ->withHeader('Access-Control-Max-Age', '3600');
            }
            
            return $response->withStatus(204);
        }
        
        // Process request
        $response = $handler->handle($request);
        
        // Add CORS headers to response
        if (in_array($origin, $this->allowedOrigins)) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Credentials', 'true');
        }
        
        return $response;
    }
}
