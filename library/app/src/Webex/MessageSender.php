<?php

/*
 * This file is part of the wysiwyl project.
 *
 * (c) Darkwood <coucou@darkwood.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darkwood\Wysiwyl\Webex;

use Darkwood\Wysiwyl\Exception\MessageSendFailedException;
use Darkwood\Wysiwyl\Model\Wysiwyl;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MessageSender
{
    public function __construct(readonly private HttpClientInterface $client, readonly private string $webexBotToken)
    {
    }

    public function sendSecretMessage(Wysiwyl $wysiwyl, string $giver, string $receiver, bool $isSample): void
    {
        $text = '';

        if ($isSample) {
            $text .= "_Find below a **sample** of the message that will be sent to all participants of your wysiwyl._\n\n----\n\n";
        }

        $receiverUser = $wysiwyl->getUser($receiver);

        $text .= sprintf(
            'Hi!

You have been selected to be part of a wysiwyl 🎅!

Someone will get you a gift and **you have been chosen to gift:**

🎁 **%s** 🎁',
            $receiverUser->getName()
        );

        if ($userNote = $wysiwyl->getUserNote($receiver)) {
            $text .= sprintf("\n\nHere is some details about %s:\n\n```\n%s\n```", $receiverUser->getName(), $userNote);
        }

        if ($wysiwyl->getAdminMessage()) {
            $text .= "\n\nHere is a message from the wysiwyl admin:\n\n```\n" . $wysiwyl->getAdminMessage() . "\n```";
        } else {
            $text .= "\n\nIf you have any question please ask your wysiwyl admin";
        }

        $text .= "\n\n_Organized with Secret-Santa.team";

        if ($admin = $wysiwyl->getConfig()->getAdmin()) {
            $text .= sprintf(' by admin %s._', $admin->getName());
        } else {
            $text .= '_';
        }

        $messageSend = $this->client->request('POST', 'https://webexapis.com/v1/messages', [
            'auth_bearer' => $this->webexBotToken,
            'headers' => [
                'accept' => 'application/json',
            ],
            'json' => [
                'toPersonId' => $giver,
                'markdown' => $text,
            ],
        ]);

        if (200 === $messageSend->getStatusCode()) {
            return;
        }

        throw new MessageSendFailedException($wysiwyl, $wysiwyl->getUser($giver));
    }

    public function sendAdminMessage(Wysiwyl $wysiwyl, string $code, string $spoilUrl): void
    {
        $text = sprintf(
            'Dear wysiwyl **admin**,

In case of trouble or if you need it for whatever reason, here is a way to **retrieve the secret repartition**:

- Copy the following content:
```%s```
- Paste the content on %s then submit

Remember, with great power comes great responsibility!

Happy wysiwyl!',
            $code,
            $spoilUrl
        );

        $messageSend = $this->client->request('POST', 'https://webexapis.com/v1/messages', [
            'auth_bearer' => $this->webexBotToken,
            'headers' => [
                'accept' => 'application/json',
            ],
            'json' => [
                'toPersonId' => $wysiwyl->getConfig()->getAdmin()->getIdentifier(),
                'markdown' => $text,
            ],
        ]);

        if (200 === $messageSend->getStatusCode()) {
            return;
        }

        throw new MessageSendFailedException($wysiwyl, $wysiwyl->getConfig()->getAdmin());
    }
}
