<?php

/*
   ------------------------------------------------------------------------
   FPWebhook - Plugin with implementation of webhooks for GLPI
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

const PLUGIN_FPWEBHOOK_VERSION = '2.1.0';
const PLUGIN_FPWEBHOOK_DIRECTORY = __DIR__;

/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_fpwebhook(): void
{
    Plugin::registerClass(PluginFpwebhookConfig::class, ['addtabon' => ['Config']]);

    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['fpwebhook'] = true;

    $PLUGIN_HOOKS['helpdesk_menu_entry']['fpwebhook'] = true;

    $PLUGIN_HOOKS['menu_toadd']['fpwebhook'] = [
        'plugins' => ['Webhooks' => PluginFpwebhookSubscription::class],
    ];

    $PLUGIN_HOOKS['item_add']['fpwebhook'] = [
        'Ticket' => [PluginFpwebhookTicketCreated::class, 'eventHandler'],
        'ITILFollowup' => [PluginFpwebhookTicketFollowupAdded::class, 'eventHandler'],
        'ITILSolution' => [PluginFpwebhookTicketSolved::class, 'eventHandler'],
        'TicketValidation' => [PluginFpwebhookTicketApprovalAdded::class, 'eventHandler'],
    ];

    $PLUGIN_HOOKS['item_update']['fpwebhook'] = [
        'TicketValidation' => [PluginFpwebhookTicketApprovalResolved::class, 'eventHandler'],
    ];

    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['fpwebhook'] = 'front/config.form.php';
    }
}

/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_fpwebhook(): array
{
    return [
        'name' => 'FP Webhook',
        'version' => PLUGIN_FPWEBHOOK_VERSION,
        'author' => '<a href="https://www.future-processing.com">Future Processing</a>',
        'license' => 'AGPLv3+',
        'homepage' => 'https://www.future-processing.com',
        'requirements' => [
            'glpi' => [
                'min' => '9.5',
                'max' => '10.1',
            ],
        ],
    ];
}

/**
 * Check pre-requisites before install
 * OPTIONAL, but recommended
 *
 * @return bool
 */
function plugin_webhook_check_prerequisites(): bool
{
    return true; // Unnecessary for the moment, versions are checked by GLPI
}

/**
 * Check configuration
 *
 * @param  bool  $verbose  Whether to display message on failure. Defaults to false
 *
 * @return bool
 */
function plugin_fpwebhook_check_config($verbose = false): bool
{
    return true; // The configuration has defaults, there is no need to force a check
}
