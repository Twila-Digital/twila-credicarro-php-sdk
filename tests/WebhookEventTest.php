<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use PHPUnit\Framework\TestCase;
use Twila\Credicarro\Errors\CredicarroWebhookSignatureException;
use Twila\Credicarro\Proposals\ActionOwner;
use Twila\Credicarro\Proposals\BiometryStatus;
use Twila\Credicarro\Proposals\DocumentStatus;
use Twila\Credicarro\Proposals\DocumentType;
use Twila\Credicarro\Proposals\ProposalStatus;
use Twila\Credicarro\Proposals\ProposalStep;
use Twila\Credicarro\Proposals\StepSituation;
use Twila\Credicarro\Webhooks\CredicarroWebhookEvent;
use Twila\Credicarro\Webhooks\ProposalEventReason;

final class WebhookEventTest extends TestCase
{
    private const SECRET = 'segredo-de-exemplo';
    private const DOC_TIMESTAMP = 1715792400;
    private const DOC_BODY = '{"id_proposta":"bea8d060-f93d-48eb-b6c4-45f64483cdd9",'
        . '"enum_status":2,"status":"Approved"}';
    private const DOC_SIGNATURE = 'db66c554eb116ef2577deb63f72619793502a31520f622b2d59864b074eb8df2';

    private const V2_BODY = <<<'JSON'
{
  "id_proposta": "bea8d060-f93d-48eb-b6c4-45f64483cdd9",
  "enum_status": 2,
  "status": "APROVADA",
  "motivo_evento": "DOCUMENTOS_ALTERADOS",
  "motivo_recusa": null,
  "data_evento": "2026-10-12T17:20:31.512+00:00",
  "comprador": {
    "cpf": "12345678909",
    "enum_biometria": 3,
    "biometria": "APROVADA",
    "status_ativo": {
      "enum_etapa": 7,
      "etapa": "CORRECAO_DOCUMENTOS",
      "enum_situacao": 4,
      "situacao": "PENDENTE_CORRECAO",
      "enum_responsavel_acao": 2,
      "responsavel_acao": "INTEGRADOR"
    },
    "etapas_concluidas": [
      { "enum_etapa": 1, "etapa": "PROPOSTA_CRIADA", "data": "2026-10-12T14:05:08-03:00" },
      { "enum_etapa": 4, "etapa": "ANALISE_BIOMETRIA", "data": "2026-10-12T14:20:00-03:00" }
    ],
    "documentos": [
      { "enum_tipo": 1, "tipo": "CNH", "enum_status": 2, "status": "INVALIDO", "motivo": "Documento ilegível" }
    ]
  },
  "loja": {
    "cnpj": "11222333000181",
    "status_ativo": {
      "enum_etapa": 6,
      "etapa": "ENVIO_DOCUMENTOS",
      "enum_situacao": 1,
      "situacao": "AGUARDANDO_ENVIO",
      "enum_responsavel_acao": 2,
      "responsavel_acao": "INTEGRADOR"
    },
    "etapas_concluidas": [],
    "documentos": []
  },
  "vendedor": null
}
JSON;

    private function header(string $body, ?int $timestamp = null, string $secret = self::SECRET): string
    {
        $t = $timestamp ?? time();

        return sprintf('t=%d,v1=%s', $t, CredicarroWebhookEvent::computeSignature($secret, $t, $body));
    }

    private function verifyAt(string $header, int $tolerance, int $now): bool
    {
        return CredicarroWebhookEvent::verifySignature(self::DOC_BODY, $header, self::SECRET, $tolerance, $now);
    }

    public function testComputeSignatureMatchesTheDocumentationVector(): void
    {
        self::assertSame(
            self::DOC_SIGNATURE,
            CredicarroWebhookEvent::computeSignature(self::SECRET, self::DOC_TIMESTAMP, self::DOC_BODY)
        );
        self::assertSame(
            self::DOC_SIGNATURE,
            CredicarroWebhookEvent::computeSignature(self::SECRET, '1715792400', self::DOC_BODY)
        );
    }

