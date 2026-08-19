<?php

declare(strict_types=1);

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

    public function logHttpResponse(
        string $method,
        string $url,
        int $statusCode,
        float $duration,
        array $options = [],
    ): void
    {
        $this->logger->info(
            '[HTTP] RESPONSE ' . $method . ' ' . LogSanitizer::sanitizeUrl($url),
            [
                'status' => $statusCode,
                'duration_ms' => $duration,
                'options' => LogSanitizer::sanitizeOptions($options),
            ]
        );
    }

    public function logApipResponse(
        string $method,
        string $requestPath,
        int $statusCode,
        ?float $duration,
        array $options = [],
    ): void
    {
        $this->logger->info(
            '[APIP] RESPONSE ' . $method . ' ' . LogSanitizer::sanitizeUrl($requestPath),
            [
                'status' => $statusCode,
                'duration_ms' => $duration,
                'options' => LogSanitizer::sanitizeOptions($options),
            ]
        );
    }

    public function logHttpError(string $method, string $url, \Throwable $e, array $options = []): void
    {
        $this->logger->error(
            '[HTTP] ERROR ' . $method . ' ' . LogSanitizer::sanitizeUrl($url),
            [
                'message' => LogSanitizer::sanitizeText($e->getMessage()),
                'trace' => LogSanitizer::sanitizeText($e->getTraceAsString()),
                'options' => LogSanitizer::sanitizeOptions($options),
            ]
        );
    }
}
