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

use Darkwood\Wysiwyl\Discord\MessageSender;
use Darkwood\Wysiwyl\Discord\UserExtractor;
use Darkwood\Wysiwyl\Model\ApplicationToken;
use Darkwood\Wysiwyl\Model\Group;
use Darkwood\Wysiwyl\Model\Wysiwyl;
use Darkwood\Wysiwyl\Model\User;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class DiscordApplication implements ApplicationInterface
{
    public const APPLICATION_CODE = 'discord';
    public const SESSION_KEY_STATE = 'santa.discord.state';

    private const SESSION_KEY_TOKEN = 'santa.discord.token';

    /** @var Group[]|null */
    private ?array $groups = null;

    public function __construct(
        private RequestStack $requestStack,
        private UserExtractor $userExtractor,
        private MessageSender $messageSender,
    ) {
    }

    public function getCode(): string
    {
        return self::APPLICATION_CODE;
    }

    public function isAuthenticated(): bool
    {
        try {
            $this->getToken();

            return true;
        } catch (\LogicException $e) {
            return false;
        }
    }

    public function getAuthenticationRoute(): string
    {
        return 'discord_authenticate';
    }

    public function getOrganization(): string
    {
        return $this->getToken()->getContext()['guildName'];
    }

    public function getGuildId(): ?int
    {
        return $this->getToken()->getContext()['guildId'];
    }

    public function reactionsAdd(string $channel, string $name, string $timestamp)
    {
    }

    public function reset(): void
    {
        $this->getSession()->remove(self::SESSION_KEY_TOKEN);
    }

    public function setToken(ApplicationToken $token): void
    {
        $this->getSession()->set(self::SESSION_KEY_TOKEN, $token);
    }

    public function getToken(): ApplicationToken
    {
        $token = $this->getSession()->get(self::SESSION_KEY_TOKEN);

        if (!$token instanceof ApplicationToken) {
            throw new \LogicException('Invalid token.');
        }

        return $token;
    }

    public function configureMessageForm(FormBuilderInterface $builder): void
    {
    }

    private function getSession(): SessionInterface
    {
        return $this->requestStack->getMainRequest()->getSession();
    }
}