    public function testDocumentationVectorIsAccepted(): void
    {
        $header = 't=1715792400,v1=' . self::DOC_SIGNATURE;

        self::assertTrue(CredicarroWebhookEvent::verifySignature(
            self::DOC_BODY,
            $header,
            self::SECRET,
            300,
            self::DOC_TIMESTAMP
        ));
    }

    public function testUppercaseHexInHeaderIsStillCompared(): void
    {
        $header = 't=1715792400,v1=' . strtoupper(self::DOC_SIGNATURE);

        self::assertTrue(CredicarroWebhookEvent::verifySignature(
            self::DOC_BODY,
            $header,
            self::SECRET,
            300,
            self::DOC_TIMESTAMP
        ));
    }

    public function testTamperedBodyIsRejected(): void
    {
        $header = 't=1715792400,v1=' . self::DOC_SIGNATURE;

        self::assertFalse(CredicarroWebhookEvent::verifySignature(
            str_replace('"enum_status":2', '"enum_status":3', self::DOC_BODY),
            $header,
            self::SECRET,
            300,
            self::DOC_TIMESTAMP
        ));
        // Reformatar o JSON (espaços) também invalida o hash: a validação é sobre o corpo bruto.
        self::assertFalse(CredicarroWebhookEvent::verifySignature(
            str_replace(',', ', ', self::DOC_BODY),
            $header,
            self::SECRET,
            300,
            self::DOC_TIMESTAMP
        ));
    }

    public function testWrongSecretIsRejected(): void
    {
        $header = 't=1715792400,v1=' . self::DOC_SIGNATURE;

        self::assertFalse(CredicarroWebhookEvent::verifySignature(
            self::DOC_BODY,
            $header,
            'outro-segredo',
            300,
            self::DOC_TIMESTAMP
        ));
    }

    public function testEmptySecretIsAlwaysInvalid(): void
    {
        $body = self::DOC_BODY;
        // Mesmo uma assinatura calculada com segredo vazio não pode ser aceita.
        $header = sprintf(
            't=%d,v1=%s',
            self::DOC_TIMESTAMP,
            hash_hmac('sha256', self::DOC_TIMESTAMP . '.' . $body, '')
        );

        self::assertFalse(CredicarroWebhookEvent::verifySignature($body, $header, '', 300, self::DOC_TIMESTAMP));

        $this->expectException(CredicarroWebhookSignatureException::class);
        CredicarroWebhookEvent::parse($body, $this->header($body, null, ''), '');
    }

