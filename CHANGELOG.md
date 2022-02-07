# Changelog

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
