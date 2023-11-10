<?php

/*
 * This file is part of the wysiwyl project.
 *
 * (c) Darkwood <mathieu@darkwood.com>
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

    public function reactionsAdd(string $channel, string $name, string $timestamp);

    public function reset(): void;
}
