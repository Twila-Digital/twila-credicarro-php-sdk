<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Yaml\Yaml;
use Twila\Credicarro\CredicarroClient;
use Twila\Credicarro\Customers\CreateCustomerRequest;
use Twila\Credicarro\Customers\CustomerAddress;
use Twila\Credicarro\Customers\OccupationType;
use Twila\Credicarro\Customers\ProfessionalData;
use Twila\Credicarro\Customers\UpsertCustomerRequest;
use Twila\Credicarro\Internal\Mapping\Wire;
use Twila\Credicarro\ListRequest;
use Twila\Credicarro\Proposals\CreateProposalRequest;
use Twila\Credicarro\Proposals\DepositAccount;
use Twila\Credicarro\Proposals\DepositAccountType;
use Twila\Credicarro\Proposals\DocumentFile;
use Twila\Credicarro\Proposals\DocumentType;
use Twila\Credicarro\Proposals\ProposalDocumentUpload;
use Twila\Credicarro\Proposals\ProposalParticipant;
use Twila\Credicarro\Proposals\VehicleDetails;
use Twila\Credicarro\Simulations\CreateFinancingSimulationRequest;
use Twila\Credicarro\Simulations\CreateRefinancingSimulationRequest;
use Twila\Credicarro\Tests\TestClientFactory;
use Twila\Credicarro\Webhooks\CreateWebhookRequest;
use Twila\Credicarro\Webhooks\UpdateWebhookRequest;
use Twila\Credicarro\Webhooks\WebhookAuthenticationType;
use Twila\Credicarro\Webhooks\WebhookStatus;
use Twila\Credicarro\Webhooks\WebhookType;

/**
 * Testes de contrato contra o OpenAPI público do Credicarro. O swagger de staging exige autenticação, então o
 * contrato vem de https://documentacao.credicarro.com.br/openapi.yaml (override: CREDICARRO_CONTRACT_TESTS_OPENAPI_URL).
 * Não roda a cada PR — só agendado e no pipeline de release (ver contract-tests.yml).
 *
 * Cada cenário executa o SDK de verdade contra um transporte simulado e confere, na requisição que ele monta e na
 * resposta que ele lê, rota, query, header Idempotency-Key e propriedades do corpo contra o schema da operação.
 */
final class WireContractTest extends TestCase
{
    private const DEFAULT_OPENAPI_URL = 'https://documentacao.credicarro.com.br/openapi.yaml';

    private const UUID = TestClientFactory::UUID;

    private const TOKEN_OPERATION = 'POST /v1/authentication/accesstoken';

    /** @var array<string, mixed>|null */
    private static $spec;

    /**
     * @return array<string, mixed>
     */
    private static function spec(): array
    {
        if (self::$spec !== null) {
            return self::$spec;
        }

        $url = getenv('CREDICARRO_CONTRACT_TESTS_OPENAPI_URL');
        $url = $url === false || $url === '' ? self::DEFAULT_OPENAPI_URL : $url;

        $response = (new Client(['timeout' => 30]))->get($url);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Falha ao buscar o openapi.yaml: HTTP %d', $response->getStatusCode()));
        }

        $parsed = Yaml::parse((string) $response->getBody());
        if (!is_array($parsed) || !isset($parsed['paths'])) {
            throw new \RuntimeException('O openapi.yaml baixado não tem a seção "paths".');
        }

        self::$spec = $parsed;

