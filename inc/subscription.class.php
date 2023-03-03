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
 * Subscription DB entity class
 */
class PluginFpwebhookSubscription extends CommonDBTM
{
    public const STILL_ACTIVE = 0; // GLPI does not always restore NULL
    public const REASON_MAX_FAILURES = 1;
    public const REASON_HTTP_STATUS_410 = 2;

    private const UNSUBSCRIPTION_REASONS = [
        self::STILL_ACTIVE => 'Still active',
        self::REASON_MAX_FAILURES => 'Allowed failures exceeded',
        self::REASON_HTTP_STATUS_410 => 'Received 410 Gone reply',
    ];

    private static $typeNames = [];

    public static $rightname = 'fpwebhooks';

    public static function getTypeName($nb = 0): string
    {
        return __('Webhook subscription' . ($nb !== 1 ? 's' : ''));
    }

    public static function getIcon(): string
    {
        return 'fas fa-eye';
    }

    public function rawSearchOptions(): array
    {
        $tab = [];

        $tab[] = [
            'id' => 'common',
            'name' => __('Characteristics')
        ];

        $tab[] = [
            'id' => '1',
            'table' => $this->getTable(),
            'field' => 'name',
            'name' => __('Name'),
            'datatype' => 'itemlink',
            'massiveaction' => false,
            'autocomplete' => true,
        ];

        $tab[] = [
            'id' => '2',
            'table' => $this->getTable(),
            'field' => 'event_type_id',
            'name' => __('Event type'),
            'datatype' => 'int',
            'massiveaction' => false,
            'autocomplete' => true,
        ];

        $tab[] = [
            'id' => '3',
            'table' => $this->getTable(),
            'field' => 'is_active',
            'name' => __('Active?'),
            'datatype' => 'bool',
            'massiveaction' => false,
            'autocomplete' => true,
        ];

        $tab[] = [
            'id' => '4',
            'table' => $this->getTable(),
            'field' => 'failures',
            'name' => __('Failures'),
            'datatype' => 'integer',
            'massiveaction' => false,
            'autocomplete' => true,
        ];

        $tab[] = [
            'id' => '5',
            'table' => $this->getTable(),
            'field' => 'url',
            'name' => __('Webhook URL'),
            'datatype' => 'text',
            'massiveaction' => false,
            'autocomplete' => true,
        ];

        // add ObjectLock search options
        $tab = array_merge($tab, ObjectLock::rawSearchOptionsToAdd(get_class($this)));

        return $tab;
    }

