<?php

declare(strict_types=1);

namespace ApiLog\Tests\Logger;

use ApiLog\Logger\LogSanitizer;
use PHPUnit\Framework\TestCase;

final class LogSanitizerTest extends TestCase
{
    private const TOKEN = 'eyJhbGciOiJIUzI1NiJ9.super-secret-token';

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

    public function testRedactsAuthenticationOptions(): void
    {
        $sanitized = LogSanitizer::sanitizeOptions([
            'auth_bearer' => self::TOKEN,
            'auth_basic' => ['api-user', 'api-password'],
            'auth_ntlm' => 'domain\\user:password',
        ]);

        self::assertSame([
            'auth_bearer' => LogSanitizer::REDACTED,
            'auth_basic' => LogSanitizer::REDACTED,
            'auth_ntlm' => LogSanitizer::REDACTED,
        ], $sanitized);
    }

    public function testRedactsSensitiveHeadersWhateverTheCase(): void
    {
        $sanitized = LogSanitizer::sanitizeOptions([
            'headers' => [
                'Authorization' => 'Bearer '.self::TOKEN,
                'COOKIE' => 'PHPSESSID=abcdef',
                'Set-Cookie' => 'session=abcdef',
                'proxy-authorization' => 'Basic Zm9vOmJhcg==',
                'X-Api-Key' => 'gateway-api-key',
                'X-Auth-Token' => 'another-secret',
                'Accept' => 'application/json',
            ],
        ]);

        self::assertSame([
            'headers' => [
                'Authorization' => LogSanitizer::REDACTED,
                'COOKIE' => LogSanitizer::REDACTED,
                'Set-Cookie' => LogSanitizer::REDACTED,
                'proxy-authorization' => LogSanitizer::REDACTED,
                'X-Api-Key' => LogSanitizer::REDACTED,
                'X-Auth-Token' => LogSanitizer::REDACTED,
                'Accept' => 'application/json',
            ],
        ], $sanitized);
    }

    public function testRedactsHeadersGivenAsAList(): void
    {
        $sanitized = LogSanitizer::sanitizeOptions([
            'headers' => [
                'Authorization: Bearer '.self::TOKEN,
                'Accept: application/json',
            ],
        ]);

        self::assertSame([
            'headers' => [
                'Authorization: '.LogSanitizer::REDACTED,
                'Accept: application/json',
            ],
        ], $sanitized);
    }

    public function testReplacesTheSecretInsteadOfTruncatingIt(): void
    {
        $sanitized = LogSanitizer::sanitizeOptions(['auth_bearer' => self::TOKEN]);

        self::assertStringNotContainsString('eyJhbGciOiJIUzI1NiJ9', json_encode($sanitized, \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('super-secret', json_encode($sanitized, \JSON_THROW_ON_ERROR));
    }

    public function testRedactsTheWholeBody(): void
    {
        $sanitized = LogSanitizer::sanitizeOptions([
            'body' => 'grant_type=password&username=jane&password=hunter2',
        ]);

        self::assertSame(['body' => LogSanitizer::REDACTED], $sanitized);
    }

    public function testRedactsSecretKeysInsideAJsonPayloadAndKeepsTheRest(): void
    {
        $sanitized = LogSanitizer::sanitizeOptions([
            'json' => [
                'reference' => 'ORD-42',
                'password' => 'hunter2',
                'client_secret' => 'shhh',
                'nested' => [
                    'access_token' => self::TOKEN,
                    'amount' => 1250,
                ],
            ],
        ]);

        self::assertSame([
            'json' => [
                'reference' => 'ORD-42',
                'password' => LogSanitizer::REDACTED,
                'client_secret' => LogSanitizer::REDACTED,
                'nested' => [
                    'access_token' => LogSanitizer::REDACTED,
                    'amount' => 1250,
                ],
            ],
        ], $sanitized);
    }

    public function testRedactsSecretQueryParametersOfTheOptions(): void
    {
        $sanitized = LogSanitizer::sanitizeOptions([
            'query' => [
                'page' => 2,
                'api_key' => 'carrier-api-key',
            ],
        ]);

        self::assertSame([
            'query' => [
                'page' => 2,
                'api_key' => LogSanitizer::REDACTED,
            ],
        ], $sanitized);
    }

    public function testKeepsHarmlessOptionsUntouched(): void
    {
        $options = [
            'timeout' => 30,
            'max_redirects' => 3,
            'headers' => ['Accept' => 'application/json'],
        ];

        self::assertSame($options, LogSanitizer::sanitizeOptions($options));
    }

    public function testRedactsProxyCredentials(): void
    {
        $sanitized = LogSanitizer::sanitizeOptions([
            'proxy' => 'http://proxy-user:proxy-password@proxy.example.com:3128',
        ]);

        self::assertSame(
            ['proxy' => 'http://'.LogSanitizer::REDACTED.'@proxy.example.com:3128'],
            $sanitized
        );
    }

    public function testRedactsUrlUserInfo(): void
    {
        self::assertSame(
            'https://'.LogSanitizer::REDACTED.'@api.example.com/orders',
            LogSanitizer::sanitizeUrl('https://api-user:api-password@api.example.com/orders')
        );
    }

    public function testRedactsSecretUrlQueryParameters(): void
    {
        self::assertSame(
            'https://api.example.com/orders?page=2&access_token='.LogSanitizer::REDACTED.'&locale=fr_FR',
            LogSanitizer::sanitizeUrl('https://api.example.com/orders?page=2&access_token='.self::TOKEN.'&locale=fr_FR')
        );
    }

    public function testRedactsUrlsEmbeddedInFreeText(): void
    {
        $message = 'Could not connect to server for "https://api-user:api-password@api.example.com/orders?token='
            .self::TOKEN.'".';

        self::assertSame(
            'Could not connect to server for "https://'.LogSanitizer::REDACTED
                .'@api.example.com/orders?token='.LogSanitizer::REDACTED.'".',
            LogSanitizer::sanitizeText($message)
        );
    }

    public function testLeavesFreeTextWithoutUrlUntouched(): void
    {
        self::assertSame('Timeout after 30 seconds', LogSanitizer::sanitizeText('Timeout after 30 seconds'));
    }

    public function testLeavesAHarmlessUrlUntouched(): void
    {
        $url = 'https://api.example.com/orders?page=2&locale=fr_FR#top';

        self::assertSame($url, LogSanitizer::sanitizeUrl($url));
    }
}