        return self::$spec;
    }

    /**
     * Resolve `$ref` locais (`#/components/...`).
     *
     * @param mixed $node
     * @return mixed
     */
    private static function resolve($node)
    {
        $guard = 0;
        while (is_array($node) && isset($node['$ref']) && $guard++ < 20) {
            $node = self::pointer((string) $node['$ref']);
        }

        return $node;
    }

    /**
     * @return mixed
     */
    private static function pointer(string $ref)
    {
        $node = self::spec();
        foreach (explode('/', ltrim($ref, '#/')) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                throw new \RuntimeException(sprintf('Referência %s não encontrada no openapi.yaml.', $ref));
            }
            $node = $node[$segment];
        }

        return $node;
    }

    /**
     * Schema com `allOf` mesclado e referências resolvidas (um nível).
     *
     * @param mixed $schema
     * @return array<string, mixed>
     */
    private static function normalize($schema): array
    {
        $schema = self::resolve($schema);
        if (!is_array($schema)) {
            return [];
        }

        if (isset($schema['allOf']) && is_array($schema['allOf'])) {
            $merged = $schema;
            unset($merged['allOf']);
            foreach ($schema['allOf'] as $part) {
                $part = self::normalize($part);
                $merged['properties'] = array_merge($merged['properties'] ?? [], $part['properties'] ?? []);
                $merged['required'] = array_merge($merged['required'] ?? [], $part['required'] ?? []);
            }

            return $merged;
        }

        return $schema;
    }

    /**
     * Todos os nomes de propriedade do schema, em qualquer profundidade.
     *
     * @param mixed $schema
     * @return array<int, string>
     */
    private static function allPropertyNames($schema, int $depth = 0): array
    {
        $schema = self::normalize($schema);
        $names = [];

        if ($depth > 8) {
            return $names;
        }

        foreach (($schema['properties'] ?? []) as $name => $property) {
            $names[] = (string) $name;
            $names = array_merge($names, self::allPropertyNames($property, $depth + 1));
        }

        if (isset($schema['items'])) {
            $names = array_merge($names, self::allPropertyNames($schema['items'], $depth + 1));
        }

        return array_values(array_unique($names));
    }

    /**
     * Valor de exemplo coerente com o schema, para alimentar os mappers de resposta.
     *
     * @param mixed $schema
     * @return mixed
     */
    private static function sample($schema, int $depth = 0)
    {
        $schema = self::normalize($schema);
        if ($depth > 8) {
            return null;
        }

        $type = $schema['type'] ?? (isset($schema['properties']) ? 'object' : 'string');

        switch ($type) {
            case 'object':
                $object = [];
                foreach (($schema['properties'] ?? []) as $name => $property) {
                    $object[(string) $name] = self::sample($property, $depth + 1);
                }

                return $object;
            case 'array':
                return [self::sample($schema['items'] ?? [], $depth + 1)];
            case 'integer':
                return isset($schema['enum']) ? $schema['enum'][0] : 1;
            case 'number':
                return 1.5;
            case 'boolean':
                return true;
            default:
                if (isset($schema['enum'])) {
                    return $schema['enum'][0];
                }
                if (($schema['format'] ?? '') === 'uuid') {
                    return self::UUID;
                }
                if (($schema['format'] ?? '') === 'date-time') {
                    return '2026-10-12T14:05:08-03:00';
                }

                return 'texto';
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function operation(string $method, string $requestPath): ?array
    {
        $path = (string) preg_replace('#^/integration#', '', $requestPath);

        $matches = [];
        foreach (self::spec()['paths'] as $template => $item) {
            $regex = '#^' . preg_replace('#\\\\\{[^}]+\\\\\}#', '[^/]+', preg_quote((string) $template, '#')) . '$#';
            if (preg_match($regex, $path) === 1 && isset($item[strtolower($method)])) {
                $matches[substr_count((string) $template, '{')][] = [
                    'template' => (string) $template,
                    'op' => $item[strtolower($method)],
                    'item' => $item,
                ];
            }
        }

        if ($matches === []) {
            return null;
        }

        ksort($matches);

        return reset($matches)[0];
    }

    /**
     * @param array<string, mixed> $found
     * @return array<string, array<string, mixed>> parâmetros declarados, indexados por "in:name"
     */
    private static function parameters(array $found): array
    {
        $declared = [];
        $lists = [$found['item']['parameters'] ?? [], $found['op']['parameters'] ?? []];
        foreach ($lists as $list) {
            foreach ($list as $parameter) {
                $parameter = self::resolve($parameter);
                $declared[$parameter['in'] . ':' . $parameter['name']] = $parameter;
            }
        }

        return $declared;
    }

    /**
     * Confere recursivamente: toda chave enviada existe no schema e toda propriedade do schema é enviável.
     *
     * @param mixed $wire
     * @param mixed $schema
     */
    private function assertWireMatchesSchema($wire, $schema, string $where, bool $requireAll): void
    {
        $schema = self::normalize($schema);
        $properties = $schema['properties'] ?? [];

        self::assertIsArray($wire, sprintf('%s: esperava um objeto JSON.', $where));

        foreach (array_keys($wire) as $key) {
            self::assertArrayHasKey(
                (string) $key,
                $properties,
                sprintf("%s: o SDK envia '%s', que não existe no schema da operação.", $where, $key)
            );
        }

        foreach (($schema['required'] ?? []) as $required) {
            self::assertArrayHasKey(
                (string) $required,
                $wire,
                sprintf("%s: o campo obrigatório '%s' não é enviado pelo SDK.", $where, $required)
            );
        }

        if ($requireAll) {
            foreach (array_keys($properties) as $name) {
                self::assertArrayHasKey(
                    (string) $name,
                    $wire,
                    sprintf("%s: a propriedade '%s' do schema não pode ser expressa pelo SDK.", $where, $name)
                );
            }
        }

        foreach ($wire as $key => $value) {
            $property = self::normalize($properties[(string) $key] ?? []);
            if (($property['type'] ?? null) === 'object' && is_array($value)) {
                $this->assertWireMatchesSchema($value, $property, $where . '.' . $key, $requireAll);
            }
        }
    }

    /**
     * @return array<string, array{0: string, 1: callable(CredicarroClient): mixed}>
     */
    public static function scenarioProvider(): array
    {
        $address = new CustomerAddress(
            'Av. Paulista',
            'Bela Vista',
            'São Paulo',
            'SP',
            '01311000',
            '1578',
            'Bloco B, Apto 1203'
        );
        $file = DocumentFile::fromBytes('%PDF-1.7 conteudo', 'cnh-comprador.pdf');
        $paging = new ListRequest(2, 20);

        return [
            'customers.upsert' => ['POST /v1/customer/unified', static function (CredicarroClient $c) use ($address) {
                return $c->customers->upsert(new UpsertCustomerRequest(
                    '12345678909',
                    'Maria',
                    'Souza',
                    'maria.souza@exemplo.com.br',
                    '+55 (11) 99876-5432',
                    '1990-05-20T00:00:00-03:00',
                    $address,
                    new ProfessionalData(OccupationType::EMPLOYEE, 6500.0)
                ), 'chave-explicita');
            }],
            'customers.create' => ['POST /v1/customer', static function (CredicarroClient $c) {
                return $c->customers->create(new CreateCustomerRequest(
                    '12345678909',
                    'Maria',
                    'Souza',
                    'maria.souza@exemplo.com.br',
                    '+55 (11) 99876-5432',
                    '1990-05-20T00:00:00-03:00'
                ));
            }],
            'customers.upsertAddress' => ['POST /v1/customer/address', static function (CredicarroClient $c) use ($address) {
                $c->customers->upsertAddress('12345678909', $address);
            }],
            'customers.upsertProfessionalData' => ['POST /v1/customer/professional', static function (CredicarroClient $c) {
                $c->customers->upsertProfessionalData('12345678909', new ProfessionalData(OccupationType::RETIRED, 3000.0));
            }],
            'simulations.getFinancingRange' => ['GET /v2/proposal/simulation/range/financing', static function (CredicarroClient $c) {
                return $c->simulations->getFinancingRange('ABC1D23', '11222333000181');
            }],
            'simulations.getRefinancingRange' => ['GET /v2/proposal/simulation/range/refinancing', static function (CredicarroClient $c) {
                return $c->simulations->getRefinancingRange('ABC1D23', '11222333000181');
            }],
            'simulations.createFinancing' => ['POST /v2/proposal/simulation/financing', static function (CredicarroClient $c) {
                return $c->simulations->createFinancing(
                    new CreateFinancingSimulationRequest('12345678909', 'ABC1D23', 45000.0, 5000.0, 'SP', false, '11222333000181')
                );
            }],
            'simulations.createRefinancing' => ['POST /v2/proposal/simulation/refinancing', static function (CredicarroClient $c) {
                return $c->simulations->createRefinancing(
                    new CreateRefinancingSimulationRequest('12345678909', 'ABC1D23', 25000.0, '11222333000181')
                );
            }],
            'simulations.get' => ['GET /v1/proposal/simulation/{simulacaoId}', static function (CredicarroClient $c) {
                return $c->simulations->get(self::UUID);
            }],
            'simulations.list' => ['GET /v1/proposal/simulation', static function (CredicarroClient $c) use ($paging) {
                return $c->simulations->list($paging);
            }],
            'proposals.create' => ['POST /v2/proposal', static function (CredicarroClient $c) {
                return $c->proposals->create(new CreateProposalRequest('12345678909', self::UUID));
            }],
            'proposals.get' => ['GET /v2/proposal/{propostaId}', static function (CredicarroClient $c) {
                return $c->proposals->get(self::UUID);
            }],
            'proposals.list' => ['GET /v1/proposal', static function (CredicarroClient $c) use ($paging) {
                return $c->proposals->list($paging);
            }],
            'proposals.sendVehicleDetails' => ['POST /v2/proposal/{propostaId}/vehicle-detail', static function (CredicarroClient $c) {
                $c->proposals->sendVehicleDetails(self::UUID, new VehicleDetails('12345678900', '9BWZZZ377VT004251', 'ABC1D23', 'PRATA'));
            }],
            'proposals.sendDepositAccount' => ['POST /v2/proposal/{propostaId}/deposit-account', static function (CredicarroClient $c) {
                $c->proposals->sendDepositAccount(
                    self::UUID,
                    new DepositAccount('12345678909', '001', '1234', '5', '123456', '7', DepositAccountType::CHECKING)
                );
            }],
            'proposals.uploadDocument' => ['POST /v2/proposal/{propostaId}/document', static function (CredicarroClient $c) use ($file) {
                $c->proposals->uploadDocument(
                    self::UUID,
                    new ProposalDocumentUpload(ProposalParticipant::BUYER, DocumentType::CNH, $file)
                );
            }],
            'webhooks.create' => ['POST /v1/webhook', static function (CredicarroClient $c) {
                return $c->webhooks->create(new CreateWebhookRequest(
                    WebhookType::PROPOSAL,
                    'https://parceiro.com.br/webhooks/credicarro',
                    WebhookAuthenticationType::BASIC,
                    'dXN1YXJpbzpzZW5oYQ=='
                ));
            }],
            'webhooks.list' => ['GET /v1/webhook', static function (CredicarroClient $c) use ($paging) {
                return $c->webhooks->list($paging);
            }],
            'webhooks.update' => ['PUT /v1/webhook/{webhookId}', static function (CredicarroClient $c) {
                $c->webhooks->update(
                    self::UUID,
                    new UpdateWebhookRequest(WebhookType::PROPOSAL, 'https://parceiro.com.br/novo', WebhookStatus::ACTIVE)
                );
            }],
            'webhooks.delete' => ['DELETE /v1/webhook/{webhookId}', static function (CredicarroClient $c) {
                $c->webhooks->delete(self::UUID);
            }],
            'v1.simulations.getFinancingRange' => ['GET /v1/proposal/simulation/range/financing', static function (CredicarroClient $c) {
                return $c->v1->simulations->getFinancingRange('ABC1D23');
            }],
            'v1.simulations.getRefinancingRange' => ['GET /v1/proposal/simulation/range/refinancing', static function (CredicarroClient $c) {
                return $c->v1->simulations->getRefinancingRange('ABC1D23');
            }],
            'v1.simulations.createFinancing' => ['POST /v1/proposal/simulation/financing', static function (CredicarroClient $c) {
                return $c->v1->simulations->createFinancing(
                    new CreateFinancingSimulationRequest('12345678909', 'ABC1D23', 45000.0, 5000.0, 'SP', true)
                );
            }],
            'v1.simulations.createRefinancing' => ['POST /v1/proposal/simulation/refinancing', static function (CredicarroClient $c) {
                return $c->v1->simulations->createRefinancing(
                    new CreateRefinancingSimulationRequest('12345678909', 'ABC1D23', 25000.0)
                );
            }],
            'v1.proposals.create' => ['POST /v1/proposal', static function (CredicarroClient $c) {
                return $c->v1->proposals->create(new CreateProposalRequest('12345678909', self::UUID));
            }],
            'v1.proposals.get' => ['GET /v1/proposal/{propostaId}', static function (CredicarroClient $c) {
                return $c->v1->proposals->get(self::UUID);
            }],
        ];
    }

    /**
     * @dataProvider scenarioProvider
     * @param callable(CredicarroClient): mixed $call
     */
    public function testSdkCallMatchesTheOpenApiOperation(string $expectedOperation, callable $call): void
    {
        $spec = self::spec();
        [$method, $templatePath] = explode(' ', $expectedOperation, 2);
        self::assertArrayHasKey($templatePath, $spec['paths'], "Rota {$templatePath} não existe no openapi.yaml.");
        $operation = $spec['paths'][$templatePath][strtolower($method)] ?? null;
        self::assertIsArray($operation, "Operação {$expectedOperation} não existe no openapi.yaml.");

        $successStatus = 200;
        $responseSchema = null;
        foreach ($operation['responses'] as $status => $definition) {
            if ((int) $status >= 200 && (int) $status < 300) {
                $successStatus = (int) $status;
                $definition = self::resolve($definition);
                $responseSchema = $definition['content']['application/json']['schema'] ?? null;
                break;
            }
        }

        $response = $responseSchema === null
            ? new Response($successStatus)
            : TestClientFactory::json($successStatus, self::sample($responseSchema));

        $harness = TestClientFactory::harness([$response]);

        Wire::startRecording();
        try {
            $call($harness->client);
        } finally {
            $read = Wire::stopRecording();
        }

        $request = $harness->lastApiRequest();
        $found = self::operation($request->getMethod(), $request->getUri()->getPath());
        self::assertNotNull($found, 'O SDK chamou uma rota que não existe no openapi.yaml: ' . $request->getUri()->getPath());
        self::assertSame($templatePath, $found['template'], 'Rota inesperada para ' . $expectedOperation);
        self::assertSame(strtoupper($method), $request->getMethod());

        $this->assertQueryAndHeaders($request, $found);

        $requestBody = $found['op']['requestBody'] ?? null;
        if ($requestBody !== null) {
            $schema = $requestBody['content']['application/json']['schema'];
            $this->assertWireMatchesSchema(TestClientFactory::bodyOf($request), $schema, $expectedOperation, true);
        } else {
            self::assertSame('', (string) $request->getBody(), 'A operação não tem corpo, mas o SDK enviou um.');
        }

        $allowed = self::allPropertyNames($spec['components']['schemas']['authenticationResponse']);
        $mustRead = [];
        if ($responseSchema !== null) {
            $responseNames = self::allPropertyNames($responseSchema);
            $allowed = array_merge($allowed, $responseNames);
            $mustRead = $responseNames;
        }

        foreach ($read as $key) {
            self::assertContains(
                $key,
                $allowed,
                sprintf("%s: o SDK lê '%s', que não existe no schema da resposta.", $expectedOperation, $key)
            );
        }

        foreach ($mustRead as $key) {
            self::assertContains(
                $key,
                $read,
                sprintf("%s: a propriedade '%s' da resposta não é lida pelo SDK.", $expectedOperation, $key)
            );
        }
    }

    /**
     * @param array<string, mixed> $found
     */
    private function assertQueryAndHeaders(RequestInterface $request, array $found): void
    {
        $declared = self::parameters($found);
        $where = $found['template'];

        parse_str($request->getUri()->getQuery(), $query);
        foreach (array_keys($query) as $name) {
            self::assertArrayHasKey('query:' . $name, $declared, "{$where}: o SDK envia o parâmetro de query '{$name}' não documentado.");
        }

        foreach ($declared as $key => $parameter) {
            if (($parameter['in'] ?? '') === 'query' && ($parameter['required'] ?? false) === true) {
                self::assertArrayHasKey($parameter['name'], $query, "{$where}: o parâmetro obrigatório '{$parameter['name']}' não é enviado.");
            }
        }

        $hasKeyHeader = $request->hasHeader('Idempotency-Key');
        $declaresKey = isset($declared['header:Idempotency-Key']);
        if ($hasKeyHeader) {
            self::assertTrue($declaresKey, "{$where}: o SDK envia Idempotency-Key, mas a operação não o documenta.");
        }

        if ($declaresKey && ($declared['header:Idempotency-Key']['required'] ?? false) === true) {
            self::assertTrue($hasKeyHeader, "{$where}: o header Idempotency-Key é obrigatório e não foi enviado.");
        }

        self::assertStringStartsWith('Bearer ', $request->getHeaderLine('Authorization'));
    }

    public function testTokenRequestMatchesTheOpenApiOperation(): void
    {
        $spec = self::spec();
        $operation = $spec['paths']['/v1/authentication/accesstoken']['post'];

        $harness = TestClientFactory::harness([new Response(204)]);
        $harness->client->webhooks->delete(self::UUID);

        $tokenRequest = $harness->tokenRequests()[0];
        $found = self::operation('POST', $tokenRequest->getUri()->getPath());
        self::assertNotNull($found);
        self::assertSame('/v1/authentication/accesstoken', $found['template']);

        $this->assertWireMatchesSchema(
            TestClientFactory::bodyOf($tokenRequest),
            $operation['requestBody']['content']['application/json']['schema'],
            self::TOKEN_OPERATION,
            true
        );
        self::assertFalse($tokenRequest->hasHeader('Authorization'));
        self::assertSame([], $operation['security'], 'A rota de token deveria ser pública no contrato.');
    }

    public function testEveryDocumentedOperationHasAPublicSdkMethod(): void
    {
        $covered = [self::TOKEN_OPERATION];
        foreach (self::scenarioProvider() as $scenario) {
            $covered[] = $scenario[0];
        }

        $documented = [];
        foreach (self::spec()['paths'] as $template => $item) {
            foreach (array_keys($item) as $method) {
                if (in_array($method, ['get', 'post', 'put', 'delete', 'patch'], true)) {
                    $documented[] = strtoupper($method) . ' ' . $template;
                }
            }
        }

        sort($covered);
        sort($documented);

        self::assertSame(
            $documented,
            $covered,
            'As operações do openapi.yaml e as cobertas pelo SDK divergiram: o contrato mudou ou falta um método.'
        );
    }

    public function testSdkEnumCodesMatchTheContractEnums(): void
    {
        $schemas = self::spec()['components']['schemas'];

        self::assertSame(
            ['EM_ANALISE', 'APROVADA', 'RECUSADA', 'CANCELADA', 'ANALISE_MANUAL', 'FINALIZADA', 'EM_NEGOCIACAO', 'EXPIRADA', 'PENDENTE'],
            $schemas['ProposalV2Response']['properties']['status']['enum']
        );

        foreach ($schemas['ProposalV2Response']['properties']['status']['enum'] as $index => $name) {
            self::assertSame($index + 1, \Twila\Credicarro\Proposals\ProposalStatus::fromWireName($name), $name);
        }

        foreach ($schemas['ProposalV2ActiveStep']['properties']['etapa']['enum'] as $index => $name) {
            self::assertSame($index, \Twila\Credicarro\Proposals\ProposalStep::fromWireName($name), $name);
        }

        foreach ($schemas['ProposalV2ActiveStep']['properties']['situacao']['enum'] as $index => $name) {
            self::assertSame($index + 1, \Twila\Credicarro\Proposals\StepSituation::fromWireName($name), $name);
        }

        foreach ($schemas['ProposalV2ActiveStep']['properties']['responsavel_acao']['enum'] as $index => $name) {
            self::assertSame($index, \Twila\Credicarro\Proposals\ActionOwner::fromWireName($name), $name);
        }

        foreach ($schemas['ProposalV2Participant']['properties']['biometria']['enum'] as $index => $name) {
            self::assertSame($index, \Twila\Credicarro\Proposals\BiometryStatus::fromWireName($name), $name);
        }

        foreach ($schemas['ProposalV2Document']['properties']['status']['enum'] as $index => $name) {
            self::assertSame($index, \Twila\Credicarro\Proposals\DocumentStatus::fromWireName($name), $name);
        }

        foreach ($schemas['ProposalResponse']['properties']['status']['enum'] as $index => $name) {
            self::assertSame($index + 1, \Twila\Credicarro\Proposals\ProposalStatus::fromWireName($name), $name);
        }

        foreach ($schemas['ProposalResponse']['properties']['tipo']['enum'] as $name) {
            self::assertNotSame(-1, \Twila\Credicarro\Proposals\ProposalType::fromWireName($name), $name);
        }

        foreach ($schemas['SimulationResponse']['properties']['tipo']['enum'] as $name) {
            self::assertNotSame(-1, \Twila\Credicarro\Simulations\SimulationType::fromWireName($name), $name);
        }

        $deposit = self::spec()['paths']['/v2/proposal/{propostaId}/deposit-account']['post']['requestBody']['content']
            ['application/json']['schema']['properties']['tipoConta']['enum'];
        self::assertSame($deposit, \Twila\Credicarro\Proposals\DepositAccountType::values());

        $document = self::spec()['paths']['/v2/proposal/{propostaId}/document']['post']['requestBody']['content']
            ['application/json']['schema']['properties']['participante']['enum'];
        self::assertSame($document, ProposalParticipant::values());
    }
}
