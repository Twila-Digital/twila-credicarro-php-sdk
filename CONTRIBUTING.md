# Contribuindo

## Pré-requisitos

- PHP 7.4+
- [Composer](https://getcomposer.org/)

## Build e testes

```bash
composer install

vendor/bin/phpstan analyse
vendor/bin/phpcs
vendor/bin/phpunit
```

Não commitamos `composer.lock` (convenção padrão para bibliotecas PHP) — cada ambiente resolve as dependências de acordo com sua própria versão de PHP. Isso importa de verdade aqui: `phpunit/phpunit` está declarado como `^9.6 || ^10.5` porque a versão `10.5` exige PHP ≥ 8.1; rodando `composer install` num PHP 7.4/8.0, o Composer resolve sozinho a branch `9.6.x` (compatível), e a suíte de testes roda igual — sem precisar de um job de "smoke test" separado para o piso legado.

O código precisa continuar compilando em PHP 7.4: nada de `enum` nativo, `readonly`, constructor promotion, union types, `match`, `str_contains`/`str_starts_with` ou operador nullsafe. Os "enums" do SDK são classes com constantes inteiras (ou de texto, nos que só vão para a API).

Os contract tests (`contract-tests/`) executam o SDK contra o `openapi.yaml` público da documentação (`https://documentacao.credicarro.com.br/openapi.yaml`, ou a URL da variável `CREDICARRO_CONTRACT_TESTS_OPENAPI_URL`) e não rodam por padrão (suíte separada, própria config de `phpunit.xml`):

```bash
vendor/bin/phpunit -c contract-tests/phpunit.xml
```

Para uma análise de segurança local equivalente à do CI (requer PHP ≥ 8.1.31):

```bash
vendor/bin/psalm --taint-analysis
```

## Instalando a partir do código-fonte

Enquanto o pacote não é publicado no Packagist, referencie o repositório direto no `composer.json` do seu projeto:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/Twila-Digital/twila-credicarro-php-sdk" }
    ],
    "require": {
        "twila/credicarro": "dev-production"
    }
}
```

## Abrindo um PR

1. Crie uma branch a partir de `production`
2. Adicione testes para qualquer mudança de comportamento
3. Rode `vendor/bin/phpstan analyse && vendor/bin/phpcs && vendor/bin/phpunit` localmente antes de abrir o PR
4. Abra o PR contra `production` — o CI roda build + testes automaticamente

## Release (publicação no Packagist)

Diferente de npm/PyPI/NuGet, o **Packagist não tem um fluxo de "publish" via CI** — nenhuma credencial, token ou Trusted Publishing envolvidos. O Packagist se integra via **webhook do GitHub**: uma vez configurado, toda tag `vX.Y.Z` enviada ao repositório é detectada automaticamente e vira uma nova versão do pacote, sem nenhum passo de CI/CD necessário para a publicação em si.

Configuração inicial (só uma vez, manual, por quem tiver acesso à conta/organização do Packagist):

1. Criar conta em [packagist.org](https://packagist.org/) e conectar com o GitHub (login via OAuth) — isso já habilita o webhook automaticamente para os repositórios da organização `Twila-Digital`.
2. Submeter o pacote pela primeira vez em [packagist.org/packages/submit](https://packagist.org/packages/submit), informando a URL do repositório (`https://github.com/Twila-Digital/twila-credicarro-php-sdk`).
3. Nas configurações do pacote no Packagist, adicionar um mantenedor adicional da Twila Digital.

Depois disso, `git push --tags` numa tag `v*` já é o release inteiro do ponto de vista do Packagist — o `release.yml` deste repositório serve só para: rodar CI + contract tests como gate de qualidade, e criar o GitHub Release com changelog automático. Não há aprovação manual de `environment` aqui porque não existe nenhuma credencial de publish para proteger — o Packagist já vai puxar a tag de qualquer forma, independente do resultado do workflow do GitHub Actions.

## Reportando problemas

Abra uma [issue](https://github.com/Twila-Digital/twila-credicarro-php-sdk/issues) com passos para reproduzir, versão do pacote/PHP e o comportamento esperado vs. observado. Nunca inclua `clientId`/`clientSecret` reais, tokens, CPFs ou dados pessoais no relato.
