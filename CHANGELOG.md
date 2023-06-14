# Changelog

## [2.1.0] - 2023-06-14

Message authentication added

### Added

* An authorization token that would be sent with every `Authorization` header can now be set in plugin configuration

### Changed

* Visual upgrade of the plugin's config screen

## [2.0.0] - 2023-03-10

Ensured compatibility with GLPI 10.0.x

### Added

* Display of the automatic unsubscription reasons in the subscription detail page

### Changed

* Solutions deprecated or removed in GLPI 10.0 have been upgraded

### Fixed

* Category filter is now removed correctly at all times
* Unsubscription reason are no longer overwritten during queue cleaning

## [1.1.0] - 2022-02-07

Ticket filtering added.

### Added

* Events can now be set to trigger only for specific category of tickets
* Events can now be set to trigger only for tickets with a title matching a regular expression

## [1.0.1] - 2022-01-20

Installation process fix.

### Changed

* Installation process now pays better attention to schema versioning
* Documentation was updated to better reflect the available events

### Fixed

* Installation process now attaches the access rights in a more predictable way, preventing
  [lack of access for admin](https://github.com/FutureProcessing/glpi-webhook/issues/1).
* Installation process works now with the default MariaDB settings,
  fixing [installation issues](https://github.com/FutureProcessing/glpi-webhook/issues/3).

## [1.0.0] - 2021-12-08

The initial release.

### Added

* Webhook handling added for five events:
    * TicketCreated
    * TicketSolved
    * TicketFollowupAdded
    * TicketApprovalAdded
    * TicketApprovalResolved
