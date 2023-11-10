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

class UserExtractionFailedException extends \RuntimeException implements ApplicationRelatedException
{
    public function __construct(
        private string $applicationCode,
        string $message = '',
        \Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getApplicationCode(): string
    {
        return $this->applicationCode;
    }
}
