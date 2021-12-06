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
 * Template class for all webhook event types
 */
abstract class PluginFpwebhookEventBase
{
   /**
    * Trigger handler
    *
    * @param CommonDBTM $item
    *
    * @return boolean
    *
    * @throws Exception
    */
   public static function eventHandler(CommonDBTM $item): bool
   {
      if (!static::isObjectTypeCorrect($item)) {
         return true;
      }

      $message = static::makeMessage($item);

      $event_type_id = self::getEventTypeId();

      $content_id = static::createContentRecord($event_type_id, $message);

      $subscriptions_iterator = static::getSubscriptionsIterator($event_type_id);

      while ($subscription = $subscriptions_iterator->next()) {
         PluginFpwebhookQueue::queueMessage($subscription['id'], $content_id);
      }

      return true;
   }

   /**
    * Provide event type ID
    *
    * @return int
    *
    * @throws Exception
    */
   protected static function getEventTypeId(): int
   {
      return PluginFpwebhookEventType::getEventTypeIdByName(static::getEventType());
   }

   /**
    * Create the record with the message for future sending
    *
    * @param int $event_type_id
    * @param array $content
    *
    * @return int
    */
   protected static function createContentRecord(int $event_type_id, array $content): int
   {
      global $DB;
      $DB->insert(
         PluginFpwebhookContent::getTable(),
         [
            'event_type_id' => $event_type_id,
            'content' => $DB->escape(json_encode($content)),
         ]
      );

      return $DB->insertId();
   }

   /**
    * Get all active subscriptions
    *
    * @param int $event_type_id
    *
    * @return DBmysqlIterator
    */
   protected static function getSubscriptionsIterator(int $event_type_id): DBmysqlIterator
   {
      global $DB;

      return $DB->request(
         [
            'FROM' => PluginFpwebhookSubscription::getTable(),
            'WHERE' => [
               'event_type_id' => $event_type_id,
               'is_active' => 1,
               'is_deleted' => 0,
               'unsubscribed_at' => null,
            ],
         ]
      );
   }

   /**
    * Provide event type string
    *
    * @return string
    */
   abstract public static function getEventType(): string;

   /**
    * Is this the right object to trigger a reaction?
    *
    * @param $item
    *
    * @return bool
    */
   abstract protected static function isObjectTypeCorrect($item): bool;

   /**
    * Prepares message array
    *
    * @param CommonDBTM $item
    *
    * @return array
    */
   abstract protected static function makeMessage(CommonDBTM $item): array;
}
