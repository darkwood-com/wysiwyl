<?php

/*
 * This file is part of the wysiwyl project.
 *
 * (c) Darkwood <mathieu@darkwood.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darkwood\Wysiwyl\Controller;

use Darkwood\Wysiwyl\Application\SlackApplication;
use Darkwood\Wysiwyl\Exception\AuthenticationException;
use Darkwood\Wysiwyl\Model\ApplicationToken;
use Darkwood\Wysiwyl\Slack\SlackProvider;
use Darkwood\Wysiwyl\Slack\UserExtractor;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class SlackController extends AbstractController
{
    public function __construct(
        private string $slackClientId,
        private string $slackClientSecret,
        private RouterInterface $router,
    ) {
    }

    /**
     * Ask for Slack authentication and store the AccessToken in Session.
     */
    #[Route('/auth/slack', name: 'slack_authenticate', methods: ['GET'])]
    public function authenticate(Request $request, SlackApplication $slackApplication, UserExtractor $userExtractor): Response
    {
        $session = $request->getSession();

        $provider = new SlackProvider([
            'clientId' => $this->slackClientId,
            'clientSecret' => $this->slackClientSecret,
            'redirectUri' => $this->router->generate('slack_authenticate', [], RouterInterface::ABSOLUTE_URL),
        ]);

        if ($request->query->has('error')) {
            return $this->redirectToRoute('homepage');
        }

        if (!$request->query->has('code')) {
            // If we don't have an authorization code then get one
            $options = [
                'scope' => [
                    'reactions:read',
                    'reactions:write',
                ],
            ];
            $authUrl = $provider->getAuthorizationUrl($options);

            $session->set(SlackApplication::SESSION_KEY_STATE, $provider->getState());

            return new RedirectResponse($authUrl);
        }
        // Check given state against previously stored one to mitigate CSRF attack
        if (empty($request->query->get('state')) || ($request->query->get('state') !== $session->get(SlackApplication::SESSION_KEY_STATE))) {
            $session->remove(SlackApplication::SESSION_KEY_STATE);

            throw new AuthenticationException(SlackApplication::APPLICATION_CODE, 'Invalid OAuth state.');
        }

        try {
            // Try to get an access token (using the authorization code grant)
            /** @var AccessToken $token */
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);

            $appToken = new ApplicationToken($token->getToken(), ['team' => $token->getValues()['team']['name']]);

            $admin = $userExtractor->getUser($token->getToken(), $token->getValues()['authed_user']['id']);
        } catch (\Exception $e) {
            throw new AuthenticationException(SlackApplication::APPLICATION_CODE, 'Failed to retrieve data from Slack.', $e);
        }

        $slackApplication->setToken($appToken);
        $slackApplication->setAdmin($admin);

        return new RedirectResponse($this->router->generate('run', [
            'application' => $slackApplication->getCode(),
        ]));
    }

    #[Route('/event/slack', name: 'event', methods: ['GET', 'POST'])]
    public function event(Request $request, SlackApplication $slackApplication, LoggerInterface $logger): Response
    {
        $payload = json_decode($request->getContent(), true);
        $logger->log('debug', 'reaction_added', [
            'payload' => $payload,
            'is_event' => isset($payload['event']) && $payload['event']['type'] === 'reaction_added' && $payload['event']['item']['type'] === 'message',
        ]);
        
        if (isset($payload['type']) && $payload['type'] === 'url_verification') {
            return $this->json([
                'challenge' => $payload['challenge'],
            ]);
        } else if (isset($payload['event']) && $payload['event']['type'] === 'reaction_added' && $payload['event']['item']['type'] === 'message'/* && $payload['event']['reaction'] === 'thumbsup'*/) {
            $channelId = $payload['event']['item']['channel'];
            $timestamp = $payload['event']['item']['ts'];
    
            // select random emoji
            $emojis = ['smile', 'heart', 'star', 'clap', 'fire'];
            $randomEmoji = $emojis[array_rand($emojis)];

            $appToken = new ApplicationToken($payload['token'], ['team' => $payload['team_id']]);
    
            $slackApplication->setToken($appToken);
            $slackApplication->reactionsAdd($channelId, $randomEmoji, $timestamp);
        }

        return $this->redirectToRoute('homepage');
    }
}