    public function showForm($ID, $options = []): bool
    {
        $is_new_record = empty($ID);

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo '<tbody>';

        echo '<tr class="headerRow">';
        echo '<th colspan="3">' . __('Settings') . '</th>';
        echo '</tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Name') . '</td>';
        echo '<td colspan="3">';
        echo Html::input('name', [
            'required' => true,
            'value' => $this->fields['name'],
        ]);
        echo '</td>';
        echo '</tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Target URL') . '</td>';
        echo '<td colspan="3">';
        echo Html::input('url', [
            'required' => true,
            'value' => $this->fields['url'],
        ]);
        echo '</td>';
        echo '</tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Event') . '</td>';
        echo '<td>';
        PluginFpwebhookEventType::dropdown(
            [
                'addicon' => false,
                'comments' => false,
                'name' => 'event_type_id',
                'required' => true,
                'display_emptychoice' => false,
                'value' => $this->fields['event_type_id'],
            ]
        );
        echo '</td>';
        echo '</tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Active') . '</td>';
        echo '<td>';
        Html::showCheckbox(
            [
                'checked' => $is_new_record || $this->isActive(),
                'value' => $is_new_record || $this->isActive(),
                'name' => 'is_active'
            ]
        );
        echo '</td>';
        echo '</tr>';

        if (
            !$is_new_record &&
            !$this->isActive() &&
            isset($this->fields['unsubscribed_because']) &&
            $this->fields['unsubscribed_because'] !== self::STILL_ACTIVE // reactivation doesn't erase
        ) {
            echo '<tr class="headerRow">';
            echo '<th colspan="3">' . __('Automatic deactivation') . '</th>';
            echo '</tr>';

            echo '<tr class="tab_bg_1">';
            echo '<td>Deactivated at</td>';
            echo '<td>'
                . ($this->fields['unsubscribed_at'] ?? 'Unknown')
                . '</td>';
            echo '</tr>';

            echo '<tr class="tab_bg_1">';
            echo '<td>Deactivation reason</td>';
            echo '<td>'
                . (self::UNSUBSCRIPTION_REASONS[$this->fields['unsubscribed_because']] ?? 'Unknown')
                . '</td>';
            echo '</tr>';
        }

        echo '<tr class="headerRow">';
        echo '<th colspan="3">' . __('Filters') . '</th>';
        echo '</tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Filter regular expression') . '</td>';
        echo '<td colspan="3">';
        echo Html::input('filtering_regex', [
            'value' => $this->fields['filtering_regex'],
        ]);
        echo '</td>';
        echo '</tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Ticket category') . '</td>';
        echo '<td>';
        ITILCategory::dropdown(
            [
                'comments' => false,
                'name' => 'filtering_category_id',
                'required' => false,
                'display_emptychoice' => true,
                'value' => $this->fields['filtering_category_id'],
            ]
        );
        echo '</td>';
        echo '</tr>';

        echo '</tbody>';

        $this->showFormButtons($options);

        return true;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        switch ($field) {
            case 'failures':
                $my_config = Config::getConfigurationValues('plugin:Fpwebhook');
                return $values[$field] . ' / ' . (
                        $my_config['max_allowed_failures']
                        ?? PluginFpwebhookConfig::$default_max_allowed_failures
                    );
            case 'event_type_id':
                return self::getEventTypeName($values[$field]);
            case 'unsubscribed_because':
                return self::UNSUBSCRIPTION_REASONS[$values[$field]];
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    private static function getEventTypeName(int $event_type_id)
    {
        global $DB;

        if (empty(self::$typeNames)) {
            $typeQuery = $DB->request(['FROM' => 'glpi_plugin_fpwebhook_eventtypes']);

            self::$typeNames = [];

            foreach ($typeQuery as $type) {
                self::$typeNames[$type['id']] = $type['name'];
            }
        }

        return self::$typeNames[$event_type_id] ?? __('Unknown');
    }

    /**
     * Deactivates webhook
     *
     * @param int $subscription_id ID of the webhook to deactivate
     * @param string|null $reason Reason for deactivation
     *
     * @return bool
     */
    public static function unsubscribeByID(int $subscription_id, ?string $reason = null): bool
    {
        return self::unsubscribe(['id' => $subscription_id], $reason);
    }

    /**
     * Adds to failure count on the webhook
     *
     * @param int $subscription_id
     *
     * @return bool
     */
    public static function addFailure(int $subscription_id): bool
    {
        global $DB;

        return $DB->query(
            'UPDATE ' . self::getTable() .
            ' SET failures = failures + 1 WHERE id = ' . $subscription_id
        );
    }

    /**
     * Resets failures upon success
     *
     * @param int $subscription_id
     *
     * @return bool
     */
    public static function resetFailures(int $subscription_id): bool
    {
        global $DB;

        return $DB->update(
            self::getTable(),
            ['failures' => 0],
            ['id' => $subscription_id]
        );
    }

    /**
     * Unsubscribes all hooks that failed too many times
     */
    public static function unsubscribeAllFailures(): bool
    {
        $my_config = Config::getConfigurationValues('plugin:Fpwebhook');
        $max_allowed_failures =
            $my_config['max_allowed_failures']
            ?? PluginFpwebhookConfig::$default_max_allowed_failures;

        return self::unsubscribe(
            ['failures >= ' . $max_allowed_failures],
            PluginFpwebhookSubscription::REASON_MAX_FAILURES
        );
    }

    /**
     * Fixes the 'on'/'0' behavior of form checkbox with some safety margin
     *
     * @param string $checkboxInput
     *
     * @return bool|null
     */
    public static function fixInputForCheckbox(string $checkboxInput): ?bool
    {
        $result = null;

        switch ($checkboxInput) {
            case 'on':
            case '1':
                $result = true;
                break;
            case 'off':
            case '0':
                $result = false;
                break;
        }

        return $result;
    }

    /**
     * Creates header for list and form
     */
    public static function makeHeader(): void
    {
        if ($_SESSION['glpiactiveprofile']['interface'] === 'central') {
            Html::header(
                'Webhooks',
                $_SERVER['PHP_SELF'],
                'plugins',
                'pluginfpwebhooksubscription',
                ''
            );
        } else {
            Html::helpHeader(
                'Webhooks',
                $_SERVER['PHP_SELF']
            );
        }
    }

    public function defineTabs($options = []): array
    {
        $tabs = [];

        $this->addDefaultFormTab($tabs);
        $this->addStandardTab('PluginFpwebhookMessage', $tabs, $options);
        $this->addStandardTab('PluginFpwebhookQueue', $tabs, $options);

        return $tabs;
    }

    /**
     * Shows message history for this subscription
     */
    public function showMessages(): void
    {
        global $DB;

        if (!empty($_GET['message_id'])) {
            $this->showMessageDetails($_GET['message_id']);
            return;
        }

        if (!empty($_GET['content_id'])) {
            $this->showContentDetails($_GET['content_id'], __('Sent messages list'));
            return;
        }

        $start = $_GET['start'] ?? 0;

        $total_count = countElementsInTable(
            PluginFpwebhookMessage::getTable(),
            [
                'subscription_id' => $this->fields['id'],
            ]
        );

        echo '<br><div class="center">';

        if ($total_count < 1) {
            echo '<table class="tab_cadre_fixe">';
            echo '<tr><th>' . __('No items found') . '</th></tr>';
            echo '</table>';
            echo '</div>';
            return;
        }

        Html::printAjaxPager(__('Sent messages list'), $start, $total_count);

        $iterator = $DB->request([
            'SELECT' => ['*', PluginFpwebhookMessage::getTable() . '.id as message_id'],
            'FROM' => PluginFpwebhookMessage::getTable(),
            'WHERE' => [
                'subscription_id' => $this->fields['id'],
            ],
            'INNER JOIN' => [
                'glpi_plugin_fpwebhook_contents' => [
                    'ON' => [
                        'glpi_plugin_fpwebhook_contents' => 'id',
                        PluginFpwebhookMessage::getTable() => 'content_id'
                    ]
                ],
                'glpi_plugin_fpwebhook_eventtypes' => [
                    'ON' => [
                        'glpi_plugin_fpwebhook_eventtypes' => 'id',
                        'glpi_plugin_fpwebhook_contents' => 'event_type_id'
                    ]
                ],
            ],
            'ORDER' => PluginFpwebhookMessage::getTable() . '.id DESC',
            'START' => (int)$start,
            'LIMIT' => (int)$_SESSION['glpilist_limit']
        ]);

        if (count($iterator)) {
            echo '<table class="tab_cadre_fixehov">';
            $header = '<tr>';
            $header .= '<th>' . __('Event Type') . '</th>';
            $header .= '<th>' . __('Sent at') . '</th>';
            $header .= '<th>' . __('Content size') . '</th>';
            $header .= '<th>' . __('Response') . '</th>';
            $header .= '<th>' . __('Attempt') . '</th>';
            $header .= '</tr>';
            echo $header;

            foreach ($iterator as $data) {
                echo '<tr class="tab_bg_2">';
                echo '<td>' . $data['name'] . '</td>';
                echo '<td>' . Html::convDateTime($data['called_at']) . '</td>';
                echo '<td>' .
                    '<a href="javascript:reloadTab(\'content_id=' .
                    $data['content_id'] . '\')">' .
                    self::convertSize(strlen($data['content']), 1) .
                    '</td>';
                echo '<td>' .
                    '<a href="javascript:reloadTab(\'message_id=' .
                    $data['message_id'] . '\')">' .
                    $data['response_status'] .
                    '</td>';
                echo '<td>' . $data['attempt'] . '</td>';
                echo '</tr>';
            }
            echo $header;
            echo '</table>';
        } else {
            echo __('No items found');
        }

        Html::printAjaxPager(__('Sent messages list'), $start, $total_count);

        echo '</div>';
    }

    /**
     * Shows queue content for this subscription
     */
    public function showQueue(): void
    {
        global $DB;

        if (!empty($_GET['content_id'])) {
            $this->showContentDetails($_GET['content_id'], __('Queue content'));
            return;
        }

        $start = $_GET['start'] ?? 0;

        $total_count = countElementsInTable(
            PluginFpwebhookQueue::getTable(),
            [
                'subscription_id' => $this->fields['id'],
            ]
        );

        echo '<div class="center">';

        if ($total_count < 1) {
            echo '<table class="tab_cadre_fixe">';
            echo '<tr><th>' . __('No messages for this subscription in the queue') . '</th></tr>';
            echo '</table>';
            echo '</div>';
            return;
        }

        Html::printAjaxPager(__('Queue content'), $start, $total_count);

        $iterator = $DB->request([
            'FROM' => PluginFpwebhookQueue::getTable(),
            'WHERE' => [
                'subscription_id' => $this->fields['id'],
            ],
            'INNER JOIN' => [
                'glpi_plugin_fpwebhook_contents' => [
                    'ON' => [
                        'glpi_plugin_fpwebhook_contents' => 'id',
                        PluginFpwebhookQueue::getTable() => 'content_id'
                    ]
                ],
                'glpi_plugin_fpwebhook_eventtypes' => [
                    'ON' => [
                        'glpi_plugin_fpwebhook_eventtypes' => 'id',
                        'glpi_plugin_fpwebhook_contents' => 'event_type_id'
                    ]
                ],
            ],
            'ORDER' => PluginFpwebhookQueue::getTable() . '.id DESC',
            'START' => (int)$start,
            'LIMIT' => (int)$_SESSION['glpilist_limit']
        ]);

        if (count($iterator)) {
            echo '<table class="tab_cadre_fixehov">';
            $header = '<tr>';
            $header .= '<th>' . __('Event Type') . '</th>';
            $header .= '<th>' . __('Queued at') . '</th>';
            $header .= '<th>' . __('Updated at') . '</th>';
            $header .= '<th>' . __('Content size') . '</th>';
            $header .= '<th>' . __('Last response') . '</th>';
            $header .= '<th>' . __('Attempts') . '</th>';
            $header .= '</tr>';
            echo $header;

            foreach ($iterator as $data) {
                echo '<tr class="tab_bg_2">';
                echo '<td>' . $data['name'] . '</td>';
                echo '<td>' . Html::convDateTime($data['created_at']) . '</td>';
                echo '<td>' .
                    (isset($data['updated_at']) ? Html::convDateTime($data['updated_at']) : '-') .
                    '</td>';
                echo '<td>' .
                    '<a href="javascript:reloadTab(\'content_id=' .
                    $data['content_id'] . '\')">' .
                    self::convertSize(strlen($data['content']), 1) .
                    '</td>';
                echo '<td>' . ($data['response_status'] ?? '-') . '</td>';
                echo '<td>' . $data['attempts'] . '</td>';
                echo '</tr>';
            }
            echo $header;
            echo '</table>';
        } else {
            echo __('No messages for this subscription in the queue');
        }
        Html::printAjaxPager(__('Queue content'), $start, $total_count);

        echo '</div>';
    }

    /**
     * @param int $content_id ID of the content to display
     * @param string $return_link_name Name of the list of origin to display in the return link
     */
    private function showContentDetails(int $content_id, string $return_link_name): void
    {
        global $DB;

        echo '<br><div class="center">';
        echo '<p><a href="javascript:reloadTab();">' .
            $return_link_name .
            '</a></p>';

        $iterator = $DB->request([
            'FROM' => PluginFpwebhookContent::getTable(),
            'WHERE' => [
                'id' => $content_id,
            ],
        ]);

        $iterator->rewind();
        $content = $iterator->current();

        if (empty($content)) {
            echo '<table class="tab_cadre_fixe">';
            echo '<tr><th>' . __('Content not found') . '</th></tr>';
        } else {
            echo '<table class="tab_cadre_fixehov">';

            echo '<tr class="tab_bg_1">';
            echo '<td><strong>' . __('Content size') . '</strong></td>';
            echo '<td>' . self::convertSize(strlen($content['content'])) . '</td>';
            echo '</tr>';

            echo '<tr class="tab_bg_1">';
            echo '<td><strong>' . __('Content') . '</strong></td>';
            echo '<td>' . htmlspecialchars($content['content']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';

        echo '</div>';
    }

    /**
     * @param int $message_id ID of the message to display
     */
    private function showMessageDetails(int $message_id): void
    {
        global $DB;

        echo '<br><div class="center">';
        echo '<p><a href="javascript:reloadTab();">' .
            __('Sent messages list') .
            '</a></p>';

        $iterator = $DB->request([
            'FROM' => PluginFpwebhookMessage::getTable(),
            'WHERE' => [
                'id' => $message_id,
            ],
        ]);

        $iterator->rewind();
        $message = $iterator->current();

        if (empty($message)) {
            echo '<table class="tab_cadre_fixe">';
            echo '<tr><th>' . __('Message not found') . '</th></tr>';
        } else {
            echo '<table class="tab_cadre_fixehov">';

            echo '<tr class="tab_bg_1">';
            echo '<td><strong>' . __('Target URL') . '</strong></td>';
            echo '<td>' . $message['url'] . '</td>';
            echo '</tr>';

            echo '<tr class="tab_bg_1">';
            echo '<td><strong>' .
                __('Response status') .
                '</strong></td><td>' .
                $message['response_status'] .
                '</td>';
            echo '</tr>';

            echo '<tr class="tab_bg_1">';
            echo '<td><strong>' . __('Response content') . '</strong></td>';
            echo '<td>' . htmlspecialchars($message['response_content']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';

        echo '</div>';
    }

    /**
     * Check whether the ticket passes filter set by subscription
     *
     * @param array $subscription
     * @param PluginFpwebhookTicketExtracted $ticket_data
     *
     * @return bool
     */
    public static function passesFilter(
        array $subscription,
        PluginFpwebhookTicketExtracted $ticket_data
    ): bool {
        $regex_matches = true;
        $category_matches = true;

        if (!empty($subscription['filtering_regex'])) { // empty string => automatic pass
            $delimited_regex = '|' . $subscription['filtering_regex'] . '|';
            $regex_matches = preg_match($delimited_regex, $ticket_data->getTitle());
        }

        $filtering_category_id = (int)$subscription['filtering_category_id'];
        if (!empty($filtering_category_id)) { // unset category => automatic pass
            $category_matches = ($filtering_category_id === $ticket_data->getCategoryId());
        }

        return $regex_matches && $category_matches;
    }

    /**
     * Converts size in bytes to a more concise form as needed
     *
     * @param int $bytes
     * @param int $precision
     *
     * @return string
     */
    private static function convertSize(int $bytes, int $precision = 0): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, $precision) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, $precision) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Cleans the input for the form
     */
    public static function cleanInput(): void
    {
        if (isset($_POST['is_active'])) {
            $_POST['is_active'] = self::fixInputForCheckbox($_POST['is_active']);
        }
    }

    /**
     * Removes the deactivation reason once the subscription is reactivated
     *
     * @return void
     */
    public static function resetUnsubscriptionData(): void
    {
        if (!isset($_POST['is_active'])) {
            return;
        }

        if ($_POST['is_active']) {
            $_POST['unsubscribed_because'] = self::STILL_ACTIVE;
        }
    }

    /**
     * Validates whether the form input is correct
     */
    public static function validateInput(): void
    {
        if (strpos($_POST['filtering_regex'], '|') !== false) {
            $_SESSION['glpi_saved']['PluginFpwebhookSubscription'] = $_POST;
            Session::addMessageAfterRedirect(
                __('Unable to add - the pipe (|) character is disallowed in the regular expression')
            );
            Html::back();
        }
    }

    /**
     * Unsubscribes on given conditions and with the given reason
     *
     * @param array $condition
     * @param string|null $reason
     * @return bool
     */
    private static function unsubscribe(array $condition, ?string $reason = null): bool
    {
        global $DB;

        return $DB->update(
            self::getTable(),
            [
                'is_active' => false,
                'unsubscribed_at' => $_SESSION['glpi_currenttime'],
                'unsubscribed_because' => $reason
            ],
            array_merge(
                ['is_active' => 1],
                // do not unsubscribe inactive - this would overwrite the history
                $condition
            )
        );
    }
}
