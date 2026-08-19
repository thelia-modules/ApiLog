<?php

declare(strict_types=1);

namespace ApiLog\Logger;

/**
 * Strips authentication material from everything written to the api_log channel.
 *
 * The HTTP client decorator logs the option array it was handed, and that array
 * routinely carries the credentials of the remote service (payment gateways,
 * carriers, webservices). Secrets are replaced by a constant marker rather than
 * truncated: the prefix of a token is still a leak.
 */
final class LogSanitizer
{
    public const REDACTED = '***REDACTED***';

    /**
     * Symfony HTTP client options holding credentials.
     */
    private const SECRET_OPTIONS = [
        'auth_bearer',
        'auth_basic',
        'auth_ntlm',
    ];

    /**
     * Headers holding credentials, compared lowercase.
     */
    private const SECRET_HEADERS = [
        'authorization',
        'cookie',
        'set-cookie',
        'proxy-authorization',
        'x-api-key',
    ];

    /**
     * Catch-all for keys named after a secret, wherever they sit in the payload.
     */
    private const SECRET_KEY_PATTERN = '~pass(word|wd)?|secret|token|api[_-]?key|credential|signature|private[_-]?key|auth~i';

    public static function sanitizeOptions(array $options): array
    {
        $sanitized = [];

        foreach ($options as $key => $value) {
            $name = \is_string($key) ? strtolower($key) : '';

            $sanitized[$key] = match (true) {
                \in_array($name, self::SECRET_OPTIONS, true) => self::REDACTED,
                'body' === $name => self::REDACTED,
                'json' === $name => \is_array($value) ? self::sanitizePayload($value) : self::REDACTED,
                'headers' === $name && \is_array($value) => self::sanitizeHeaders($value),
                ('proxy' === $name || 'base_uri' === $name) && \is_string($value) => self::sanitizeUrl($value),
                self::isSecretKey($key) => self::REDACTED,
                \is_array($value) => self::sanitizePayload($value),
                default => $value,
            };
        }

        return $sanitized;
    }

    /**
     * Removes the credentials a URL may embed, either as user information or as a
     * query parameter, while keeping the URL readable in the log.
     */
    public static function sanitizeUrl(string $url): string
    {
        $sanitized = preg_replace('~^([a-zA-Z][a-zA-Z0-9+.-]*://)[^/?#@]*@~', '$1'.self::REDACTED.'@', $url) ?? $url;

        $queryStart = strpos($sanitized, '?');

        if (false === $queryStart) {
            return $sanitized;
        }

        $query = substr($sanitized, $queryStart + 1);
        $fragment = '';
        $fragmentStart = strpos($query, '#');

        if (false !== $fragmentStart) {
            $fragment = substr($query, $fragmentStart);
            $query = substr($query, 0, $fragmentStart);
        }

        $pairs = [];

        foreach (explode('&', $query) as $pair) {
            $parameter = explode('=', $pair, 2)[0];
            $pairs[] = self::isSecretKey(urldecode($parameter)) ? $parameter.'='.self::REDACTED : $pair;
        }

        return substr($sanitized, 0, $queryStart + 1).implode('&', $pairs).$fragment;
    }

    /**
     * Scrubs the URLs embedded in free text. Transport exception messages quote the
     * requested URL, credentials included.
     */
    public static function sanitizeText(string $text): string
    {
        return preg_replace_callback(
            '~[a-zA-Z][a-zA-Z0-9+.-]*://[^\\s"\'<>]+~',
            static fn (array $match): string => self::sanitizeUrl($match[0]),
            $text
        ) ?? $text;
    }

    private static function sanitizePayload(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            $sanitized[$key] = match (true) {
                self::isSecretKey($key) => self::REDACTED,
                \is_array($value) => self::sanitizePayload($value),
                default => $value,
            };
        }

        return $sanitized;
    }

    private static function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];

        foreach ($headers as $key => $value) {
            if (\is_string($key)) {
                $sanitized[$key] = self::isSecretHeader($key) ? self::REDACTED : $value;

                continue;
            }

            // Symfony also accepts headers as a list of "Name: value" strings.
            if (\is_string($value) && str_contains($value, ':')) {
                $name = explode(':', $value, 2)[0];
                $sanitized[$key] = self::isSecretHeader($name) ? $name.': '.self::REDACTED : $value;

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private static function isSecretHeader(string $name): bool
    {
        return \in_array(strtolower(trim($name)), self::SECRET_HEADERS, true) || self::isSecretKey($name);
    }

    private static function isSecretKey(int|string $key): bool
    {
        return \is_string($key) && 1 === preg_match(self::SECRET_KEY_PATTERN, $key);
    }
}
