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

class User
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        private string $identifier,
        private string $name,
        private array $extra = [],
    ) {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtra(): array
    {
        return $this->extra;
    }
}
