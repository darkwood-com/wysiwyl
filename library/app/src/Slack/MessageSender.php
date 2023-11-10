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

use Darkwood\Wysiwyl\Exception\MessageSendFailedException;
use Darkwood\Wysiwyl\Model\Wysiwyl;

class MessageSender
{
    public function __construct(private ClientFactory $clientFactory)
    {
    }

    /**
     * @throws MessageSendFailedException
     */
    public function sendSecretMessage(Wysiwyl $wysiwyl, string $giver, string $receiver, string $token, bool $isSample): void
    {
        $fallbackText = '';
        $blocks = [];

        $schedule = $wysiwyl->getOptions()['scheduled_at'] ?? null;

        if ($isSample) {
            $blocks[] = [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => '_Find below a *sample* of the message that will be sent to all participants of your wysiwyl._',
                    ],
                ],
            ];

            $blocks[] = [
                'type' => 'divider',
            ];
        }

        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "Hi!\nYou have been selected to be part of a wysiwyl :santa:!\n\n",
            ],
        ];

        $receiverUser = $wysiwyl->getUser($receiver);
        $receiverBlock = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => sprintf("Someone will get you a gift and *you have been chosen to gift:*\n\n:gift: *<@%s>* :gift:\n\n", $receiver),
            ],
        ];

        if ($receiverUser->getExtra() && \array_key_exists('image', $receiverUser->getExtra())) {
            $receiverBlock['accessory'] = [
                'type' => 'image',
                'image_url' => $receiverUser->getExtra()['image'],
                'alt_text' => $receiverUser->getName(),
            ];
        }

        $blocks[] = $receiverBlock;

        $fallbackText .= sprintf('You have been selected to be part of a wysiwyl :santa:!
*You have been chosen to gift:* :gift: *<@%s>* :gift:', $receiver);

        if (!empty($userNote = $wysiwyl->getUserNote($receiver))) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => sprintf('*Here is some details about <@%s>:*', $receiver),
                ],
            ];

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $userNote,
                ],
            ];
        }

        if (!empty($wysiwyl->getAdminMessage())) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '*Here is a message from the wysiwyl admin:*',
                ],
            ];

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $wysiwyl->getAdminMessage(),
                ],
            ];

            $fallbackText .= sprintf("\n\nHere is a message from the wysiwyl admin:\n\n```\n%s\n```", $wysiwyl->getAdminMessage());
        } else {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => 'If you have any question please ask your wysiwyl admin',
                ],
            ];
        }

        $blocks[] = [
            'type' => 'divider',
        ];

        $footer = 'Organized with <https://wysiwyl.team/|Secret-Santa.team>';

        if ($admin = $wysiwyl->getConfig()->getAdmin()) {
            $footer .= sprintf(' by admin <@%s>.', $admin->getIdentifier());
        }

        $blocks[] = [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => $footer,
                ],
            ],
        ];

        $messageParameters = [
            'channel' => $giver,
            'text' => $fallbackText,
            'blocks' => json_encode($blocks),
            'unfurl_links' => false,
            'unfurl_media' => false,
        ];

        try {
            if ($schedule && !$isSample) {
                $messageParameters['post_at'] = (int) $schedule;
                $response = $this->clientFactory->getClientForToken($token)->chatScheduleMessage($messageParameters);
            } else {
                $response = $this->clientFactory->getClientForToken($token)->chatPostMessage($messageParameters);
            }

            if (!$response->getOk()) {
                throw new MessageSendFailedException($wysiwyl, $wysiwyl->getUser($giver));
            }
        } catch (\Throwable $t) {
            throw new MessageSendFailedException($wysiwyl, $wysiwyl->getUser($giver), $t);
        }
    }

    /**
     * @throws MessageSendFailedException
     */
    public function sendAdminMessage(Wysiwyl $wysiwyl, string $code, string $spoilUrl, string $token): void
    {
        $scheduled = $wysiwyl->getOptions()['scheduled_at'] ?? null;

        $message = 'Dear wysiwyl *admin*,' . \PHP_EOL . \PHP_EOL;

        if ($scheduled) {
            $message .= 'As a reminder, the messages will be sent on *' . date('m/d/Y', $wysiwyl->getOptions()['scheduled_at']) . '* at *' . date('H:i', $wysiwyl->getOptions()['scheduled_at']) . 'UTC*.' . \PHP_EOL . \PHP_EOL;
        }

        $message .= 'In case of trouble or if you need it for whatever reason, here is a way to *retrieve the secret repartition*:

- Copy the following content:
```%s```
- Paste the content on <%s|this page> then submit

Remember, with great power comes great responsibility!

Happy wysiwyl!';

        $text = sprintf(
            $message,
            $code,
            $spoilUrl
        );

        try {
            $response = $this->clientFactory->getClientForToken($token)->chatPostMessage([
                'channel' => $wysiwyl->getConfig()->getAdmin()->getIdentifier(),
                'icon_url' => 'https://wysiwyl.team/images/logo-spoiler.png',
                'text' => $text,
            ]);

            if (!$response->getOk()) {
                throw new MessageSendFailedException($wysiwyl, $wysiwyl->getConfig()->getAdmin());
            }
        } catch (\Throwable $t) {
            throw new MessageSendFailedException($wysiwyl, $wysiwyl->getConfig()->getAdmin(), $t);
        }
    }
}
