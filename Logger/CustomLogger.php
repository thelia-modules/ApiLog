<?php

namespace ApiLog\Logger;

use Symfony\Component\DependencyInjection\ContainerInterface;

class CustomLogger
{
    private ?object $logger;

    public function __construct(
        ContainerInterface $container,
    ) {
        $this->logger = $container->get('monolog.logger.api_log');
    }

    public function logHttpRequest(string $method, string $url, array $options = []): void
    {
        $this->logger->info(
            '[HTTP] REQUEST ' . $method . ' ' . $url,
            $options,
        );
    }

    public function logApiRequest(
        string $method,
        string $requestPath,
        array $options = []
    ): void
    {
        $this->logger->info(
            '[API] REQUEST ' . $method . ' ' . $requestPath,
            $options,
        );
    }

    public function logHttpResponse(
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

    public function logApiResponse(
        string $method,
        string $requestPath,
        int $statusCode,
        array $options = [],
    ): void
    {
        $this->logger->info(
            '[API] RESPONSE ' . $method . ' ' . $requestPath,
            [
                'status' => $statusCode,
                'options' => $options,
            ]
        );
    }

    public function logHttpError(string $method, string $url, \Throwable $e, array $options = []): void
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
