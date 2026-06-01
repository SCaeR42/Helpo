<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Queue\RabbitMQConnection;
use App\Utils\LoggerManager;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use DateTimeImmutable;

/**
 * Authentication service with JWT and auto-registration.
 */
class AuthService
{
    private Database $db;
    private array $jwtConfig;
    private LoggerManager $logger;

    public function __construct(Database $db, array $jwtConfig, LoggerManager $logger)
    {
        $this->db = $db;
        $this->jwtConfig = $jwtConfig;
        $this->logger = $logger;
    }

    /**
     * Authenticate user or create new one if not exists.
     *
     * @param string $login
     * @param string $password
     * @return array{token: string, user: array}
     */
    public function login(string $login, string $password): array
    {
        $logger = $this->logger->getLogger('auth');
        $logger->info("Login attempt for user: {$login}");

        // Find user by login
        $user = $this->db->fetchOne(
            'SELECT id, login, password_hash, created_at FROM users WHERE login = ?',
            [$login],
            's'
        );

        // If user not found - create new
        if ($user === null) {
            $logger->info("User not found, creating new user: {$login}");
            $this->db->query(
                'INSERT INTO users (login, password_hash) VALUES (?, ?)',
                [$login, password_hash($password, PASSWORD_BCRYPT)],
                'ss'
            );

            $userId = $this->db->getLastInsertId();
            $user = [
                'id' => $userId,
                'login' => $login,
                'password_hash' => '',
                'created_at' => date('Y-m-d H:i:s'),
            ];
        } else {
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $logger->warning("Invalid password for user: {$login}");
                throw new \InvalidArgumentException('Invalid login or password');
            }
        }

        // Generate JWT token
        $token = $this->generateToken((int) $user['id'], $user['login']);

        $logger->info("User authenticated successfully: {$login} (ID: {$user['id']})");

        return [
            'token' => $token,
            'user' => [
                'id' => (string) $user['id'],
                'login' => $user['login'],
                'createdAt' => $user['created_at'],
            ],
        ];
    }

    /**
     * Generate JWT token.
     *
     * @param int $userId
     * @param string $login
     * @return string
     */
    public function generateToken(int $userId, string $login): string
    {
        $issuedAt = new DateTimeImmutable();
        $expire = $issuedAt->modify('+' . $this->jwtConfig['ttl'] . ' seconds');

        $payload = [
            'iss' => $this->jwtConfig['issuer'],
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expire->getTimestamp(),
            'sub' => (string) $userId,
            'login' => $login,
        ];

        return JWT::encode($payload, $this->jwtConfig['secret'], $this->jwtConfig['algorithm']);
    }

    /**
     * Validate and decode JWT token.
     *
     * @param string $token
     * @return array Decoded payload
     * @throws \InvalidArgumentException
     */
    public function validateToken(string $token): array
    {
        try {
            $decoded = JWT::decode(
                $token,
                new Key($this->jwtConfig['secret'], $this->jwtConfig['algorithm'])
            );

            return (array) $decoded;
        } catch (ExpiredException $e) {
            throw new \InvalidArgumentException('Token has expired', 0, $e);
        } catch (SignatureInvalidException $e) {
            throw new \InvalidArgumentException('Invalid token signature', 0, $e);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid token', 0, $e);
        }
    }

    /**
     * Get user by ID.
     *
     * @param int $userId
     * @return array|null
     */
    public function getUserById(int $userId): ?array
    {
        $user = $this->db->fetchOne(
            'SELECT id, login, created_at FROM users WHERE id = ?',
            [$userId],
            'i'
        );

        if ($user === null) {
            return null;
        }

        return [
            'id' => (string) $user['id'],
            'login' => $user['login'],
            'createdAt' => $user['created_at'],
        ];
    }
}
