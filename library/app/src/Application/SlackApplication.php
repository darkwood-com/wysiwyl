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

use Darkwood\Wysiwyl\Model\ApplicationToken;
use Darkwood\Wysiwyl\Model\Wysiwyl;
use Darkwood\Wysiwyl\Model\User;
use Darkwood\Wysiwyl\Slack\ClientFactory;
use Darkwood\Wysiwyl\Slack\MessageSender;
use Darkwood\Wysiwyl\Slack\UserExtractor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;

class SlackApplication implements ApplicationInterface
{
    public const APPLICATION_CODE = 'slack';
    public const SESSION_KEY_STATE = 'santa.slack.state';

    private const SESSION_KEY_TOKEN = 'santa.slack.token';

    public function __construct(
        private RequestStack $requestStack,
        private UserExtractor $userExtractor,
        private MessageSender $messageSender,
        private ClientFactory $clientFactory,
        private LoggerInterface $logger,
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
        return 'slack_authenticate';
    }

    public function getOrganization(): string
    {
        return $this->getToken()->getContext()['team'] ?? '';
    }

    public function reactionsAdd(string $channel, string $name, string $timestamp)
    {
        $this->logger->log('debug', 'reactions.add', [
            'token' => $this->getToken()->getToken(),
            'channel' => $channel,
            'name' => $name,
        ]);
        $this->clientFactory
            ->getClientForToken($this->getToken()->getToken())
            ->reactionsAdd([
                'channel' => $channel,
                'name' => $name,
                'timestamp' => $timestamp,
            ], [
                'token' => $this->getToken()->getToken()
            ]);
    }

    public function reset(): void
    {
        $this->getSession()->remove(self::SESSION_KEY_TOKEN);
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
