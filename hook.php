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
 * Sends log message.
 *
 * @param  string  $message
 * @return void
 */
function log_fpwebhook_error(string $message): void
{
    $migration = new Migration(PLUGIN_FPWEBHOOK_VERSION);
    $migration->displayMessage($message);
}

/**
 * Plugin install process
 *
 * @return bool
 */
function plugin_fpwebhook_install(): bool
{
    $webhook = new PluginFpwebhookInstaller();

    $schema_version = $webhook->detectSchemaVersion();

    if ($schema_version === null) { // not installed at all
        try {
            $webhook->applySchema('1.0.0');
            $webhook->addViews();
            $webhook->addAccessRights();
            $webhook->registerCronTasks();
        } catch (Throwable $e) {
            log_fpwebhook_error($e->getMessage());
            exit(1);
        }
    }

    if (version_compare($schema_version, '1.0.1') < 0) { // 1.0.0
        // reset is necessary due to previous installation sequence causing issues
        $webhook->removeAccessRights();
        $webhook->addAccessRights();
    }

    if (version_compare($schema_version, '1.1.0') < 0) { // 1.0.1 or lower
        $webhook->applySchema('1.1.0'); // nothing but the schema changed
    }

    if (version_compare($schema_version, '2.0.0') < 0) { // 1.1.0 or lower
        $webhook->applySchema('2.0.0'); // nothing but the schema changed
    }

    return true;
}

/**
 * Plugin uninstall process
 *
 * @return bool
 */
function plugin_fpwebhook_uninstall(): bool
{
    $webhook = new PluginFpwebhookInstaller();

    if ($webhook->isInstalled()) {
        try {
            $webhook->unregisterCronTasks();
            $webhook->removeAccessRights();
            $webhook->removeViews();
            $webhook->purgeSchema();
        } catch (Throwable $e) {
            log_fpwebhook_error($e->getMessage());
            exit(1);
        }
    }

    return true;
}
