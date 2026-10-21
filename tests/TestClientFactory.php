<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Twila\Credicarro\Config\ClientOptions;
use Twila\Credicarro\Config\ResilienceOptions;
use Twila\Credicarro\CredicarroClient;

final class TestClientFactory
{
    public const BASE_URL = 'https://sdk-test.local/integration/';

    public const UUID = 'bea8d060-f93d-48eb-b6c4-45f64483cdd9';

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public static function json(int $status, array $body, array $headers = []): Response
    {
        return new Response($status, $headers, (string) json_encode($body));
    }

    public static function noContent(int $status = 204): Response
    {
        return new Response($status);
    }

    public static function tokenResponse(string $token = 'test-token', ?string $expiresAt = null): Response
    {
        $body = [
            'token_de_acesso' => $token,
            'expira_em_segundos' => 3600,
            'tipo_de_token' => 'Bearer',
            'escopo' => 'credicarro.integration.access/full',
        ];

        if ($expiresAt !== null) {
            $body['expira_em'] = $expiresAt;
        }

        return self::json(200, $body);
    }

    /**
     * Resiliência rápida para os testes: backoff de 1 ms.
     */
    public static function fastResilience(): ResilienceOptions
    {
        return new ResilienceOptions(5000, 1000, 60000, 3, 1);
    }

    /**
     * Cria um CredicarroClient cujas chamadas HTTP (token + recurso) compartilham a mesma fila do MockHandler.
     * A resposta de token padrão é enfileirada primeiro; as demais seguem a ordem real das chamadas.
     *
     * @param array<int, Response> $responses
     */
    public static function withMockResponses(array $responses, ?ResilienceOptions $resilience = null): CredicarroClient
    {
        return self::harness($responses, $resilience)->client;
    }

    /**
     * Igual a withMockResponses, mas devolve também o histórico das requisições enviadas (inclusive a do token).
     *
     * @param array<int, Response> $responses
     */
    public static function harness(
        array $responses,
        ?ResilienceOptions $resilience = null,
        bool $includeDefaultToken = true
    ): TestHarness {
        $queue = $includeDefaultToken ? array_merge([self::tokenResponse()], $responses) : $responses;
        $mock = new MockHandler($queue);
        $handlerStack = HandlerStack::create($mock);

        $harness = new TestHarness();
        $handlerStack->push(Middleware::history($harness->history));

        $httpClient = new Client(['handler' => $handlerStack, 'http_errors' => false]);
        $options = new ClientOptions(
            'test-client-id',
            'test-client-secret',
            'production',
            self::BASE_URL,
            $resilience ?? self::fastResilience()
        );

        $harness->client = new CredicarroClient($options, $httpClient);
        $harness->mock = $mock;

        return $harness;
    }

    /**
     * @return array<mixed>
     */
    public static function bodyOf(RequestInterface $request): array
    {
        $decoded = json_decode((string) $request->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
