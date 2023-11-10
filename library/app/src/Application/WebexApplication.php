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

use Darkwood\Wysiwyl\Model\ApplicationToken;
use Darkwood\Wysiwyl\Model\Group;
use Darkwood\Wysiwyl\Model\Wysiwyl;
use Darkwood\Wysiwyl\Model\User;
use Darkwood\Wysiwyl\Webex\MessageSender;
use Darkwood\Wysiwyl\Webex\UserExtractor;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class WebexApplication implements ApplicationInterface
{
    public const APPLICATION_CODE = 'webex';
    public const SESSION_KEY_STATE = 'santa.webex.state';

    private const SESSION_KEY_TOKEN = 'santa.webex.token';
    private const SESSION_KEY_ADMIN = 'santa.webex.admin';

    /**
     * @var array<Group>
     */
    private array $groups = [];

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
        return 'webex_authenticate';
    }

    public function getOrganization(): string
    {
        return $this->getToken()->getContext()['team'] ?? '';
    }

    public function getAdmin(): ?User
    {
        return $this->getSession()->get(self::SESSION_KEY_ADMIN);
    }

    public function setAdmin(User $admin): void
    {
        $this->getSession()->set(self::SESSION_KEY_ADMIN, $admin);
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getUsers(): array
    {
        [$users, $groups] = $this->userExtractor->extractAll($this->getToken()->getToken());

        $this->groups = $groups;

        return $users;
    }

    public function sendSecretMessage(Wysiwyl $wysiwyl, string $giver, string $receiver, bool $isSample = false): void
    {
        $this->messageSender->sendSecretMessage($wysiwyl, $giver, $receiver, $isSample);
    }

    public function sendAdminMessage(Wysiwyl $wysiwyl, string $code, string $spoilUrl): void
    {
        $this->messageSender->sendAdminMessage($wysiwyl, $code, $spoilUrl);
    }

    public function configureMessageForm(FormBuilderInterface $builder): void
    {
    }

    public function reset(): void
    {
        $this->getSession()->remove(self::SESSION_KEY_TOKEN);
        $this->getSession()->remove(self::SESSION_KEY_ADMIN);
    }

    public function setToken(ApplicationToken $token): void
    {
        $this->getSession()->set(self::SESSION_KEY_TOKEN, $token);
    }

    private function getToken(): ApplicationToken
    {
        $token = $this->getSession()->get(self::SESSION_KEY_TOKEN);

        if (!$token instanceof ApplicationToken) {
            throw new \LogicException('Invalid token.');
        }

        return $token;
    }

    private function getSession(): SessionInterface
    {
        return $this->requestStack->getMainRequest()->getSession();
    }
}
