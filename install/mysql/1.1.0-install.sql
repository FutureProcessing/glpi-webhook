ALTER TABLE `glpi_plugin_fpwebhook_subscriptions`
    ADD COLUMN `filtering_regex`       VARCHAR(255) DEFAULT NULL,
    ADD COLUMN `filtering_category_id` INT(11)      DEFAULT NULL;
