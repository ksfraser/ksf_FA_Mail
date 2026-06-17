CREATE TABLE IF NOT EXISTS `fa_preference_values` (
    `module_name` VARCHAR(60) NOT NULL,
    `user_id` VARCHAR(60) NOT NULL,
    `pref_key` VARCHAR(100) NOT NULL,
    `pref_value` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`module_name`, `user_id`, `pref_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
