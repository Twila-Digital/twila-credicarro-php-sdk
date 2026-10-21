<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Http;

/**
 * @internal
 */
final class QueryStringBuilder
{
    /** @var array<int, string> */
    private $parameters = [];

    /**
     * @param string|int|float|null $value
     */
    public function add(string $name, $value): self
    {
        if ($value === null) {
            return $this;
        }

        $this->parameters[] = rawurlencode($name) . '=' . rawurlencode((string) $value);

        return $this;
    }

    public function build(string $path): string
    {
        if (empty($this->parameters)) {
            return $path;
        }

        return $path . '?' . implode('&', $this->parameters);
    }
}
