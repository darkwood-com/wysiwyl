<?php

/*
 * This file is part of the wysiwyl project.
 *
 * (c) Darkwood <coucou@darkwood.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darkwood\Wysiwyl\Exception;

use Darkwood\Wysiwyl\Model\Wysiwyl;
use Darkwood\Wysiwyl\Model\User;

class MessageSendFailedException extends \RuntimeException implements WysiwylException
{
    public function __construct(
        private Wysiwyl $wysiwyl,
        private User $recipient,
        \Throwable $previous = null,
        string $precision = null,
    ) {
        parent::__construct(sprintf('Fail to send message to @%s.%s', $recipient->getName(), $precision ? ' ' . $precision : ''), 0, $previous);
    }

    public function getWysiwyl(): Wysiwyl
    {
        return $this->wysiwyl;
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }
}
