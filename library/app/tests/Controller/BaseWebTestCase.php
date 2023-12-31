<?php

/*
 * This file is part of the wysiwyl project.
 *
 * (c) Darkwood <mathieu@darkwood.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darkwood\Wysiwyl\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BaseWebTestCase extends WebTestCase
{
    /**
     * @param array<string>       $options An array of options to pass to the createKernel method
     * @param array<string,mixed> $server  An array of server parameters
     */
    protected static function createClient(array $options = [], array $server = []): KernelBrowser
    {
        $server['HTTPS'] ??= true;

        return parent::createClient($options, $server);
    }
}
