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

class MessageDispatchTimeoutException extends \RuntimeException implements WysiwylException
{
    public function __construct(private Wysiwyl $wysiwyl)
    {
        parent::__construct('It takes too much time to send messages!');
    }

    public function getWysiwyl(): Wysiwyl
    {
        return $this->wysiwyl;
    }
}
