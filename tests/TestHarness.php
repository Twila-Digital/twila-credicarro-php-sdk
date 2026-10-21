<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use GuzzleHttp\Handler\MockHandler;
use Psr\Http\Message\RequestInterface;
use Twila\Credicarro\CredicarroClient;

final class TestHarness
{
    /** @var CredicarroClient */
    public $client;

    /** @var MockHandler */
    public $mock;

    /** @var array<int, array{request: RequestInterface}> */
    public $history = [];

    /**
     * Requisições enviadas à API (sem as de geração de token), na ordem.
     *
     * @return array<int, RequestInterface>
     */
    public function apiRequests(): array
    {
        $requests = [];
        foreach ($this->history as $entry) {
            if (
                substr($entry['request']->getUri()->getPath(), -strlen('authentication/accesstoken'))
                !== 'authentication/accesstoken'
            ) {
                $requests[] = $entry['request'];
            }
        }

        return $requests;
    }

    /**
     * Requisições de geração de token, na ordem.
     *
     * @return array<int, RequestInterface>
     */
    public function tokenRequests(): array
    {
        $requests = [];
        foreach ($this->history as $entry) {
            if (
                substr($entry['request']->getUri()->getPath(), -strlen('authentication/accesstoken'))
                === 'authentication/accesstoken'
            ) {
                $requests[] = $entry['request'];
            }
        }

        return $requests;
    }

    public function lastApiRequest(): RequestInterface
    {
        $requests = $this->apiRequests();

        return $requests[count($requests) - 1];
    }
}
