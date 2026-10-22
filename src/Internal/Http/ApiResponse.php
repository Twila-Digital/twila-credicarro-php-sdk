<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Http;

/**
 * @internal
 */
final class ApiResponse
{
    /** @var int */
    public $statusCode;

    /** @var mixed */
    public $body;

    /** @var array<string, string> */
    public $headers;

    /** @var string */
    public $rawBody;

    /**
     * @param mixed $body
     * @param array<string, string> $headers
     */
    public function __construct(int $statusCode, $body, array $headers, string $rawBody = '')
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->headers = $headers;
        $this->rawBody = $rawBody;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
