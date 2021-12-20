# Changelog

## [1.0.1] - UNRELEASED

Installation fix.

### Changed

* Installation process now pays better attention to schema versioning
* Documentation was updated to better reflect the available events

### Fixed

* Installation process now attaches the access rights in a more predictable way - a possible fix for #1

## [1.0.0] - 2021-12-08

The initial release.

### Added

* Webhook handling added for five events:
    * TicketCreated
    * TicketSolved
    * TicketFollowupAdded
    * TicketApprovalAdded
    * TicketApprovalResolved
