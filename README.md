# GLPI FPWebhook

## General Information

This plugin creates support for webhooks for GLPI.

### Requirements

GLPI 9.5.* or GLPI 10.0.x

### Installation instructions

Installation is similar to those of other plugins: copy to `plugins/` directory, ensure that name of the folder that
contains the plugin files is `fpwebhook`, and then install and enable from Administration/Plugins section. This will
create the database tables.

### Uninstallation instructions

Use Uninstall option in Administration/Plugins section.

**Please keep in mind that this will remove the database tables, including all subscriptions and trigger history**.

## Basic use

1. Install and enable on the plugin list
2. Visit top menu > Plugins > Webhook subscriptions; this will open the form
3. Enter the name (any non-empty), enter the URL to receive data (make sure it can receive and respond), select the
   desired type of the event, and click Add
4. You will be redirected to the list with an added position
5. Trigger the event - depending on the chosen event type, this may require creating an event, solving the event, adding
   a followup, or adding or resolving an approval
6. During the next minute-long cron cycle, the URL will receive a packet with IDs and content.
7. If the URL responded with a 2xx or 3xx status, the queue object will be removed; if the response was 4xx or 5xx, it
   will stay there with increased attempts count; if the reply was a `410 Gone`, the subscription will be deactivated.

## Database details

* `glpi_plugin_fpwebhook_eventtypes` - event type dictionary
* `glpi_plugin_fpwebhook_subscriptions` - subscriptions to hooks with names, URLs, and failure count
* `glpi_plugin_fpwebhook_contents` - content to sent, created by events
* `glpi_plugin_fpwebhook_messages` - actual attempts to send messages, including replies; this table is the main history
* `glpi_plugin_fpwebhook_queue` - the queue; this table is regularly cleaned and does not retain data

## Detailed behavior

This is an example based on the first implemented event,`TicketCreated`.

1. When a ticket is created, `TicketCreated` event is triggered
2. In reaction to the event, a content record is created and the request to send it is placed in the queue
3. Every minute, a cron task sends messages in packages of 5 (configurable), starting from the "cleanest"
   (ones with the fewest failures), followed by those that failed more times
    * If an auth token configuration variable is set, its value is sent in the message's header
4. Every result (status and reply) are archived; successes are removed from the queue, the failures stay
5. If any reply contains `410 Gone` status, the subscription is immediately disabled
6. If any queue item fails 3 times (configurable), `failures` count for its subscription is increased
7. Every 24h a cleaning is performed via a cron task
    * everything that has exceeded the failure count or belongs to inactive subscription is removed from the queue
    * Sufficiently failed subscriptions are also set to inactive.

## Filtering mechanism

Filtering is an optional functionality that allows to set two parameters: a ticket category and a title-matching regular
expression. If the category is chosen, only tickets belonging to this category will trigger the event - others will be
ignored. If the filtering regex is non-empty, only the tickets with the title matching the expression will trigger the
event.

The filters are applied together - if both filters are set, the event will trigger only if the ticket has both a
matching title and a correct category.

To remove the filtering from the subscription, simply empty the regular expression field and unset the category field.

The regular expression should be entered without delimiters. Since the delimiters used are `|`, this character is
disallowed in the regular expression.

**Note:** the regular expression is applied as case-sensitive.

## Available events

### TicketCreated

On ticket creation, returns:

* `ticket_id` - ticket ID; integer
* `subject` - ticket title/subject; string, plaintext
* `content` - ticket description; string, HTML

### TicketSolved

On ticket solution added, returns:

* `ticket_id` - ticket ID; integer
* `solution_id` - solution ID; integer
* `content` - solution description; string
* `status` - ticket status; string

### TicketFollowupAdded

On ticket follow-up added, returns:

* `ticket_id` - ticket ID; integer
* `followup_id` - follow-up ID; integer
* `content` - follow-up content; string

### TicketApprovalAdded

On ticket approval request (ticket validation) added, returns:

* `approval_id` - ticket validation ID; integer
* `ticket_id` - ticket ID; integer
* `user_id` - user ID; integer
* `content` - approval request content; string

### TicketApprovalResolved

On ticket approval request (ticket validation) added, returns:

* `approval_id` - ticket validation ID; integer
* `ticket_id` - ticket ID; integer
* `user_id` - user ID; integer
* `content` - approval request reply content; string
* `status` - approved/refused status; string

## Notes

* The plugin expects the receiver to reply with a valid HTTP status
    * anything that indicates acceptance will be fine
        * Redirect statuses do not update anything
    * 4xx and 5xx statuses will be treated as failure
    * `410 Gone` will result in an immediate deactivation of the subscription
* To add a new event:
    * create a new class that inherits from `PluginFpwebhookEventBase`
    * configure the name, activation conditions, and the output structure
    * add the event type to the `glpi_plugin_fpwebhook_eventtypes` table
    * connect it to the right hook and object type in `setup.php`
    * document the new event in this file
* There is no schema downgrade mechanism; the database does not change down
* Due to the GLPI architecture up to the version this plugin was created for (9.5.5), it is not possible to connect more
  than one method to the same hook/itemtype combination
