<?php

declare(strict_types=1);

namespace Twila\Credicarro;

/**
 * Página de resultados. Sem auto-paginação: avance a página explicitamente com `ListRequest`.
 */
final class PagedResult
{
    /** @var array<int, mixed> itens da página (instâncias do tipo listado) */
    public $items;

    /** @var bool */
    public $hasNext;

    /** @var bool */
    public $hasPrevious;

    /** @var int número da página atual (`numero`) */
    public $page;

    /** @var int itens por página (`tamanho`) */
    public $pageSize;

    /** @var int total de itens encontrados (`total`) */
    public $totalCount;

    /**
     * @param array<int, mixed> $items
     */
    public function __construct(
        array $items,
        bool $hasNext,
        bool $hasPrevious,
        int $page,
        int $pageSize,
        int $totalCount
    ) {
        $this->items = $items;
        $this->hasNext = $hasNext;
        $this->hasPrevious = $hasPrevious;
        $this->page = $page;
        $this->pageSize = $pageSize;
        $this->totalCount = $totalCount;
    }
}
