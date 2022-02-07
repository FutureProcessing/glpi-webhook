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
 * TicketApprovalAdded event handler
 */
class PluginFpwebhookTicketApprovalAdded extends PluginFpwebhookEventBase
{
   public static function getEventType(): string
   {
      return 'TicketApprovalAdded';
   }

   protected static function getTicketId(CommonDBTM $item): int
   {
      return $item->fields['tickets_id'];
   }

   protected static function isObjectTypeCorrect($item): bool
   {
      if ($item::getType() === TicketValidation::getType()) {
         return true;
      }

      return false;
   }

   protected static function makeMessage(CommonDBTM $item): array
   {
      return [
         'approval_id' => $item->fields['id'],
         'ticket_id' => self::getTicketId($item),
         'user_id' => $item->fields['users_id'],
         'content' => $item->input['comment_submission'],
      ];
   }
}
