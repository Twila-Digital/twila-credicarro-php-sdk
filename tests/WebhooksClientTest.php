<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Twila\Credicarro\Errors\CredicarroApiException;
use Twila\Credicarro\Errors\CredicarroBusinessRuleException;
use Twila\Credicarro\ListRequest;
use Twila\Credicarro\Webhooks\CreateWebhookRequest;
use Twila\Credicarro\Webhooks\UpdateWebhookRequest;
use Twila\Credicarro\Webhooks\WebhookAuthenticationType;
use Twila\Credicarro\Webhooks\WebhookStatus;
use Twila\Credicarro\Webhooks\WebhookType;

final class WebhooksClientTest extends TestCase
{
    private const ID = '3fa85f64-5717-4562-b3fc-2c963f66afa6';

    public function testCreateReturnsIdAndSigningSecret(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(201, [
            'id_webhook' => self::ID,
            'segredo_assinatura' => 'segredo-de-exemplo',
        ])]);

        $result = $h->client->webhooks->create(new CreateWebhookRequest(
            WebhookType::PROPOSAL,
            'https://parceiro.com.br/webhooks/credicarro',
            WebhookAuthenticationType::BASIC,
            'dXN1YXJpbzpzZW5oYQ=='
        ));

        self::assertSame(self::ID, $result->webhookId);
        self::assertSame('segredo-de-exemplo', $result->signingSecret);
        $request = $h->lastApiRequest();
        self::assertSame('/integration/v1/webhook', $request->getUri()->getPath());
        self::assertSame([
            'tipo' => 3,
            'url' => 'https://parceiro.com.br/webhooks/credicarro',
            'tipo_autenticacao' => 2,
            'credencial' => 'dXN1YXJpbzpzZW5oYQ==',
        ], TestClientFactory::bodyOf($request));
    }

    public function testCreateOmitsCredentialWhenNotProvided(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(201, ['id_webhook' => self::ID, 'segredo_assinatura' => 's']),
        ]);

        $h->client->webhooks->create(
            new CreateWebhookRequest(
                WebhookType::PROPOSAL,
                'https://parceiro.com.br/hook',
                WebhookAuthenticationType::NONE
            )
        );

        self::assertArrayNotHasKey('credencial', TestClientFactory::bodyOf($h->lastApiRequest()));
    }

    public function testSecretsDoNotLeakThroughDebugOutput(): void
    {
        $request = new CreateWebhookRequest(
            WebhookType::PROPOSAL,
            'https://parceiro.com.br/hook',
            WebhookAuthenticationType::JWT,
            'token-jwt-secreto'
        );
        $h = TestClientFactory::harness([TestClientFactory::json(201, [
            'id_webhook' => self::ID,
            'segredo_assinatura' => 'segredo-super-sigiloso',
        ])]);
        $result = $h->client->webhooks->create($request);

        self::assertStringNotContainsString('token-jwt-secreto', print_r($request, true));
        self::assertStringNotContainsString('segredo-super-sigiloso', print_r($result, true));
    }

    public function testCreateWithInvalidEndpointRaisesBusinessRule(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(422, ['tipo' => 'Organization.Webhook.InvalidEndpoint']),
        ]);

        try {
            $h->client->webhooks->create(
                new CreateWebhookRequest(
                    WebhookType::PROPOSAL,
                    'https://10.0.0.1/hook',
                    WebhookAuthenticationType::NONE
                )
            );
            self::fail('Esperava CredicarroBusinessRuleException.');
        } catch (CredicarroBusinessRuleException $e) {
            self::assertSame('Organization.Webhook.InvalidEndpoint', $e->getErrorCode());
        }
    }

    public function testListMapsTextualEnumsAndPaging(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, [
            'itens' => [
                [
                    'id_webhook' => self::ID,
                    'tipo' => 'Proposal',
                    'url' => 'https://parceiro.com.br/webhooks/credicarro',
                    'tipo_autenticacao' => 'Basic',
                    'status' => 'Active',
                ],
                [
                    'id_webhook' => '3fa85f64-5717-4562-b3fc-2c963f66afa7',
                    'tipo' => 'Futuro',
                    'url' => 'https://parceiro.com.br/b',
                    'tipo_autenticacao' => 'Jwt',
                    'status' => 'Suspended',
                ],
            ],
            'pagina' => ['tem_proximo' => true, 'tem_anterior' => false, 'numero' => 1, 'tamanho' => 2, 'total' => 5],
        ])]);

        $page = $h->client->webhooks->list(new ListRequest(1, 2));

        self::assertSame('/integration/v1/webhook', $h->lastApiRequest()->getUri()->getPath());
        self::assertSame('pagina_inicial=1&limite=2', $h->lastApiRequest()->getUri()->getQuery());
        self::assertSame(5, $page->totalCount);
        self::assertTrue($page->hasNext);
        self::assertCount(2, $page->items);

        $first = $page->items[0];
        self::assertSame(self::ID, $first->id);
        self::assertSame(WebhookType::PROPOSAL, $first->type);
        self::assertSame('Proposal', $first->typeRaw);
        self::assertSame(WebhookAuthenticationType::BASIC, $first->authenticationType);
        self::assertSame('Basic', $first->authenticationTypeRaw);
        self::assertSame(WebhookStatus::ACTIVE, $first->status);
        self::assertSame('https://parceiro.com.br/webhooks/credicarro', $first->url);

        $second = $page->items[1];
        self::assertSame(WebhookType::UNKNOWN, $second->type);
        self::assertSame('Futuro', $second->typeRaw);
        self::assertSame(WebhookAuthenticationType::JWT, $second->authenticationType);
        self::assertSame(WebhookStatus::UNKNOWN, $second->status);
        self::assertSame('Suspended', $second->statusRaw);
    }

    public function testUpdateSendsTypeUrlAndOptionalStatus(): void
    {
        $h = TestClientFactory::harness([new Response(204), new Response(204)]);

        $h->client->webhooks->update(
            self::ID,
            new UpdateWebhookRequest(WebhookType::PROPOSAL, 'https://parceiro.com.br/novo', WebhookStatus::INACTIVE)
        );
        $withStatus = $h->lastApiRequest();
        $h->client->webhooks->update(
            self::ID,
            new UpdateWebhookRequest(WebhookType::PROPOSAL, 'https://parceiro.com.br/novo')
        );

        self::assertSame('PUT', $withStatus->getMethod());
        self::assertSame('/integration/v1/webhook/' . self::ID, $withStatus->getUri()->getPath());
        self::assertSame(
            ['tipo' => 3, 'url' => 'https://parceiro.com.br/novo', 'status' => 2],
            TestClientFactory::bodyOf($withStatus)
        );
        self::assertArrayNotHasKey('status', TestClientFactory::bodyOf($h->lastApiRequest()));
    }

    public function testDeleteSendsDeleteToTheWebhookRoute(): void
    {
        $h = TestClientFactory::harness([new Response(204)]);

        $h->client->webhooks->delete(self::ID);

        self::assertSame('DELETE', $h->lastApiRequest()->getMethod());
        self::assertSame('/integration/v1/webhook/' . self::ID, $h->lastApiRequest()->getUri()->getPath());
    }

    public function testDeleteNotFoundRaisesApiException(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(404, ['tipo' => 'Webhook.Registration.NotFound'])]);

        try {
            $h->client->webhooks->delete(self::ID);
            self::fail('Esperava CredicarroApiException.');
        } catch (CredicarroApiException $e) {
            self::assertSame(404, $e->getStatusCode());
            self::assertSame('Webhook.Registration.NotFound', $e->getErrorCode());
        }
    }
}
