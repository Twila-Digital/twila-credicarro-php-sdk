<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use PHPUnit\Framework\TestCase;
use Twila\Credicarro\Customers\CreateCustomerRequest;
use Twila\Credicarro\Customers\CustomerAddress;
use Twila\Credicarro\Customers\OccupationType;
use Twila\Credicarro\Customers\ProfessionalData;
use Twila\Credicarro\Customers\UpsertCustomerRequest;
use Twila\Credicarro\Errors\CredicarroApiException;
use Twila\Credicarro\Errors\CredicarroValidationException;

final class CustomersClientTest extends TestCase
{
    private const CUSTOMER_ID = '123e4567-e89b-12d3-a456-426614174000';

    private function address(): CustomerAddress
    {
        return new CustomerAddress(
            'Av. Paulista',
            'Bela Vista',
            'São Paulo',
            'SP',
            '01311000',
            '1578',
            'Bloco B, Apto 1203'
        );
    }

    public function testUpsertSendsUnifiedBodyAndReturnsCustomerId(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, ['id_cliente' => self::CUSTOMER_ID])]);

        $id = $h->client->customers->upsert(new UpsertCustomerRequest(
            '12345678909',
            'Maria',
            'Souza',
            'maria.souza@exemplo.com.br',
            '+55 (11) 99876-5432',
            '1990-05-20T00:00:00-03:00',
            $this->address(),
            new ProfessionalData(OccupationType::EMPLOYEE, 6500.0)
        ));

        self::assertSame(self::CUSTOMER_ID, $id);
        $request = $h->lastApiRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/integration/v1/customer/unified', $request->getUri()->getPath());
        self::assertEquals([
            'cpf' => '12345678909',
            'nome' => 'Maria',
            'sobrenome' => 'Souza',
            'email' => 'maria.souza@exemplo.com.br',
            'celular' => '+55 (11) 99876-5432',
            'dataDeNascimento' => '1990-05-20T00:00:00-03:00',
            'endereco' => [
                'rua' => 'Av. Paulista',
                'numero' => '1578',
                'complemento' => 'Bloco B, Apto 1203',
                'bairro' => 'Bela Vista',
                'cidade' => 'São Paulo',
                'estado' => 'SP',
                'cep' => '01311000',
            ],
            'dadosProfissionais' => ['tipoOcupacao' => 3, 'renda' => 6500.0],
        ], TestClientFactory::bodyOf($request));
    }

    public function testOptionalAddressFieldsAreOmittedWhenNull(): void
    {
        $h = TestClientFactory::harness([new \GuzzleHttp\Psr7\Response(202)]);

        $h->client->customers->upsertAddress(
            '12345678909',
            new CustomerAddress('Av. Paulista', 'Bela Vista', 'São Paulo', 'SP', '01311-000')
        );

        $body = TestClientFactory::bodyOf($h->lastApiRequest());
        self::assertSame('12345678909', $body['cpf']);
        self::assertArrayNotHasKey('numero', $body['endereco']);
        self::assertArrayNotHasKey('complemento', $body['endereco']);
        self::assertSame('01311-000', $body['endereco']['cep']);
    }

    public function testCreateSendsOnlyPersonalFields(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, ['id_cliente' => self::CUSTOMER_ID])]);

        $id = $h->client->customers->create(new CreateCustomerRequest(
            '12345678909',
            'Maria',
            'Souza',
            'maria.souza@exemplo.com.br',
            '+55 (11) 99876-5432',
            '1990-05-20T00:00:00-03:00'
        ));

        self::assertSame(self::CUSTOMER_ID, $id);
        $request = $h->lastApiRequest();
        self::assertSame('/integration/v1/customer', $request->getUri()->getPath());
        self::assertSame(
            ['celular', 'cpf', 'dataDeNascimento', 'email', 'nome', 'sobrenome'],
            $this->sortedKeys(TestClientFactory::bodyOf($request))
        );
    }

    public function testUpsertAddressPostsToAddressRouteAndAccepts202(): void
    {
        $h = TestClientFactory::harness([new \GuzzleHttp\Psr7\Response(202, [], 'Accepted')]);

        $h->client->customers->upsertAddress('12345678909', $this->address());

        $request = $h->lastApiRequest();
        self::assertSame('/integration/v1/customer/address', $request->getUri()->getPath());
        $body = TestClientFactory::bodyOf($request);
        self::assertSame(['cpf', 'endereco'], $this->sortedKeys($body));
        self::assertSame('Av. Paulista', $body['endereco']['rua']);
    }

    public function testUpsertProfessionalDataPostsOccupationAndIncome(): void
    {
        $h = TestClientFactory::harness([new \GuzzleHttp\Psr7\Response(202)]);

        $h->client->customers->upsertProfessionalData(
            '12345678909',
            new ProfessionalData(OccupationType::BUSINESS_OWNER, 12000.5)
        );

        $request = $h->lastApiRequest();
        self::assertSame('/integration/v1/customer/professional', $request->getUri()->getPath());
        self::assertSame(
            ['cpf' => '12345678909', 'tipoOcupacao' => 7, 'renda' => 12000.5],
            TestClientFactory::bodyOf($request)
        );
    }

    public function testConflictRaisesApiExceptionWithStableCode(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(409, [
            'tipo' => 'Cpf.CpfAlreadyAdded',
            'titulo' => 'CPF já cadastrado',
            'status' => 409,
        ])]);

        try {
            $h->client->customers->create(
                new CreateCustomerRequest('1', 'M', 'S', 'm@e.com', '+55 (11) 99876-5432', 'x')
            );
            self::fail('Esperava CredicarroApiException.');
        } catch (CredicarroApiException $e) {
            self::assertSame(409, $e->getStatusCode());
            self::assertSame('Cpf.CpfAlreadyAdded', $e->getErrorCode());
        }
    }

    public function testBlankCpfIsRejectedBeforeTheNetworkCall(): void
    {
        $h = TestClientFactory::harness([]);

        try {
            $h->client->customers->upsertAddress('  ', $this->address());
            self::fail('Esperava CredicarroValidationException.');
        } catch (CredicarroValidationException $e) {
            self::assertSame('Validation.Field.Required', $e->getErrorCode());
            self::assertSame('cpf', $e->getField());
            self::assertCount(0, $h->tokenRequests());
        }
    }

    public function testMissingCustomerIdInResponseRaisesApiException(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, [])]);

        $this->expectException(CredicarroApiException::class);
        $h->client->customers->create(new CreateCustomerRequest('1', 'M', 'S', 'm@e.com', '+55 (11) 99876-5432', 'x'));
    }

    /**
     * @param array<mixed> $array
     * @return array<int, string>
     */
    private function sortedKeys(array $array): array
    {
        $keys = array_map('strval', array_keys($array));
        sort($keys);

        return $keys;
    }
}
