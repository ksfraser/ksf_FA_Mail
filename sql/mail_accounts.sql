-- The literal `0_` is the company prefix: FA's db_import() replaces it with
-- the real TB_PREF of the company being installed.  Without it the table was
-- created unprefixed, while the code read TB_PREF . 'ksf_mail_accounts' —
-- a name that never existed.
CREATE TABLE IF NOT EXISTS `0_ksf_mail_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `owner_type` VARCHAR(32) NOT NULL DEFAULT 'user',
    `owner_id` VARCHAR(60) NOT NULL,
    `is_default` TINYINT(1) DEFAULT 0,
    `local_part` VARCHAR(255) NOT NULL DEFAULT '',
    `domain` VARCHAR(255) NOT NULL DEFAULT '',
    `from_name` VARCHAR(255) DEFAULT '',
    `smtp_host` VARCHAR(255) DEFAULT '',
    `smtp_port` INT DEFAULT 587,
    `smtp_secure` VARCHAR(10) DEFAULT 'tls',
    `smtp_username` VARCHAR(255) DEFAULT '',
    `smtp_password` VARCHAR(255) DEFAULT '',
    `imap_host` VARCHAR(255) DEFAULT '',
    `imap_port` INT DEFAULT 993,
    `imap_secure` VARCHAR(10) DEFAULT 'ssl',
    `imap_username` VARCHAR(255) DEFAULT '',
    `imap_password` VARCHAR(255) DEFAULT '',
    `bcc_email` VARCHAR(255) DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_owner` (`owner_type`, `owner_id`),
    KEY `idx_domain` (`domain`),
    KEY `idx_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
