<p align="center">
  <img src="https://raw.githubusercontent.com/Twila-Digital/twila-credicarro-php-sdk/production/assets/logo-light.svg" alt="Credicarro" width="180" style="max-width: 100%;">
</p>

<p align="center">
  <a href="LICENSE"><img alt="License" src="https://img.shields.io/github/license/Twila-Digital/twila-credicarro-php-sdk"></a>
  <a href="https://github.com/Twila-Digital/twila-credicarro-php-sdk/actions/workflows/ci.yml"><img alt="CI" src="https://github.com/Twila-Digital/twila-credicarro-php-sdk/actions/workflows/ci.yml/badge.svg"></a>
  <a href="https://github.com/Twila-Digital/twila-credicarro-php-sdk/actions/workflows/quality.yml"><img alt="Quality" src="https://github.com/Twila-Digital/twila-credicarro-php-sdk/actions/workflows/quality.yml/badge.svg"></a>
  <a href="https://github.com/Twila-Digital/twila-credicarro-php-sdk/security/code-scanning"><img alt="Security" src="https://github.com/Twila-Digital/twila-credicarro-php-sdk/actions/workflows/security.yml/badge.svg"></a>
  <a href="https://codecov.io/gh/Twila-Digital/twila-credicarro-php-sdk"><img alt="Coverage" src="https://codecov.io/gh/Twila-Digital/twila-credicarro-php-sdk/branch/production/graph/badge.svg"></a>
  <img alt="PHP" src="https://img.shields.io/badge/PHP-7.4%2B-539E43">
</p>

# twila/credicarro

SDK oficial em PHP para a API de integração do Credicarro — financiamento e refinanciamento de veículos: cadastro de clientes, simulações, propostas, envio de documentos e webhooks.

> Uso restrito a server-side. O `clientSecret` nunca deve ser embarcado em um app mobile, SPA ou qualquer código que rode no navegador/dispositivo do usuário final.

## Compatibilidade

| Runtime | Versões aceitas |
| --- | --- |
| PHP | 7.4 ou superior (CI cobre 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 e 8.5) |

