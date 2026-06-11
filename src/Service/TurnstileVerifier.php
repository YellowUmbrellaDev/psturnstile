<?php

namespace Sigterm\PsTurnstile\Service;

class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    private const TIMEOUT_SECONDS = 3;

    /** @var array<string, bool> */
    private array $requestCache = [];

    public function verify(string $token, string $secretKey, ?string $remoteIp = null, bool $failOpen = false): bool
    {
        $token = trim($token);
        $secretKey = trim($secretKey);

        if ($token === '' || $secretKey === '') {
            return false;
        }

        $cacheKey = hash('sha256', $token . '|' . $secretKey . '|' . (string) $remoteIp);
        if (array_key_exists($cacheKey, $this->requestCache)) {
            return $this->requestCache[$cacheKey];
        }

        $payload = [
            'secret' => $secretKey,
            'response' => $token,
        ];
        if ($remoteIp !== null && $remoteIp !== '') {
            $payload['remoteip'] = $remoteIp;
        }

        $response = $this->post($payload);
        if ($response === null) {
            return $this->requestCache[$cacheKey] = $failOpen;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return $this->requestCache[$cacheKey] = $failOpen;
        }

        return $this->requestCache[$cacheKey] = !empty($decoded['success']);
    }

    /**
     * @param array<string, string> $payload
     */
    private function post(array $payload): ?string
    {
        if (function_exists('curl_init')) {
            return $this->postWithCurl($payload);
        }

        return $this->postWithStreams($payload);
    }

    /**
     * @param array<string, string> $payload
     */
    private function postWithCurl(array $payload): ?string
    {
        $handle = curl_init(self::VERIFY_URL);
        if ($handle === false) {
            return null;
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if (!is_string($response) || $statusCode < 200 || $statusCode >= 300) {
            return null;
        }

        return $response;
    }

    /**
     * @param array<string, string> $payload
     */
    private function postWithStreams(array $payload): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($payload),
                'timeout' => self::TIMEOUT_SECONDS,
            ],
        ]);

        $response = @file_get_contents(self::VERIFY_URL, false, $context);
        if (empty($http_response_header) || !preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $http_response_header[0], $matches)) {
            return null;
        }

        $statusCode = (int) $matches[1];
        if ($statusCode < 200 || $statusCode >= 300) {
            return null;
        }

        return is_string($response) ? $response : null;
    }
}
