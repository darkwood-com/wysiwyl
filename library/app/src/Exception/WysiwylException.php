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

interface WysiwylException
{
    public function getWysiwyl(): Wysiwyl;
}
