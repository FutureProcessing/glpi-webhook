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
 * Configuration handling class
 */
class PluginFpwebhookConfig extends CommonDBTM
{
    public static string $context = 'plugin:Fpwebhook';

    public static int $default_max_messages_per_tick = 10;
    public static int $default_max_attempts_per_message = 3;
    public static int $default_max_allowed_failures = 100;
    public static ?string $message_auth_token = null;

    protected static $notable = true;

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if (!$withtemplate) {
            if ($item->getType() == 'Config') {
                return __('FP Webhook plugin');
            }
        }
        return '';
    }

    public static function configUpdate($input)
    {
        $input['configuration'] = 1 - $input['configuration'];
        return $input;
    }

    public function showFormDisplay(): bool
    {
        if (!Session::haveRight('config', UPDATE)) {
            return false;
        }

        $my_config = Config::getConfigurationValues(self::$context);

        echo '<form name="form" action="'
            . Toolbox::getItemTypeFormURL('Config')
            . '" method="post">';
        echo '<div class="center" id="tabsbody">';
        echo '<table class="tab_cadre_fixe" style="max-width: 768px">';

        echo '<tr><th colspan="4">' . __('Queue settings') . '</th></tr>';
        echo '<td><b>' . __('Maximum messages per one sending:') . '</b></td>';
        echo '<td colspan="3">';
        echo '<input type="hidden" name="config_class" value="' . __CLASS__ . '">';
        echo '<input type="hidden" name="config_context" value="' . self::$context . '">';
        echo '<input type="text" name="max_messages_per_tick" value="'
            . ($my_config['max_messages_per_tick'] ?? self::$default_max_messages_per_tick)
            . '" style="width: 25%; float: right; text-align: right;">';
        echo '</td></tr>';

        echo '<tr><td>';
        echo '<b>Maximum number of tries per message:</b></td><td>';
        echo '<input type="text" name="max_attempts_per_message" value="'
            . ($my_config['max_attempts_per_message'] ?? self::$default_max_attempts_per_message)
            . '" style="width: 25%; float: right; text-align: right;">';
        echo '</td></tr>';

        echo '<tr><th colspan="4">' . __('Subscription settings') . '</th></tr>';
        echo '<tr><td>';
        echo '<b>Maximum allowed failures before unsubscription:</b></td><td>';
        echo '<input type="text" name="max_allowed_failures" value="'
            . ($my_config['max_allowed_failures'] ?? self::$default_max_allowed_failures)
            . '" style="width: 25%; float: right; text-align: right;">';
        echo '</td></tr>';

        echo '<tr><th colspan="4">' . __('Security settings') . '</th></tr>';
        echo '<tr><td>';
        echo '<b>Message authorization token:</b></td><td>';
        echo '<input type="text" name="message_auth_token" value="'
            . ($my_config['message_auth_token'] ?? self::$message_auth_token)
            . '" style="width: 100%; float: right; text-align: right;">';
        echo '</td></tr>';

        echo '<tr class="tab_bg_2">';
        echo '<td colspan="4" class="center">';
        echo '<input type="submit" name="update" class="submit" value="'
            . _sx('button', 'Save')
            . '">';
        echo '</td></tr>';

        echo '</table></div>';

        Html::closeForm();

        return true;
    }

    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ) {
        if ($item->getType() === 'Config') {
            $config = new self();
            $config->showFormDisplay();
        }
    }
}
