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
 * Installer class
 */
class PluginFpwebhookInstaller
{
    private const DISPLAY_PREFERENCES_IDS = [2, 3, 4, 5];
    private const PROFILES_HAVING_ACCESS = ['Super-Admin', 'Admin'];

    /**
     * Verifies if the plugin is already installed
     *
     * @return bool Returns true if plugin is already installed
     */
    public function isInstalled(): bool
    {
        global $DB;

        return
            $DB->tableExists('glpi_plugin_fpwebhook_messages', false) &&
            $DB->tableExists('glpi_plugin_fpwebhook_contents', false) &&
            $DB->tableExists('glpi_plugin_fpwebhook_subscriptions', false) &&
            $DB->tableExists('glpi_plugin_fpwebhook_eventtypes', false) &&
            $DB->tableExists('glpi_plugin_fpwebhook_queue', false);
    }

    /**
     * Detects a version for the purposes of the database upgrade
     *
     * NOTE: this is not a precise tool; is the upgrade had no schema nor configuration changes,
     * it will not be detectable via this method. Do not rely on it for anything but the upgrade
     * purposes.
     *
     * NOTE on expanding: add future version checks before the existing ones to reduce number of
     * necessary checks
     *
     * @return string|null
     */
    public function detectSchemaVersion(): ?string
    {
        global $DB;

        // version 2.1.0 does not change the schema

        if (
            $DB->tableExists('glpi_plugin_fpwebhook_subscriptions') &&
            $DB->fieldExists('glpi_plugin_fpwebhook_subscriptions', 'filtering_category_id')
        ) {
            $filtering_category_id_type = $DB->getField(
                'glpi_plugin_fpwebhook_subscriptions',
                'filtering_category_id'
            );
            if ($filtering_category_id_type['Type'] === 'bigint(20) unsigned') {
                return '2.0.0';
            }
        }

        if (
            $DB->tableExists('glpi_plugin_fpwebhook_subscriptions') &&
            $DB->fieldExists('glpi_plugin_fpwebhook_subscriptions', 'filtering_regex') &&
            $DB->fieldExists('glpi_plugin_fpwebhook_subscriptions', 'filtering_category_id')
        ) {
            return '1.1.0';
        }

        // version 1.0.1 does not change the schema

        if ($this->isInstalled()) {
            return '1.0.0';
        }

        return null;
    }

    /**
     * Initiates plugin tables
     *
     * @param string $version Version of database to update to
     * @return void
     *
     * @throws RuntimeException in case schema installation fails
     */
    public function applySchema(string $version): void
    {
        global $DB;

        $schemaName = PLUGIN_FPWEBHOOK_DIRECTORY . '/install/mysql/' . $version . '-install.sql';

        if (!$DB->runFile($schemaName)) {
            throw new RuntimeException(
                'Error occurred during FP Webhook setup - unable to run schema for ' . $version . '.'
            );
        }
    }

    /**
     * Removes plugin tables
     *
     * @return void
     *
     * @throws RuntimeException in case schema de-installation fails
     */
    public function purgeSchema(): void
    {
        global $DB;

        if (!$DB->runFile(PLUGIN_FPWEBHOOK_DIRECTORY . '/install/mysql/uninstall.sql')) {
            throw new RuntimeException(
                'Error occurred while removing Webhook - unable to purge database'
            );
        }
    }

    /**
     * Adds base view preferences for subscription list
     *
     * @return void
     */
    public function addViews(): void
    {
        $rank = 1;

        foreach (self::DISPLAY_PREFERENCES_IDS as $id) {
            $dp = new DisplayPreference();

            $dp->fields['itemtype'] = 'PluginFpwebhookSubscription';
            $dp->fields['num'] = $id;
            $dp->fields['rank'] = $rank++;

            if (!$dp->addToDB()) {
                throw new RuntimeException('Error occurred while adding access rights');
            };
        }
    }

    /**
     * Removes base view preferences for subscription list
     *
     * @return void
     * @throws GlpitestSQLError
     */
    public function removeViews(): void
    {
        global $DB;
        $DB->query(
            "DELETE FROM `glpi_displaypreferences` WHERE `itemtype` = 'PluginFpwebhookSubscription'"
        );
    }

    /**
     * Adds access rights to subscriptions
     *
     * @return void
     */
    public function addAccessRights(): void
    {
        ProfileRight::addProfileRights(['fpwebhooks']);

        $profile_ids = $this->getProfileIDsByName(self::PROFILES_HAVING_ACCESS);

        foreach ($profile_ids as $profile_id) {
            ProfileRight::updateProfileRights($profile_id, ['fpwebhooks' => 31]);
        }
    }

    /**
     * Retrieves user profiles ID by name
     *
     * @param string[] $profiles Profile names
     * @return int[] Profile IDs
     */
    private function getProfileIDsByName(array $profiles): array
    {
        global $DB;
        $profile_objects = $DB->request([
            'SELECT' => ['id'],
            'FROM' => Profile::getTable(),
            'WHERE' => ['name' => $profiles]
        ]);

        $profile_ids = [];
        foreach ($profile_objects as $profile_object) {
            $profile_ids[] = $profile_object['id'];
        }

        return $profile_ids;
    }

    /**
     * Removes access rights to subscriptions
     *
     * @return void
     */
    public function removeAccessRights(): void
    {
        ProfileRight::deleteProfileRights(['fpwebhooks']);
    }

    /**
     * Registers response sending with the GLPI cron
     *
     * @return void
     *
     * @throws RuntimeException in case cron task setup fails.
     */
    public function registerCronTasks(): void
    {
        $taskRegisterStatus =
            CronTask::register(
                'PluginFpwebhookQueue',
                'SendResponses',
                MINUTE_TIMESTAMP,
                [
                    'comment' => 'Dispatch messages from webhook message queue',
                    'mode' => CronTask::MODE_EXTERNAL,
                ]
            ) &&
            CronTask::register(
                'PluginFpwebhookQueue',
                'CleanQueue',
                DAY_TIMESTAMP,
                [
                    'comment' => 'Clean failed messages from webhook message queue',
                    'mode' => CronTask::MODE_EXTERNAL,
                ]
            );

        if (!$taskRegisterStatus) {
            throw new RuntimeException('Error occurred during FP Webhook cron task setup');
        }
    }

    /**
     * Unregisters automatic actions
     *
     * @return void
     */
    public function unregisterCronTasks(): void
    {
        CronTask::unregister('PluginFpwebhookQueue');
    }
}
