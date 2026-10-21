<?php

declare(strict_types=1);

namespace Twila\Credicarro\Errors;

/**
 * Raiz da hierarquia de erros do SDK. Capture esta classe para tratar qualquer falha do Credicarro.
 */
abstract class CredicarroException extends \Exception
{
}
