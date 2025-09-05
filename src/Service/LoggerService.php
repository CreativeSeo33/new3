<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Сервис для централизованного логирования
 */
class LoggerService
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    public function logRequest(string $method, string $uri, int $statusCode = null): void
    {
        $this->logger->info('HTTP Request', [
            'method' => $method,
            'uri' => $uri,
            'status_code' => $statusCode,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    public function logDatabaseQuery(string $query, array $params = []): void
    {
        $this->logger->debug('Database Query', [
            'query' => $query,
            'params' => $params,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    public function logUserAction(string $action, string $userId = null, array $extra = []): void
    {
        $this->logger->info('User Action', [
            'action' => $action,
            'user_id' => $userId,
            'extra' => $extra,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    public function logError(\Throwable $exception, string $context = ''): void
    {
        $this->logger->error('Exception occurred', [
            'exception' => get_class($exception),
            'message' => $exception->message,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
