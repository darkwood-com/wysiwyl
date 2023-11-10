<?php

/*
 * This file is part of the wysiwyl project.
 *
 * (c) Darkwood <coucou@darkwood.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darkwood\Wysiwyl\Model;

class ApplicationToken
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private string $token,
        private array $context = [],
    ) {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
