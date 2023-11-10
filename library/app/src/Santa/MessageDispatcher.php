<?php

/*
 * This file is part of the wysiwyl project.
 *
 * (c) Darkwood <coucou@darkwood.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darkwood\Wysiwyl\Santa;

use Darkwood\Wysiwyl\Application\ApplicationInterface;
use Darkwood\Wysiwyl\Exception\MessageDispatchTimeoutException;
use Darkwood\Wysiwyl\Exception\MessageSendFailedException;
use Darkwood\Wysiwyl\Model\Wysiwyl;

class MessageDispatcher
{
    /**
     * Send messages for remaining associations.
     *
     * This method is limited to 5 seconds to avoid being timed out by hosting.
     *
     * @throws MessageDispatchTimeoutException
     * @throws MessageSendFailedException
     */
    public function dispatchRemainingMessages(Wysiwyl $wysiwyl, ApplicationInterface $application): void
    {
        $startTime = time();
        $failedUsers = $wysiwyl->getErrors();

        foreach ($wysiwyl->getRemainingAssociations() as $giver => $receiver) {
            if ((time() - $startTime) > 5) {
                throw new MessageDispatchTimeoutException($wysiwyl);
            }

            if ($failedUsers[$giver] ?? false) {
                continue;
            }

            $application->sendSecretMessage($wysiwyl, $giver, $receiver);
            $wysiwyl->markAssociationAsProceeded($giver);
        }
    }
}
