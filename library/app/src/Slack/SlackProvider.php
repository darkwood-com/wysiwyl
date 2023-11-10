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

use AdamPaterson\OAuth2\Client\Provider\Slack;

/**
 * Overrides Slack provider while https://github.com/adam-paterson/oauth2-slack/pull/25
 * is not merged.
 */
class SlackProvider extends Slack
{
    public function getBaseAuthorizationUrl(): string
    {
        return 'https://slack.com/oauth/v2/authorize';
    }

    /**
     * @param array<string,mixed> $params
     */
    public function getBaseAccessTokenUrl(array $params): string
    {
        return 'https://slack.com/api/oauth.v2.access';
    }
}
