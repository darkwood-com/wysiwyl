<?php

/*
 * This file is part of the wysiwyl project.
 *
 * (c) Darkwood <coucou@darkwood.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darkwood\Wysiwyl\Slack;

use Darkwood\Slack\Api\Client;
use Darkwood\Slack\ClientFactory as DefaultClientFactory;
use Psr\Http\Client\ClientInterface as PsrHttpClient;

class ClientFactory
{
    /** @var array<string, Client> */
    private array $clientsByToken = [];

    public function __construct(private PsrHttpClient $httpClient)
    {
    }

    public function getClientForToken(string $token): Client
    {
        if (!isset($this->clientsByToken[$token])) {
            $this->clientsByToken[$token] = DefaultClientFactory::create($token, $this->httpClient);
        }

        return $this->clientsByToken[$token];
    }
}