Cliente síncrono, baseado em [Guzzle](https://docs.guzzlephp.org/). Não usa `enum` nativo (PHP 8.1+) nem `readonly`/constructor promotion — o SDK precisa compilar e rodar de verdade em PHP 7.4, então os "enums" (`ProposalStatus`, `ProposalStep`, `DocumentType`, etc.) são classes com constantes. Valores que o SDK não conhece viram `UNKNOWN` (`-1`) e o valor original continua disponível nos campos `*Raw`/`*Name`.

## Instalação

```bash
composer require twila/credicarro
```

## Configuração

```php
use Twila\Credicarro\CredicarroClient;
use Twila\Credicarro\Config\ClientOptions;
use Twila\Credicarro\Config\Environment;

$client = new CredicarroClient(new ClientOptions(
    '<seu-client-id>',
    '<seu-client-secret>',
    Environment::STAGING
));
```

Sem o terceiro argumento, o ambiente é `Environment::PRODUCTION`. As credenciais (`clientId` e `clientSecret`) são geradas pelo time Credicarro, uma por ambiente.

`CredicarroClient` deve ser reaproveitado (não crie uma instância por requisição) — ele mantém o cache do token de acesso e o estado do circuit breaker durante o ciclo de vida do processo/worker. O token é gerado sozinho, guardado até o `expira_em` informado pela API (sem ele, a validade é `agora + max(expira_em_segundos / 2, expira_em_segundos - 60)`) e renovado quando necessário; se uma chamada devolver `401`, o SDK renova o token e repete a chamada **uma** vez.

O `clientSecret` fica dentro de um objeto interno (`ClientOptions` não tem getter para ele): não aparece em `var_dump`, `print_r`, `var_export` nem em stack traces. `ClientOptions`, o cliente e os objetos internos de credencial/token **recusam a serialização** (`serialize` lança exceção) e o `json_encode` deles sai mascarado; nunca devolvem o segredo.

#### Máscara só na depuração, valores reais na serialização

Requisições e respostas com CPF, e-mail, celular, renda, conta bancária, credencial, segredo de webhook ou conteúdo de documento aparecem **mascaradas em `var_dump` e `print_r`** (CPF `***.456.789-**`, demais `***`). `serialize`, `json_encode` e a leitura das propriedades públicas carregam os **valores reais**, porque os modelos são feitos para ir para fila, sessão ou cache (Laravel, Messenger, Symfony) e voltar sem perda: `unserialize(serialize($dto)) == $dto`. Limitação: `var_export` e `(array)` ignoram `__debugInfo` e mostram as propriedades públicas como são — não os use para logar esses objetos. Em log, registre só identificadores e códigos de erro.

### Cache do token entre requisições (PHP-FPM)

Sem configuração, o token vive só na memória da instância — e em PHP-FPM cada requisição é um processo novo, ou seja, uma chamada de autenticação por requisição. Para compartilhar o token, passe um cache [PSR-16](https://www.php-fig.org/psr/psr-16/) (APCu, Redis, Memcached...) como último argumento de `ClientOptions`:

```php
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Psr16Cache;

$cache = new Psr16Cache(new ApcuAdapter('credicarro')); // ou RedisAdapter, etc.

$client = new CredicarroClient(new ClientOptions(
    '<seu-client-id>',
    '<seu-client-secret>',
    Environment::PRODUCTION,
    null,   // baseUrl
    null,   // resilience
    $cache  // tokenCache
));
```

A chave é derivada (SHA-256) do ambiente/URL base e do `clientId`; o TTL vai até a validade do token; o `clientSecret` nunca é gravado. O cache guarda o token (um segredo de curta duração): use um backend que só a sua aplicação consiga ler. Falhas do cache são ignoradas (o SDK volta a pedir o token à API), e um token rejeitado com `401` é removido do cache.

> **Segurança do cache.** O token vale como credencial até expirar: use um cache **exclusivo da aplicação** e protegido (Redis com senha/TLS, APCu do pool do próprio app), nunca um cache compartilhado com outros sistemas. Ao **rotacionar o `clientSecret`**, apague a chave do token (ou deixe-o expirar), senão o token antigo continua em uso até o fim da validade. Em picos de partida a frio, vários processos podem gerar token ao mesmo tempo; isso é inofensivo (o último a gravar vence).

A `baseUrl` pode ser trocada (por exemplo, para um proxy), mas precisa ser `https` — `http` só é aceito para `localhost` e `127.0.0.1`, em testes.

## Fluxo completo

```php
use Twila\Credicarro\Customers\CustomerAddress;
use Twila\Credicarro\Customers\OccupationType;
use Twila\Credicarro\Customers\ProfessionalData;
use Twila\Credicarro\Customers\UpsertCustomerRequest;
use Twila\Credicarro\Proposals\CreateProposalRequest;
use Twila\Credicarro\Simulations\CreateFinancingSimulationRequest;

// 1. Cliente (cadastro completo em uma chamada)
$client->customers->upsert(new UpsertCustomerRequest(
    '12345678909',
    'Maria',
    'Souza',
    'maria.souza@exemplo.com.br',
    '+55 (11) 99876-5432',
    '1990-05-20T00:00:00-03:00',
    new CustomerAddress('Av. Paulista', 'Bela Vista', 'São Paulo', 'SP', '01311000', '1578'),
    new ProfessionalData(OccupationType::EMPLOYEE, 6500.0)
));

// 2. Simulação: uma opção por prazo
$opcoes = $client->simulations->createFinancing(new CreateFinancingSimulationRequest(
    '12345678909',   // cpf
    'ABC1D23',       // placa
    45000.0,         // valor do veículo
    5000.0,          // entrada
    'SP',            // UF do registro
    false,           // veículo zero km?
    '11222333000181', // CNPJ da loja de integração
    null             // retornoComissao: opcional, dentro da faixa configurada na loja (ver getCommissionReturns)
));

// 3. Proposta a partir da opção escolhida
$propostaId = $client->proposals->create(new CreateProposalRequest('12345678909', $opcoes[0]->simulationId));

// 4. O que o seu sistema precisa enviar agora?
$proposta = $client->proposals->get($propostaId);
foreach ($proposta->getPendingActions() as $acao) {
    echo "{$acao->participant}: {$acao->kind}" . PHP_EOL;
}
```

Os valores do cliente (nome, CPF, endereço) precisam bater com os documentos enviados depois. O CPF identifica o cliente em todas as chamadas — não é preciso guardar o `id_cliente`.

## Clientes por recurso

| Cliente | Métodos |
| --- | --- |
| `$client->customers` | `upsert`, `create`, `upsertAddress`, `upsertProfessionalData` |
| `$client->simulations` | `getFinancingRange`, `getRefinancingRange`, `getCommissionReturns`, `createFinancing`, `createRefinancing`, `get`, `list` |
| `$client->proposals` | `create`, `get`, `list`, `sendVehicleDetails`, `sendDepositAccount`, `uploadDocument` |
| `$client->webhooks` | `create`, `list`, `update`, `delete` |
| `$client->v1->simulations` | `getFinancingRange`, `getRefinancingRange`, `createFinancing`, `createRefinancing` (rotas v1, sem `cnpjLoja`) |
| `$client->v1->proposals` | `create`, `get` (rotas v1) |

Para novas integrações use a v2 (`simulations` e `proposals`), em que a loja é escolhida pelo `cnpjLoja` e a consulta da proposta devolve etapas, biometria e documentos.

### Cadastro de cliente: criar x atualizar

`customers->upsert` (`POST /v1/customer/unified`) **muda de comportamento com a `Idempotency-Key`**:

| Situação | CPF novo | CPF já cadastrado |
| --- | --- | --- |
| Sem chave (padrão do SDK) | Cria | Atualiza o cadastro |
| Com `$idempotencyKey` | Cria | `409` (`Cpf.CpfAlreadyAdded`) |

Por isso o SDK **nunca gera chave automática** nessa rota e **não repete** a chamada em caso de falha sem chave. Para repetir com segurança uma criação, informe você mesmo a chave: `$client->customers->upsert($request, $minhaChave)`.

## Idempotência e retentativas

O SDK gera um `Idempotency-Key` (UUID) por chamada lógica — e reaproveita **a mesma chave em todas as tentativas** — em: `customers->create`, `upsertAddress`, `upsertProfessionalData`, `simulations->create*`, `proposals->create`, `proposals->uploadDocument` e nas rotas v1 equivalentes.

| Chamada | Repetida em caso de falha transitória? |
| --- | --- |
| Todo `GET`, geração de token | Sim |
| `POST` com `Idempotency-Key` (automática ou informada) | Sim, com a mesma chave |
| `sendVehicleDetails`, `sendDepositAccount` (reenviar substitui) | Sim |
| `webhooks->update`, `webhooks->delete` | Sim |
| `customers->upsert` sem chave | **Não** |
| `webhooks->create` | **Não** (a rota não aceita chave): consulte `webhooks->list()` antes de tentar de novo |

Falhas transitórias: `408`, `429` (respeitando `Retry-After`), `500`, `502`, `503`, `504`, falha de rede e timeout de tentativa. Nenhum outro `4xx` é repetido. Redirects (`3xx`) nunca são seguidos: viram `CredicarroApiException`.

`Retry-After` aceita segundos ou data HTTP; valores inválidos ou menores ou iguais a zero são ignorados e valores absurdos são limitados a 24 h. Se o valor for maior que o orçamento restante, o SDK **não espera**: lança logo o erro (`CredicarroRateLimitException` com `getRetryAfterMs()` no `429`). Todo backoff é limitado ao orçamento restante. O orçamento é `totalTimeoutMs` (30 s) e, no envio de documento, `documentUploadAttemptTimeoutMs × maxRetryAttempts` (180 s).

A `Idempotency-Key` explícita deve ter de 1 a 200 caracteres ASCII imprimíveis (não só espaços), senão `CredicarroValidationException` (`Validation.IdempotencyKey.Invalid`) antes de qualquer requisição.

### Envio de documentos

```php
use Twila\Credicarro\Proposals\DocumentFile;
use Twila\Credicarro\Proposals\DocumentType;
use Twila\Credicarro\Proposals\ProposalDocumentUpload;
use Twila\Credicarro\Proposals\ProposalParticipant;

$arquivo = DocumentFile::fromFile('/caminho/cnh-comprador.pdf'); // também: fromBytes($bytes, $nome), fromStream($stream, $nome)

$client->proposals->uploadDocument(
    $propostaId,
    new ProposalDocumentUpload(ProposalParticipant::BUYER, DocumentType::CNH, $arquivo),
    $chaveDoEnvio // opcional, mas recomendada
);
```

`DocumentFile` valida o que a API validaria (em qualquer forma de construção: `fromFile($caminho, ?$nome)`, `fromBytes($bytes, $nome)` — conteúdo primeiro —, `fromStream($stream, $nome)` ou `new DocumentFile($bytes, $nome)`): nome sem caminho com até 255 caracteres, extensão `.pdf`, `.jpg`, `.jpeg` ou `.png`, e conteúdo de 1 byte a 10 MiB (arquivos e streams são lidos em blocos, até 10 MiB + 1, sem confiar no tamanho informado); o Base64 gerado é puro e materializado uma única vez, reaproveitado em todas as tentativas. O header `Idempotency-Key` é obrigatório na API: guarde **uma chave por envio** junto ao registro do documento e reaproveite-a nas retentativas desse envio; use uma chave **nova** ao reenviar na correção. Sem `$idempotencyKey`, o SDK gera uma e a reutiliza nas tentativas da própria chamada.

### O que enviar: `getPendingActions()`

`ProposalDetails::getPendingActions()` lista, só para os participantes cuja etapa ativa tem o integrador como responsável, o que precisa ser enviado:

| Etapa ativa | Ação (`PendingActionKind`) |
| --- | --- |
| `DOCUMENT_SUBMISSION` (6) | `SEND_DOCUMENTS` — tipos de documento com status `PENDING` |
| `DOCUMENT_CORRECTION` (7) | `CORRECT_DOCUMENTS` — tipos com status `INVALID` |
| `VEHICLE_DETAILS` (8) | `SEND_VEHICLE_DETAILS` |
| `DEPOSIT_ACCOUNT` (5) | `SEND_DEPOSIT_ACCOUNT` |

## Paginação

`simulations->list`, `proposals->list` e `webhooks->list` retornam um `PagedResult` — sem auto-paginação, você controla explicitamente o avanço de página. `ListRequest(página, tamanho)` valida no cliente `página ≥ 1` e `tamanho` entre 1 e 100.

```php
use Twila\Credicarro\ListRequest;

$page = $client->proposals->list(new ListRequest(1, 20));

foreach ($page->items as $proposta) {
    echo "{$proposta->number} {$proposta->statusName}" . PHP_EOL;
}

if ($page->hasNext) {
    $next = $client->proposals->list(new ListRequest($page->page + 1, 20));
}
```

## Tratamento de erros

| Erro | Quando |
| --- | --- |
| `CredicarroConfigurationException` | Configuração do `CredicarroClient` inválida (ex.: `clientId`/`clientSecret` ausentes, `baseUrl` sem `https`) |
| `CredicarroAuthenticationException` | Falha ao gerar o token: `400` e `401` (`Integration.Authentication.InvalidCredentials`) — qualquer outro status do endpoint de token (`3xx`, `403`, `5xx`...) vira `CredicarroApiException` com status e `tipo`, e falha de rede vira `CredicarroTimeoutException` ou `401` persistente depois da renovação |
| `CredicarroValidationException` | `400` — dados inválidos; também lançada pelas validações locais (paginação, arquivo, UUID) com os mesmos códigos `Validation.*` |
| `CredicarroBusinessRuleException` | `422` — regra de negócio; trate pelo código (`Store.Integration.Intermediation`, `Customer.Account.Incomplete`...) |
| `CredicarroRateLimitException` | `429`, com `getRetryAfterMs()` |
| `CredicarroApiException` | Qualquer outro erro de API (`403`, `404`, `409`, `5xx`) |
| `CredicarroTimeoutException` | Falha de rede, timeout (da tentativa ou total) ou circuit breaker aberto; a causa (`getPrevious()`) é sanitizada: só o tipo e o código, sem requisição, headers ou corpo do Guzzle |
| `CredicarroWebhookSignatureException` | Assinatura de webhook ausente, inválida ou fora da tolerância |

Todas herdam de `CredicarroException`; as de resposta HTTP herdam de `CredicarroApiException`.

```php
use Twila\Credicarro\Errors\CredicarroApiException;
use Twila\Credicarro\Errors\CredicarroBusinessRuleException;

try {
    $client->proposals->create(new CreateProposalRequest($cpf, $simulacaoId));
} catch (CredicarroBusinessRuleException $e) {
    if ($e->getErrorCode() === 'Proposal.SimulationAlreadyExpired') {
        // refazer a simulação
    }
} catch (CredicarroApiException $e) {
    echo "{$e->getStatusCode()} {$e->getErrorCode()}: {$e->getMessage()}" . PHP_EOL;
}
```

`getErrorCode()` devolve o `tipo` do erro — **use-o no seu tratamento**. Também há `getTitle()`, `getDetail()`, `getField()`, `getErrors()` e `getRawBody()`. `getErrors()` devolve a lista `erros` do corpo (`ProblemError` com `code`, `message`, `field`), que só vem preenchida quando o JSON enviado está malformado ou tem valor de tipo errado; nos demais erros ela é vazia e o campo com problema está em `getField()`. A mensagem da exceção traz só o status, o `tipo` e o `titulo`; `getDetail()` e `getRawBody()` podem conter dados pessoais — não os envie a logs sem tratamento. O formato padrão de Problem Details (`type`/`title`/`detail`) das falhas inesperadas também é entendido, e o `401` sem corpo vira `CredicarroAuthenticationException`.

## Webhooks

Cadastro e gerenciamento:

```php
use Twila\Credicarro\Webhooks\CreateWebhookRequest;
use Twila\Credicarro\Webhooks\WebhookAuthenticationType;
use Twila\Credicarro\Webhooks\WebhookType;

$resultado = $client->webhooks->create(new CreateWebhookRequest(
    WebhookType::PROPOSAL,
    'https://parceiro.com.br/webhooks/credicarro',
    WebhookAuthenticationType::BASIC,
    base64_encode('usuario:senha')
));

// O segredo de assinatura só vem NESTA resposta: guarde-o em um gerenciador de segredos.
$segredo = $resultado->signingSecret;
```

Validação das notificações recebidas — sempre contra o **corpo bruto** da requisição, antes de desserializar:

```php
use Twila\Credicarro\Webhooks\CredicarroWebhookEvent;

$evento = CredicarroWebhookEvent::parse(
    $corpoBruto,
    $_SERVER['HTTP_X_CREDICARRO_SIGNATURE'] ?? '',
    $segredo
); // lança CredicarroWebhookSignatureException se for inválido
```

`parse` confere o header `X-CrediCarro-Signature` (`t={timestamp},v1={assinatura}`), calculando o HMAC-SHA256 de `"{t}.{corpo bruto}"` com o segredo, em comparação de tempo constante, e rejeita timestamps fora da tolerância de 5 minutos (nos dois sentidos; ajuste no quarto argumento). Segredo vazio é sempre inválido, e `t`/`v1` duplicados no header também. O corpo precisa trazer um `id_proposta` UUID válido. Também existem `verifySignature(...)` (devolve `bool`), `verifyAuthorization($recebido, $esperado)` para o header `Authorization` (Basic/Bearer configurado no cadastro; compara os SHA-256 dos dois valores em tempo constante e nunca aceita esperado vazio) e `parseUnverified($corpo)`, só para testes.

`ProposalWebhookEvent` aceita os dois formatos de payload: o v1 (`id_proposta` + status em inglês) e o v2 (retrato completo — `isDetailed` — com `eventReason`, `buyer`, `store` e `seller`, no mesmo tipo `ProposalParticipantState` da consulta da proposta). Responda `2xx` rápido e processe de forma assíncrona, deduplicando pelo header `Idempotency-Key` da notificação. O payload do webhook pode chegar fora de ordem e `getPendingActions()` só existe em `ProposalDetails`: ao tratar o evento, **consulte a proposta** (`$client->proposals->get($evento->proposalId)`, `GET v2/proposal/{id}`) e use `getPendingActions()` nela. O **envio de documento roda em job/worker, nunca na requisição web**: o orçamento de uma chamada de upload chega a 180 s e o backoff usa `usleep`, que bloqueia o processo do PHP-FPM.

## Resiliência

Todos os valores têm padrão e podem ser ajustados em `ResilienceOptions`:

| Opção | Padrão |
| --- | --- |
| `totalTimeoutMs` | 30000 |
| `attemptTimeoutMs` | 10000 |
| `documentUploadAttemptTimeoutMs` | 60000 |
| `maxRetryAttempts` | 3 |
| `retryBaseDelayMs` | 500 (backoff exponencial com jitter) |
| `circuitBreakerFailureRatio` / `SamplingDurationMs` / `MinimumThroughput` / `BreakDurationMs` | 0.5 / 30000 / 10 / 15000 |
| `retryOn500` | `true` (a documentação manda repetir o `500` com a mesma `Idempotency-Key`) |
| `disableAutomaticIdempotencyKey` | `false` |

## Samples

- `samples/sample-cli` — script standalone, sem framework
- `samples/sample-slim` — `CredicarroClient` como singleton no [Slim Framework](https://www.slimframework.com/), com endpoint de webhook que valida a assinatura

## Qualidade, segurança e cobertura

- **Build/Test** (`ci.yml`) — `phpstan analyse` (nível 8) + `phpcs` (PSR-12) + suíte de testes (`phpunit`) em PHP 7.4–8.5.
- **Contract tests** (`contract-tests.yml`) — o SDK é executado contra o `openapi.yaml` público (`https://documentacao.credicarro.com.br/openapi.yaml`) e cada rota, parâmetro, header e propriedade de corpo é conferido contra o schema.
- **Quality** (`quality.yml`) — análise estática via Codacy CLI (PHPMD), resultados publicados na aba **Security → Code scanning** do repositório.
- **Security** (`security.yml`) — [Psalm](https://psalm.dev/) com taint analysis (o CodeQL não suporta PHP) + `composer audit`, rodando a cada PR/push e semanalmente.
- **Coverage** — cobertura de testes coletada via PCOV e publicada no [Codecov](https://codecov.io/gh/Twila-Digital/twila-credicarro-php-sdk).

## Documentação completa

[documentacao.credicarro.com.br](https://documentacao.credicarro.com.br) — referência de todos os endpoints, autenticação, erros, etapas e status, e webhooks.

## Contribuindo

Veja [CONTRIBUTING.md](CONTRIBUTING.md).

## Código de conduta

Este projeto segue o [Código de Conduta](CODE_OF_CONDUCT.md).

## Licença

[MIT](LICENSE)
