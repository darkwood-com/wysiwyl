<?php

/*
 * This file is part of the wysiwyl project.
 *
 * (c) Darkwood <mathieu@darkwood.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darkwood\Wysiwyl\Webex;

use Darkwood\Wysiwyl\Model\Group;
use Darkwood\Wysiwyl\Model\User;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UserExtractor
{
    public function __construct(readonly private HttpClientInterface $client)
    {
    }

    /**
     * @return array{array<User>, array<Group>}
     */
    public function extractAll(string $token): array
    {
        $users = [];
        $groups = [];

        $roomsResponse = $this->client->request('GET', 'https://webexapis.com/v1/rooms?type=group&sortBy=lastactivity&max=20', [
            'auth_bearer' => $token,
            'headers' => [
                'accept' => 'application/json',
            ],
        ]);

        foreach ($roomsResponse->toArray()['items'] as $room) {
            $group = new Group((string) $room['id'], $room['title']);
            $groups[$room['id']] = $group;

            $roomMembersResponse = $this->client->request('GET', 'https://webexapis.com/v1/memberships?roomId=' . $room['id'], [
                'auth_bearer' => $token,
                'headers' => [
                    'accept' => 'application/json',
                ],
            ]);

            foreach ($roomMembersResponse->toArray()['items'] as $user) {
                $users[$user['personId']] = new User(
                    $user['personId'],
                    $user['personDisplayName']
                );

                $group->addUser($user['personId']);
            }
        }

        return [$users, $groups];
    }

    public function getMe(string $token): User
    {
        $me = $this->client->request('GET', 'https://webexapis.com/v1/people/me', [
            'auth_bearer' => $token,
            'headers' => [
                'accept' => 'application/json',
            ],
        ]);

        $me = $me->toArray();

        return new User($me['id'], $me['displayName']);
    }
}
