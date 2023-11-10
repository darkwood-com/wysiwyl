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

class Group
{
    /** @var string[] */
    private array $userIds = [];

    public function __construct(
        private string $identifier,
        private string $name,
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

    public function addUser(string $userId): void
    {
        $this->userIds[] = $userId;
    }

    /**
     * @return string[]
     */
    public function getUserIds(): array
    {
        return $this->userIds;
    }
}
