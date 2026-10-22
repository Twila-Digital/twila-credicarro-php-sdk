<?php

declare(strict_types=1);

namespace Twila\Credicarro;

/**
 * Parâmetros de paginação das listagens (`pagina_inicial` e `limite`).
 */
final class ListRequest
{
    /** @var int página inicial, a partir de 1 (`pagina_inicial`) */
    public $page;

    /** @var int itens por página, de 1 a 100 (`limite`) */
    public $pageSize;

    public function __construct(int $page = 1, int $pageSize = 10)
    {
        $this->page = $page;
        $this->pageSize = $pageSize;
    }
}
