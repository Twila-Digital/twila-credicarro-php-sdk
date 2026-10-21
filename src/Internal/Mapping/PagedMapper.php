<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Mapping;

use Twila\Credicarro\Internal\Http\QueryStringBuilder;
use Twila\Credicarro\Internal\Validation;
use Twila\Credicarro\ListRequest;
use Twila\Credicarro\PagedResult;

/**
 * @internal
 */
final class PagedMapper
{
    /**
     * Monta o caminho com `pagina_inicial` e `limite`, validando 1..100 antes de chamar a API.
     */
    public static function path(string $basePath, ?ListRequest $request): string
    {
        $request = $request ?? new ListRequest();

        if ($request->page < 1) {
            throw Validation::fail(
                'Validation.Paging.InvalidNumber',
                "A página inicial ('pagina_inicial') deve ser maior ou igual a 1.",
                'pagina_inicial'
            );
        }

        if ($request->pageSize < 1 || $request->pageSize > 100) {
            throw Validation::fail(
                'Validation.Paging.InvalidSize',
                "O limite de itens ('limite') deve estar entre 1 e 100.",
                'limite'
            );
        }

        return (new QueryStringBuilder())
            ->add('pagina_inicial', $request->page)
            ->add('limite', $request->pageSize)
            ->build($basePath);
    }

    /**
     * @param mixed $body
     * @param callable(array<mixed>): mixed $itemMapper
     */
    public static function fromWire($body, callable $itemMapper): PagedResult
    {
        $wire = is_array($body) ? $body : [];
        $page = Wire::object($wire, 'pagina') ?? [];

        $items = [];
        foreach (Wire::objects($wire, 'itens') as $item) {
            $items[] = $itemMapper($item);
        }

        return new PagedResult(
            $items,
            Wire::bool($page, 'tem_proximo'),
            Wire::bool($page, 'tem_anterior'),
            Wire::int($page, 'numero'),
            Wire::int($page, 'tamanho'),
            Wire::int($page, 'total')
        );
    }
}
