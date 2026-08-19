<?php

declare(strict_types=1);

namespace ApiLog\Tests\Logger;

use ApiLog\Logger\CustomLogger;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Guards the api_log channel against authentication material: whatever the caller
 * hands over, nothing written to the log may expose a usable credential.
 */
final class CustomLoggerTest extends TestCase
{
    private const TOKEN = 'eyJhbGciOiJIUzI1NiJ9.super-secret-token';
    private const COOKIE = 'PHPSESSID=deadbeefsession';

    private TestHandler $handler;
    private CustomLogger $customLogger;

    public static function setUpBeforeClass(): void
    {
        // Module classes are registered by the Thelia kernel at boot, not by Composer.
        spl_autoload_register(static function (string $class): void {
            if (!str_starts_with($class, 'ApiLog\\')) {
                return;
            }

            $file = \dirname(__DIR__, 2).'/'.str_replace('\\', '/', substr($class, \strlen('ApiLog\\'))).'.php';

            if (is_file($file)) {
                require_once $file;
            }
        });
    }

    protected function setUp(): void
    {
        $this->handler = new TestHandler();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn(new Logger('api_log', [$this->handler]));

        $this->customLogger = new CustomLogger($container);
    }

    public function testHttpResponseLogsTheUsefulLineWithoutAnySecret(): void
    {
        $this->customLogger->logHttpResponse('GET', 'https://api.example.com/orders', 200, 12.5, $this->options());

        $record = $this->handler->getRecords()[0];

        self::assertStringContainsString('[HTTP] RESPONSE GET https://api.example.com/orders', $record->message);
        self::assertSame(200, $record->context['status']);
        self::assertSame(12.5, $record->context['duration_ms']);
        self::assertArrayHasKey('options', $record->context);
        $this->assertRecordLeaksNothing($record->message, $record->context);
    }

    public function testHttpErrorLogsTheUsefulLineWithoutAnySecret(): void
    {
        $this->customLogger->logHttpError(
            'POST',
            'https://api.example.com/token',
            new \RuntimeException('connection refused'),
            $this->options()
        );

        $record = $this->handler->getRecords()[0];

        self::assertStringContainsString('[HTTP] ERROR POST https://api.example.com/token', $record->message);
        self::assertSame('connection refused', $record->context['message']);
        $this->assertRecordLeaksNothing($record->message, $record->context);
    }

    public function testApipResponseLogsTheUsefulLineWithoutAnySecret(): void
    {
        $this->customLogger->logApipResponse('GET', '/api/admin/products', 200, 8.0, [
            'page' => '2',
            'access_token' => self::TOKEN,
        ]);

        $record = $this->handler->getRecords()[0];

        self::assertStringContainsString('[APIP] RESPONSE GET /api/admin/products', $record->message);
        self::assertSame(200, $record->context['status']);
        self::assertSame('2', $record->context['options']['page']);
        $this->assertRecordLeaksNothing($record->message, $record->context);
    }

    public function testUrlCredentialsNeverReachTheLoggedMessage(): void
    {
        $this->customLogger->logHttpResponse(
            'GET',
            'https://api-user:api-password@api.example.com/orders?token='.self::TOKEN,
            200,
            3.0
        );

        $record = $this->handler->getRecords()[0];

        self::assertStringContainsString('api.example.com/orders', $record->message);
        self::assertStringNotContainsString('api-password', $record->message);
        self::assertStringNotContainsString(self::TOKEN, $record->message);
    }

    public function testTransportExceptionMessageIsScrubbedOfCredentials(): void
    {
        $this->customLogger->logHttpError(
            'GET',
            'https://api-user:api-password@api.example.com/orders',
            new \RuntimeException('Could not connect to server for "https://api-user:api-password@api.example.com/orders".'),
            []
        );

        $record = $this->handler->getRecords()[0];

        self::assertStringContainsString('Could not connect to server', $record->context['message']);
        self::assertStringNotContainsString('api-password', $record->context['message']);
    }

    private function options(): array
    {
        return [
            'auth_bearer' => self::TOKEN,
            'auth_basic' => ['api-user', 'api-password'],
            'headers' => [
                'Authorization' => 'Bearer '.self::TOKEN,
                'Cookie' => self::COOKIE,
                'Accept' => 'application/json',
            ],
            'body' => 'grant_type=password&password=hunter2',
            'timeout' => 30,
        ];
    }

    private function assertRecordLeaksNothing(string $message, array $context): void
    {
        $written = $message.' '.json_encode($context, \JSON_THROW_ON_ERROR);

        foreach ([self::TOKEN, 'super-secret-token', 'api-password', 'deadbeefsession', 'hunter2'] as $secret) {
            self::assertStringNotContainsString($secret, $written);
        }
    }
}
