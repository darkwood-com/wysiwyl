<?php

/*
 * This file is part of the wysiwyl project.
 *
 * (c) Darkwood <coucou@darkwood.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darkwood\Wysiwyl\Application;

use Darkwood\Wysiwyl\Exception\MessageSendFailedException;
use Darkwood\Wysiwyl\Exception\UserExtractionFailedException;
use Darkwood\Wysiwyl\Model\Group;
use Darkwood\Wysiwyl\Model\Wysiwyl;
use Darkwood\Wysiwyl\Model\User;
use Symfony\Component\Form\FormBuilderInterface;

interface ApplicationInterface
{
    public function getCode(): string;

    public function isAuthenticated(): bool;

    public function getAuthenticationRoute(): string;

    public function getOrganization(): string;

    public function getAdmin(): ?User;

    /**
     * An array of Group indexed by their identifier.
     *
     * @return Group[]
     */
    public function getGroups(): array;

    /**
     * An array of User indexed by their identifier.
     *
     * @return User[]
     *
     * @throws UserExtractionFailedException
     */
    public function getUsers(): array;

    /**
     * @throws MessageSendFailedException
     */
    public function sendSecretMessage(Wysiwyl $wysiwyl, string $giver, string $receiver, bool $isSample = false): void;

    /**
     * @throws MessageSendFailedException
     */
    public function sendAdminMessage(Wysiwyl $wysiwyl, string $code, string $spoilUrl): void;

    public function configureMessageForm(FormBuilderInterface $builder): void;

    public function reset(): void;
}
