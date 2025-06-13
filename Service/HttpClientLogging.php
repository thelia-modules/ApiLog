<?php
namespace ApiLog\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use ApiLog\Logger\CustomLogger;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class HttpClientLogging implements HttpClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly CustomLogger $logger,
    ) {}

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        try {
            $this->logger->logHttpRequest(
                $method,
                $url,
                $options,
            );
            $start = microtime(true);
            $response = $this->client->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            $duration = round((microtime(true) - $start) * 1000, 2);
            $this->logger->logHttpResponse(
                $method,
                $url,
                $statusCode,
                $duration,
                $options,
            );

            return $response;
        } catch (\Throwable $e) {
            $this->logger->logHttpError($method, $url, $e, $options);
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
