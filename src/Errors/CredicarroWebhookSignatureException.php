<?php

declare(strict_types=1);

namespace Twila\Credicarro\Errors;

/**
 * Assinatura de webhook ausente, malformada, inválida ou fora da janela de tolerância; também usada quando o corpo
 * do webhook não é um JSON de evento válido.
 */
final class CredicarroWebhookSignatureException extends CredicarroException
{
}
