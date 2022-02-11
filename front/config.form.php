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
   along with the FPWebhook source code. If not, see <http://www.gnu.org/licenses/>.

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

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

$config = new PluginFpwebhookConfig();

if (isset($_POST['update'])) {
    Config::setConfigurationValues(PluginFpwebhookConfig::$context, [
        'max_messages_per_tick' =>
            $_POST['max_messages_per_tick']
            ?? PluginFpwebhookConfig::$default_max_messages_per_tick,
        'max_attempts_per_message' =>
            $_POST['max_attempts_per_message']
            ?? PluginFpwebhookConfig::$default_max_attempts_per_message,
        'max_allowed_failures' =>
            $_POST['max_allowed_failures']
            ?? PluginFpwebhookConfig::$default_max_allowed_failures,
    ]);
    HTML::back();
}

HTML::header('Configuration of FP Webhook plugin');

$config->showFormDisplay();

HTML::footer();
