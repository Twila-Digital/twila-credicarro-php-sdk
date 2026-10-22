<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use PHPUnit\Framework\TestCase;
use Twila\Credicarro\Errors\CredicarroApiException;
use Twila\Credicarro\Errors\CredicarroAuthenticationException;
use Twila\Credicarro\Errors\CredicarroBusinessRuleException;
use Twila\Credicarro\Errors\CredicarroException;
use Twila\Credicarro\Errors\CredicarroRateLimitException;
use Twila\Credicarro\Errors\CredicarroValidationException;
use Twila\Credicarro\Errors\ExceptionFactory;
use Twila\Credicarro\Internal\Http\ApiResponse;

final class ErrorsTest extends TestCase
{
    /**
     * @param mixed $body
     * @param array<string, string> $headers
     */
    private function build(int $status, $body, array $headers = []): CredicarroException
    {
        $raw = $body === null ? '' : (string) json_encode($body);

        return ExceptionFactory::fromResponse(new ApiResponse($status, $body, $headers, $raw));
    }

    public function test422MapsToBusinessRuleException(): void
    {
        $error = $this->build(422, [
            'tipo' => 'Store.Integration.Intermediation',
            'titulo' => 'Regra de negócio não atendida',
            'status' => 422,
            'detalhe' => 'A loja possui contrato de intermediação e não é elegível para a integração.',
        ]);

        self::assertInstanceOf(CredicarroBusinessRuleException::class, $error);
        self::assertInstanceOf(CredicarroApiException::class, $error);
        self::assertSame(422, $error->getStatusCode());
        self::assertSame('Store.Integration.Intermediation', $error->getErrorCode());
        self::assertSame('Regra de negócio não atendida', $error->getTitle());
        self::assertNull($error->getField());
    }

    public function test400MapsToValidationExceptionWithFieldAndErrors(): void
    {
        $error = $this->build(400, [
            'tipo' => 'Validation.InvalidFormat',
            'titulo' => 'Requisição inválida',
            'status' => 400,
            'detalhe' => "O valor informado para 'valorVeiculo' não é um número válido.",
            'campo' => 'valorVeiculo',
            'erros' => [
                ['codigo' => 'Validation.InvalidFormat', 'mensagem' => 'Número inválido.', 'campo' => 'valorVeiculo'],
            ],
        ]);

        self::assertInstanceOf(CredicarroValidationException::class, $error);
        self::assertSame(400, $error->getStatusCode());
        self::assertSame('valorVeiculo', $error->getField());
        self::assertCount(1, $error->getErrors());
        self::assertSame('Validation.InvalidFormat', $error->getErrors()[0]->code);
        self::assertSame('Número inválido.', $error->getErrors()[0]->message);
        self::assertSame('valorVeiculo', $error->getErrors()[0]->field);
    }

    public function test400WithoutErrorsListIsStillAValidationException(): void
    {
        $error = $this->build(400, ['tipo' => 'Validation.Cpf.Invalid', 'campo' => 'cpf']);

        self::assertInstanceOf(CredicarroValidationException::class, $error);
        self::assertSame([], $error->getErrors());
    }

    public function test401WithoutBodyMapsToAuthenticationException(): void
    {
        $error = $this->build(401, null);

        self::assertInstanceOf(CredicarroAuthenticationException::class, $error);
        self::assertSame(401, $error->getStatusCode());
        self::assertNull($error->getErrorCode());
        self::assertStringContainsString('401', $error->getMessage());
    }

    public function test429MapsToRateLimitExceptionWithRetryAfter(): void
    {
        $error = $this->build(429, ['tipo' => 'RateLimit'], ['retry-after' => '2']);

        self::assertInstanceOf(CredicarroRateLimitException::class, $error);
        self::assertSame(2000, $error->getRetryAfterMs());
    }

    public function test429WithoutRetryAfterHasNullWait(): void
    {
        $error = $this->build(429, null);

        self::assertInstanceOf(CredicarroRateLimitException::class, $error);
        self::assertNull($error->getRetryAfterMs());
    }

    public function testStandardProblemDetailsFallbackIsUnderstood(): void
    {
        $error = $this->build(500, [
            'type' => 'https://tools.ietf.org/html/rfc9110#section-15.6.1',
            'title' => 'Erro interno',
            'status' => 500,
            'detail' => 'Falha inesperada.',
        ]);

        self::assertInstanceOf(CredicarroApiException::class, $error);
        self::assertSame('https://tools.ietf.org/html/rfc9110#section-15.6.1', $error->getErrorCode());
        self::assertSame('Erro interno', $error->getTitle());
        self::assertSame('Falha inesperada.', $error->getDetail());
    }

    public function testPortugueseFieldsWinOverTheStandardOnes(): void
    {
        $error = $this->build(404, [
            'tipo' => 'Proposal.Access.NotFound',
            'type' => 'outro',
            'titulo' => 'A',
            'title' => 'B',
        ]);

        self::assertInstanceOf(CredicarroApiException::class, $error);
        self::assertSame('Proposal.Access.NotFound', $error->getErrorCode());
        self::assertSame('A', $error->getTitle());
    }

    public function testMessageHasOnlyStatusTypeAndTitleNeverDetailOrBody(): void
    {
        $error = $this->build(422, [
            'tipo' => 'Customer.Account.Incomplete',
            'titulo' => 'Regra de negócio não atendida',
            'detalhe' => 'O CPF 12345678909 de Maria Souza está incompleto.',
        ]);

        self::assertSame(
            'A API do Credicarro respondeu 422 [Customer.Account.Incomplete] - Regra de negócio não atendida.',
            $error->getMessage()
        );
        self::assertStringNotContainsString('12345678909', $error->getMessage());
        self::assertInstanceOf(CredicarroApiException::class, $error);
        self::assertSame('O CPF 12345678909 de Maria Souza está incompleto.', $error->getDetail());
        self::assertStringContainsString('Maria Souza', (string) $error->getRawBody());
    }

    public function testUnmappedStatusMapsToGenericApiException(): void
    {
        $error = $this->build(409, ['tipo' => 'Cpf.CpfAlreadyAdded']);

        self::assertSame(CredicarroApiException::class, get_class($error));
        self::assertSame(409, $error->getStatusCode());
        self::assertSame('Cpf.CpfAlreadyAdded', $error->getErrorCode());
    }

    public function testMessageFallsBackToGenericWhenNoProblemDetails(): void
    {
        $error = $this->build(503, null);

        self::assertStringContainsString('503', $error->getMessage());
        self::assertNull($error->getRawBody());
    }

    public function testEveryExceptionIsACredicarroException(): void
    {
        foreach ([400, 401, 404, 409, 422, 429, 500, 503] as $status) {
            self::assertInstanceOf(CredicarroException::class, $this->build($status, null));
        }
    }
}
