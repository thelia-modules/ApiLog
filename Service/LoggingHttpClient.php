<?php
namespace ApiLog\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use ApiLog\Logger\HttpClientLogger;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class LoggingHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly HttpClientLogger $logger
    ) {}

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $start = microtime(true);

        try {
            $response = $this->client->request($method, $url, $options);
            $duration = round((microtime(true) - $start) * 1000, 2); // ms
            $this->logger->logResponse(
                $method,
                $url,
                $response->getStatusCode(),
                $duration,
                $options,
            );
            return $response;
        } catch (\Throwable $e) {
            $this->logger->logError($method, $url, $e, $options);
            throw $e;
        }
    }

    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        return new self(
            $this->client->withOptions($options),
            $this->logger
        );
    }
}
