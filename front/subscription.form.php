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

include('../../../inc/includes.php');

use Glpi\Event;

Session::checkRight('fpwebhooks', READ);

$plugin = new Plugin();
if (!$plugin->isInstalled('fpwebhook') || !$plugin->isActivated('fpwebhook')) {
    Html::displayNotFoundError();
}

PluginFpwebhookSubscription::makeHeader();

$object = new PluginFpwebhookSubscription();
if (isset($_POST['add'])) {
    $object->check(-1, CREATE, $_POST);

    PluginFpwebhookSubscription::cleanInput();
    PluginFpwebhookSubscription::validateInput();

    if (empty($_POST['event_type_id'])) {
        $_SESSION['glpi_saved']['PluginFpwebhookSubscription'] = $_POST;
        Session::addMessageAfterRedirect(__('Unable to add - please choose event type'));
        Html::back();
    }

    if ($newID = $object->add($_POST)) {
        Event::log(
            $newID,
            'webhooks',
            4,
            'plugins',
            sprintf(__('%1$s added the subscription %2$s'), $_SESSION['glpiname'], $_POST['name'])
        );
        unset($_SESSION['glpi_saved']['PluginFpwebhookSubscription']);
    } else {
        Session::addMessageAfterRedirect('Unable to add - unknown error');
    }

    $object->redirectToList();
} elseif (isset($_POST['delete'])) {
    $object->check($_POST['id'], DELETE);

    if ($object->delete($_POST)) {
        Event::log(
            $_POST['id'],
            'webhooks',
            4,
            'plugins',
            sprintf(__('%s deletes a subscription'), $_SESSION['glpiname'])
        );
        $object->redirectToList();
    }

    $object->redirectToList();
} elseif (isset($_POST['restore'])) {
    $object->check($_POST['id'], DELETE);

    if ($object->restore($_POST)) {
        Event::log(
            $_POST['id'],
            'webhooks',
            4,
            'plugins',
            sprintf(__('%s restores a subscription'), $_SESSION['glpiname'])
        );
    }

    $object->redirectToList();
} elseif (isset($_POST['update'])) {
    $object->check($_POST['id'], UPDATE);

    PluginFpwebhookSubscription::cleanInput();
    PluginFpwebhookSubscription::validateInput();
    PluginFpwebhookSubscription::resetUnsubscriptionData();

    if ($object->update($_POST)) {
        Event::log(
            $_POST['id'],
            'webhooks',
            4,
            'plugins',
            sprintf(__('%s updates a subscription'), $_SESSION['glpiname'])
        );
    }

    $object->redirectToList();
} else {
    $saved_name = Session::getSavedOption(
        'PluginFpwebhookSubscription',
        'name',
        null
    );
    $saved_url = Session::getSavedOption(
        'PluginFpwebhookSubscription',
        'url',
        null
    );
    $saved_event = Session::getSavedOption(
        'PluginFpwebhookSubscription',
        'event_type_id',
        null
    );
    $saved_regex = Session::getSavedOption(
        'PluginFpwebhookSubscription',
        'filtering_regex',
        null
    );
    $saved_category_id = Session::getSavedOption(
        'PluginFpwebhookSubscription',
        'filtering_category_id',
        null
    );

    $object->display([
        'id' => $_GET['id'] ?? null,
        'name' => $saved_name,
        'url' => $saved_url,
        'event_type_id' => $saved_event,
        'filtering_regex' => $saved_regex,
        'filtering_category_id' => $saved_category_id,
    ]);
}

Html::footer();
