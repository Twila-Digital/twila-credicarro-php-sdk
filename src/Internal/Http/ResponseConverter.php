<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
final class ResponseConverter
{
    public static function toApiResponse(ResponseInterface $raw): ApiResponse
    {
        $bodyRaw = (string) $raw->getBody();
        $decoded = $bodyRaw === '' ? null : json_decode($bodyRaw, true);

        $headers = [];
        foreach ($raw->getHeaders() as $name => $values) {
            $headers[(string) $name] = implode(', ', $values);
        }

        return new ApiResponse($raw->getStatusCode(), $decoded, $headers, $bodyRaw);
    }
}
