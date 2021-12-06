DROP TABLE IF EXISTS `glpi_plugin_fpwebhook_eventtypes`;
CREATE TABLE `glpi_plugin_fpwebhook_eventtypes`
(
    `id`          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        CHAR(24)        NOT NULL,
    `description` VARCHAR(80)     NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

INSERT INTO `glpi_plugin_fpwebhook_eventtypes` (`name`, `description`)
VALUES ('TicketCreated', 'New Ticket Created'),
       ('TicketSolved', 'Ticket Solved'),
       ('TicketFollowupAdded', 'New Followup Created'),
       ('TicketApprovalAdded', 'New Ticket Approval Created'),
       ('TicketApprovalResolved', 'Ticket Approval Resolved');

DROP TABLE IF EXISTS `glpi_plugin_fpwebhook_subscriptions`;
CREATE TABLE `glpi_plugin_fpwebhook_subscriptions`
(
    `id`                   BIGINT(20) UNSIGNED   NOT NULL AUTO_INCREMENT,
    `name`                 VARCHAR(80)       NOT NULL,
    `url`                  VARCHAR(255)      NOT NULL,
    `event_type_id`        BIGINT(20) UNSIGNED   NOT NULL,
    `created_at`           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_active`            TINYINT UNSIGNED  NOT NULL DEFAULT 1,
    `is_deleted`           TINYINT UNSIGNED  NOT NULL DEFAULT 0,
    `unsubscribed_at`      TIMESTAMP                  DEFAULT NULL,
    `unsubscribed_because` TINYINT UNSIGNED           DEFAULT NULL,
    `failures`             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    FOREIGN KEY subscriptions_event_type_index (event_type_id) REFERENCES glpi_plugin_fpwebhook_eventtypes (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `glpi_plugin_fpwebhook_contents`;
CREATE TABLE `glpi_plugin_fpwebhook_contents`
(
    `id`            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_type_id` BIGINT(20) UNSIGNED NOT NULL,
    `content`       TEXT            NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY trigger_history_event_type_index (event_type_id) REFERENCES glpi_plugin_fpwebhook_eventtypes (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `glpi_plugin_fpwebhook_messages`;
CREATE TABLE `glpi_plugin_fpwebhook_messages`
(
    `id`               BIGINT(20) UNSIGNED  NOT NULL AUTO_INCREMENT,
    `subscription_id`  BIGINT(20) UNSIGNED  NOT NULL,
    `content_id`       BIGINT(20) UNSIGNED  NOT NULL,
    `url`              VARCHAR(255)     NOT NULL DEFAULT '', # this can change in source; this preserves the old one
    `called_at`        TIMESTAMP        NOT NULL DEFAULT NOW(),
    `response_status`  SMALLINT UNSIGNED         DEFAULT NULL,
    `response_content` TEXT                      DEFAULT NULL,
    `attempt`          TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY message_history_webhook_index (subscription_id) REFERENCES glpi_plugin_fpwebhook_subscriptions (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY message_history_trigger_index (content_id) REFERENCES glpi_plugin_fpwebhook_contents (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `glpi_plugin_fpwebhook_queue`;
CREATE TABLE `glpi_plugin_fpwebhook_queue` # this is non-minimal for speed; data MUST NOT remain here after sending
(
    `id`               BIGINT(20) UNSIGNED  NOT NULL AUTO_INCREMENT,
    `subscription_id`  BIGINT(20) UNSIGNED  NOT NULL,
    `content_id`       BIGINT(20) UNSIGNED  NOT NULL,
    `created_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP                 DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `response_status`  INT UNSIGNED              DEFAULT NULL, # most recent response for debugging
    `response_content` TEXT                      DEFAULT NULL, # most recent response for debugging
    `attempts`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    FOREIGN KEY queue_webhook_index (subscription_id) REFERENCES glpi_plugin_fpwebhook_subscriptions (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY queue_trigger_index (content_id) REFERENCES glpi_plugin_fpwebhook_contents (id) ON UPDATE CASCADE ON DELETE RESTRICT

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