    /**
     * @dataProvider malformedHeaderProvider
     */
    public function testMalformedHeadersAreRejected(string $header): void
    {
        self::assertFalse(CredicarroWebhookEvent::verifySignature(
            self::DOC_BODY,
            $header,
            self::SECRET,
            300,
            self::DOC_TIMESTAMP
        ));

        $this->expectException(CredicarroWebhookSignatureException::class);
        CredicarroWebhookEvent::parse(self::DOC_BODY, $header, self::SECRET);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function malformedHeaderProvider(): array
    {
        $sig = self::DOC_SIGNATURE;

        return [
            'empty' => [''],
            'garbage' => ['garbage-header'],
            'missing t' => ['v1=' . $sig],
            'missing v1' => ['t=1715792400'],
            't not numeric' => ['t=abc,v1=' . $sig],
            't negative' => ['t=-1715792400,v1=' . $sig],
            't too long (11 digits)' => ['t=17157924000,v1=' . $sig],
            't empty' => ['t=,v1=' . $sig],
            'v1 not hex' => ['t=1715792400,v1=zzzz'],
            'v1 empty' => ['t=1715792400,v1='],
            'duplicate t' => ['t=1715792400,t=1715792401,v1=' . $sig],
            'duplicate v1' => ['t=1715792400,v1=' . $sig . ',v1=' . $sig],
            'no equals' => ['t1715792400,v1' . $sig],
        ];
    }

    public function testTimestampToleranceAppliesInBothDirections(): void
    {
        $header = 't=1715792400,v1=' . self::DOC_SIGNATURE;

        // Dentro da tolerância (5 min = 300 s), antes e depois.
        self::assertTrue($this->verifyAt($header, 300, 1715792400 + 300));
        self::assertTrue($this->verifyAt($header, 300, 1715792400 - 300));
        // Fora: replay antigo e timestamp no futuro.
        self::assertFalse($this->verifyAt($header, 300, 1715792400 + 301));
        self::assertFalse($this->verifyAt($header, 300, 1715792400 - 301));
        // Tolerância ajustável.
        self::assertTrue($this->verifyAt($header, 3600, 1715792400 + 3000));
    }

    public function testParseRejectsOldTimestampEvenWithValidSignature(): void
    {
        $header = $this->header(self::DOC_BODY, time() - 6 * 60);

        $this->expectException(CredicarroWebhookSignatureException::class);
        $this->expectExceptionMessage('tolerância');
        CredicarroWebhookEvent::parse(self::DOC_BODY, $header, self::SECRET);
    }

    public function testParseRejectsFutureTimestamp(): void
    {
        $header = $this->header(self::DOC_BODY, time() + 6 * 60);

        $this->expectException(CredicarroWebhookSignatureException::class);
        CredicarroWebhookEvent::parse(self::DOC_BODY, $header, self::SECRET);
    }

    public function testParseRejectsInvalidSignature(): void
    {
        $header = sprintf('t=%d,v1=%s', time(), str_repeat('0', 64));

        $this->expectException(CredicarroWebhookSignatureException::class);
        $this->expectExceptionMessage('assinatura');
        CredicarroWebhookEvent::parse(self::DOC_BODY, $header, self::SECRET);
    }

    public function testParseWithValidSignatureReturnsV1Event(): void
    {
        $event = CredicarroWebhookEvent::parse(self::DOC_BODY, $this->header(self::DOC_BODY), self::SECRET);

        self::assertSame('bea8d060-f93d-48eb-b6c4-45f64483cdd9', $event->proposalId);
        self::assertSame(ProposalStatus::APPROVED, $event->status);
        self::assertSame(2, $event->statusRaw);
        self::assertSame('Approved', $event->statusName);
        self::assertFalse($event->isDetailed);
        self::assertNull($event->eventReason);
        self::assertNull($event->eventReasonRaw);
        self::assertNull($event->eventDate);
        self::assertNull($event->buyer);
        self::assertNull($event->seller);
        self::assertNull($event->store);
    }

    public function testV1PayloadWithOnlyTheEnglishNameStillResolvesTheStatus(): void
    {
        $event = CredicarroWebhookEvent::parseUnverified(
            '{"id_proposta":"bea8d060-f93d-48eb-b6c4-45f64483cdd9","status":"ManualAnalysis"}'
        );

        self::assertSame(ProposalStatus::MANUAL_ANALYSIS, $event->status);
        self::assertNull($event->statusRaw);
        self::assertSame('ManualAnalysis', $event->statusName);
    }

    public function testV1PayloadWithUnknownStatusKeepsTheRawCode(): void
    {
        $event = CredicarroWebhookEvent::parseUnverified(
            '{"id_proposta":"bea8d060-f93d-48eb-b6c4-45f64483cdd9","enum_status":77,"status":"Novo"}'
        );

        self::assertSame(ProposalStatus::UNKNOWN, $event->status);
        self::assertSame(77, $event->statusRaw);
        self::assertSame('Novo', $event->statusName);
    }

    public function testV2PayloadIsFullyMapped(): void
    {
        $event = CredicarroWebhookEvent::parse(self::V2_BODY, $this->header(self::V2_BODY), self::SECRET);

        self::assertTrue($event->isDetailed);
        self::assertSame(ProposalStatus::APPROVED, $event->status);
        self::assertSame(2, $event->statusRaw);
        self::assertSame('APROVADA', $event->statusName);
        self::assertSame(ProposalEventReason::DOCUMENTS_CHANGED, $event->eventReason);
        self::assertSame('DOCUMENTOS_ALTERADOS', $event->eventReasonRaw);
        self::assertNull($event->refusalReason);
        self::assertSame('2026-10-12T17:20:31.512+00:00', $event->eventDate);
        self::assertNull($event->seller);

        self::assertNotNull($event->buyer);
        self::assertSame('12345678909', $event->buyer->document);
        self::assertSame(BiometryStatus::APPROVED, $event->buyer->biometry);
        self::assertNotNull($event->buyer->activeStep);
        self::assertSame(ProposalStep::DOCUMENT_CORRECTION, $event->buyer->activeStep->step);
        self::assertSame(StepSituation::PENDING_CORRECTION, $event->buyer->activeStep->situation);
        self::assertSame(ActionOwner::INTEGRATOR, $event->buyer->activeStep->actionOwner);
        self::assertCount(2, $event->buyer->completedSteps);
        self::assertSame(DocumentType::CNH, $event->buyer->documents[0]->type);
        self::assertSame(DocumentStatus::INVALID, $event->buyer->documents[0]->status);
        self::assertSame('Documento ilegível', $event->buyer->documents[0]->reason);

        self::assertNotNull($event->store);
        self::assertSame('11222333000181', $event->store->document);
        self::assertNotNull($event->store->activeStep);
        self::assertSame(ProposalStep::DOCUMENT_SUBMISSION, $event->store->activeStep->step);
        self::assertSame([], $event->store->documents);
    }

    public function testV2PayloadWithUnknownReasonKeepsRaw(): void
    {
        $event = CredicarroWebhookEvent::parseUnverified(
            '{"id_proposta":"bea8d060-f93d-48eb-b6c4-45f64483cdd9","enum_status":1,"status":"EM_ANALISE",'
            . '"motivo_evento":"NOVO_MOTIVO","comprador":null}'
        );

        self::assertTrue($event->isDetailed);
        self::assertSame(ProposalEventReason::UNKNOWN, $event->eventReason);
        self::assertSame('NOVO_MOTIVO', $event->eventReasonRaw);
        self::assertSame(ProposalStatus::ANALYSING, $event->status);
    }

    public function testRefusalReasonIsMapped(): void
    {
        $event = CredicarroWebhookEvent::parseUnverified(
            '{"id_proposta":"bea8d060-f93d-48eb-b6c4-45f64483cdd9","enum_status":3,"status":"RECUSADA",'
            . '"motivo_recusa":"Restrição cadastral","vendedor":null}'
        );

        self::assertSame(ProposalStatus::REFUSED, $event->status);
        self::assertSame('Restrição cadastral', $event->refusalReason);
    }

    /**
     * @dataProvider invalidPayloadProvider
     */
    public function testInvalidPayloadsRaiseSignatureException(string $body): void
    {
        $this->expectException(CredicarroWebhookSignatureException::class);
        CredicarroWebhookEvent::parseUnverified($body);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidPayloadProvider(): array
    {
        return [
            'not json' => ['not json'],
            'empty' => [''],
            'scalar json' => ['"texto"'],
            'missing proposal id' => ['{"enum_status":2}'],
            'empty proposal id' => ['{"id_proposta":"","enum_status":2}'],
        ];
    }

    public function testVerifyAuthorizationComparesInConstantTime(): void
    {
        self::assertTrue(
            CredicarroWebhookEvent::verifyAuthorization('Basic dXN1YXJpbzpzZW5oYQ==', 'Basic dXN1YXJpbzpzZW5oYQ==')
        );
        self::assertTrue(CredicarroWebhookEvent::verifyAuthorization('Bearer abc', 'Bearer abc'));
        self::assertFalse(CredicarroWebhookEvent::verifyAuthorization('Bearer abd', 'Bearer abc'));
        self::assertFalse(CredicarroWebhookEvent::verifyAuthorization('', 'Bearer abc'));
        self::assertFalse(CredicarroWebhookEvent::verifyAuthorization('Bearer abc', ''));
        self::assertFalse(CredicarroWebhookEvent::verifyAuthorization('', ''));
    }
}
