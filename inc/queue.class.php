<?php

/*
   ------------------------------------------------------------------------
   FPWebhook - simple webhooks for GLPI
   Copyright (C) 2021 by Future Processing
   ------------------------------------------------------------------------

   LICENSE

   This file is part of FPFutures project.

   FPWebhook Plugin is free software: you can redistribute it and/or modify
   it under the terms of the GNU Affero General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   FPWebhook is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
   GNU Affero General Public License for more details.

   You should have received a copy of the GNU Affero General Public License
   along with FPWebhook. If not, see <http://www.gnu.org/licenses/>.

   ------------------------------------------------------------------------

   @package   FPFutures
   @author    Future Processing
   @co-author
   @copyright Copyright (c) 2021 by Future Processing
   @license   AGPL License 3.0 or (at your option) any later version
              http://www.gnu.org/licenses/agpl-3.0-standalone.html
   @since     2021

   ------------------------------------------------------------------------
*/

/**
 * Queue entry DB entity class
 */
class PluginFpwebhookQueue extends CommonDBTM
{
    public static $rightname = 'fpwebhooks';

    private const ACCEPTED_RESPONSES = [
        200,
        201,
        202,
        203,
        204,
        205,
        302,
        303,
        307
    ]; // includes temporary redirects
    private const REDIRECTING_RESPONSES = [301, 308]; // contains permanent redirects
    private const KILLING_RESPONSES = [410]; // Unsubscribe immediately

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_fpwebhook_queue';
    }

    public static function getTypeName($nb = 0)
    {
        return __('Queued message' . ($nb !== 1 ? 's' : ''));
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        return self::getTypeName();
    }

    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ) {
        switch ($tabnum) {
            case 1:
                if ($item instanceof PluginFpwebhookSubscription) {
                    $item->showQueue();
                }
                break;
        }
    }

    /**
     * Adds message to queue
     *
     * @param int $subscription_id
     * @param int $content_id
     *
     * @return bool
     */
    public static function queueMessage(int $subscription_id, int $content_id): bool
    {
        global $DB;

        return $DB->insert(
            self::getTable(),
            [
                'subscription_id' => $subscription_id,
                'content_id' => $content_id,
            ]
        );
    }

    /**
     * Main response sender
     * Called via cron
     *
     * @return int 0 on failure, 1 on success
     */
    public static function cronSendResponses(): int
    {
        $result = true;

        $messages = self::fetchMessagesToSend();

        foreach ($messages as $message) {
            $message['attempts']++;

            $response = self::dispatchMessage($message);

            $status = (int)$response['status'];
            $content = empty($response['content']) ? null : $response['content'];

            $success = false;

            if (in_array($status, self::KILLING_RESPONSES)) {
                $result = $result &&
                    PluginFpwebhookSubscription::unsubscribeByID(
                        $message['subscription_id'],
                        PluginFpwebhookSubscription::REASON_HTTP_STATUS_410
                    );
            }

            if (
                in_array($status, self::ACCEPTED_RESPONSES) ||
                in_array($status, self::REDIRECTING_RESPONSES)
            ) {
                $success = true;
            }

            self::makeHistory($message, $response['status'], $response['content']);

            if ($success) {
                if ($message['failures'] > 0) {
                    $result = $result &&
                        PluginFpwebhookSubscription::resetFailures($message['subscription_id']);
                }
                $result = $result && self::deleteMessageFromQueue($message['id']);
            } else {
                $result = $result && self::updateMessage($message, $status, $content);
            }
        }

        return (int)$result;
    }

    /**
     * Cleans the queue from failures
     * Called via cron
     *
     * @return int 0 on failure, 1 on success
     */
    public static function cronCleanQueue(): int
    {
        $result = true;

        $my_config = Config::getConfigurationValues('plugin:Fpwebhook');

        $messages = self::fetchMessagesToRemove();

        foreach ($messages as $message) {
            if ($message['attempts'] >= (
                    $my_config['max_attempts_per_message']
                    ?? PluginFpwebhookConfig::$default_max_attempts_per_message
                )) {
                // If the delivery was attempted several times and still failed, mark this as failure of the receiver
                $result = $result &&
                    PluginFpwebhookSubscription::addFailure($message['subscription_id']);
            }
            self::deleteMessageFromQueue($message['id']);
        }

        $result = $result && PluginFpwebhookSubscription::unsubscribeAllFailures();

        return (int)$result;
    }

    /**
     * Send a message
     *
     * @param $message
     *
     * @return array
     */
    private static function dispatchMessage($message): array
    {
        $curl = curl_init($message['url']);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $message['content']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($message['content']),
        ]);

        $response = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        curl_close($curl);

        return [
            'status' => $status,
            'content' => $response,
        ];
    }

    /**
     * Remove message from queue
     *
     * @param int $message_id
     *
     * @return bool
     */
    private static function deleteMessageFromQueue(int $message_id): bool
    {
        global $DB;

        return $DB->delete(
            self::getTable(),
            ['id' => $message_id]
        );
    }

    /**
     * Get messages waiting in queue
     *
     * @return DBmysqlIterator
     */
    private static function fetchMessagesToSend(): DBmysqlIterator
    {
        global $DB;

        $my_config = Config::getConfigurationValues('plugin:Fpwebhook');

        return $DB->request(
            [
                'SELECT' => [
                    'glpi_plugin_fpwebhook_queue' => '*',
                    'glpi_plugin_fpwebhook_subscriptions' => [
                        'failures',
                        'is_active',
                        'is_deleted',
                        'unsubscribed_at',
                        'url'
                    ],
                    'glpi_plugin_fpwebhook_contents' => ['content'],
                ],
                'FROM' => 'glpi_plugin_fpwebhook_queue',
                'WHERE' => [
                    'attempts < ' . (
                        $my_config['max_attempts_per_message']
                        ?? PluginFpwebhookConfig::$default_max_attempts_per_message
                    ),
                    'is_active = 1',
                    'is_deleted = 0',
                ],
                'INNER JOIN' => [
                    'glpi_plugin_fpwebhook_subscriptions' => [
                        'ON' => [
                            'glpi_plugin_fpwebhook_subscriptions' => 'id',
                            'glpi_plugin_fpwebhook_queue' => 'subscription_id'
                        ]
                    ],
                    'glpi_plugin_fpwebhook_contents' => [
                        'ON' => [
                            'glpi_plugin_fpwebhook_contents' => 'id',
                            'glpi_plugin_fpwebhook_queue' => 'content_id'
                        ]
                    ],
                ],
                'ORDER' => 'attempts', // first, the new, then the retries
                'LIMIT' => (
                    $my_config['max_messages_per_tick']
                    ?? PluginFpwebhookConfig::$default_max_messages_per_tick
                ),
            ]
        );
    }

    /**
     * Get messages waiting in queue
     *
     * @return DBmysqlIterator
     */
    private static function fetchMessagesToRemove(): DBmysqlIterator
    {
        global $DB;

        $my_config = Config::getConfigurationValues('plugin:Fpwebhook');

        return $DB->request(
            [
                'SELECT' => [
                    'glpi_plugin_fpwebhook_queue' => '*',
                    'glpi_plugin_fpwebhook_subscriptions' => [
                        'is_active',
                        'is_deleted',
                        'unsubscribed_at'
                    ],
                ],
                'FROM' => 'glpi_plugin_fpwebhook_queue',
                'WHERE' => [
                    'OR' => [
                        'attempts >= ' . (
                            $my_config['max_attempts_per_message']
                            ?? PluginFpwebhookConfig::$default_max_attempts_per_message
                        ), // too many attempts
                        'is_active = 0',
                        'is_deleted = 1',
                    ]
                ],
                'INNER JOIN' => [
                    'glpi_plugin_fpwebhook_subscriptions' => [
                        'ON' => [
                            'glpi_plugin_fpwebhook_subscriptions' => 'id',
                            'glpi_plugin_fpwebhook_queue' => 'subscription_id'
                        ]
                    ],
                ],
            ]
        );
    }

    /**
     * Updates attempts and last response or deletes if the attempts were too many already
     *
     * @param array $message
     * @param int $status
     * @param string|null $content
     *
     * @return bool
     */
    private static function updateMessage(array $message, int $status, ?string $content): bool
    {
        global $DB;

        return $DB->update(
            self::getTable(),
            [
                'response_status' => $status,
                'response_content' => $content,
                'attempts' => $message['attempts'],
            ],
            ['id' => $message['id']]
        );
    }

    /**
     * Creates an archive record
     *
     * @param array $message
     * @param int $status
     * @param string|null $response_content
     *
     * @return bool
     */
    private static function makeHistory(
        array $message,
        int $status,
        ?string $response_content
    ): bool {
        global $DB;

        return $DB->insert(
            PluginFpwebhookMessage::getTable(),
            [
                'subscription_id' => $message['subscription_id'],
                'content_id' => $message['content_id'],
                'response_status' => $status,
                'response_content' => $DB->escape(json_encode($response_content)),
                'url' => $message['url'],
                'attempt' => $message['attempts'],
            ]
        );
    }
}
