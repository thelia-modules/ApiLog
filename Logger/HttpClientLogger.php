<?php

namespace ApiLog\Logger;

use Psr\Log\LoggerInterface;

class HttpClientLogger
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function logRequest(string $method, string $url, array $options = []): void
    {
        $this->logger->info(
            '[HTTP] REQUEST ' . $method . ' ' . $url,
            $options,
        );
    }

    public function logResponse(
        string $method,
        string $url,
        int $statusCode,
        string $duration,
        array $options = [],
    ): void
    {
        $this->logger->info(
            '[HTTP] RESPONSE ' . $method . ' ' . $url,
            [
                'status' => $statusCode,
                'duration_ms' => $duration,
                'options' => $options,
            ]
        );
    }

    public function logError(string $method, string $url, \Throwable $e, array $options = []): void
    {
        $this->logger->error(
            '[HTTP] ERROR ' . $method . ' ' . $url,
            [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'options' => $options,
            ]
        );
    }
}
