
DROP TABLE IF EXISTS `account_group_connections`;
DROP TABLE IF EXISTS `account_groups`;
DROP TABLE IF EXISTS `account_known_logins`;
DROP TABLE IF EXISTS `account_auth_tokens`;
DROP TABLE IF EXISTS `account_logins`;
DROP TABLE IF EXISTS `account_auth_remote`;
DROP TABLE IF EXISTS `account_auth_remote_providers`;
DROP TABLE IF EXISTS `account_auth_local`;
DROP TABLE IF EXISTS `accounts`;
DROP TABLE IF EXISTS `account_disabled`;
DROP TABLE IF EXISTS `account_active`;
DROP TABLE IF EXISTS `account_browser_os`;


--
-- Table structure for table `account_browser_os`
--
CREATE TABLE `account_browser_os` (
	`id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
	`os` varchar(20),
	`browser` varchar(20),
	PRIMARY KEY (`id`),
	UNIQUE KEY `account_browser_os_unique_1` (`os`,`browser`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


--
-- Table structure for table `account_active`
--
CREATE TABLE `account_active` (
	`account_id` int(10) NOT NULL,
	`datetime` datetime NOT NULL,
	PRIMARY KEY (`account_id`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

--
-- Table structure for table `account_disabled`
--
CREATE TABLE `account_disabled` (
	`id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
	`reason` varchar(45) NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


--
-- Table structure for table `accounts`
--
CREATE TABLE `accounts` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`first_name` varchar(45) DEFAULT NULL,
	`middle_name` varchar(45) DEFAULT NULL,
	`last_name` varchar(45) DEFAULT NULL,
	`screen_name` varchar(45) DEFAULT NULL,
	`email` varchar(255) DEFAULT NULL,
	`created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`first_signin` datetime,
	`disabled` datetime DEFAULT NULL,
	`disabled_reason` smallint(5) unsigned DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `fk_auth_accounts_disabled_reason_idx` (`disabled_reason`),
	CONSTRAINT `fk_auth_accounts_disabled_reason_id` FOREIGN KEY (`disabled_reason`) REFERENCES `account_disabled` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


--
-- Table structure for table `account_auth_local`
--
CREATE TABLE `account_auth_local` (
	`id` varchar(255) NOT NULL,
	`account_id` int(10) unsigned NOT NULL,
	`password` char(60) NOT NULL,
	`last_used` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`verification` char(40) DEFAULT NULL,
	`reset_code` char(40) DEFAULT NULL,
	`reset_datetime` datetime DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `fk_auth_local_account_id_idx` (`account_id`),
	CONSTRAINT `fk_auth_local_account_id` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--
-- Table structure for table `account_auth_remote_providers`
--
CREATE TABLE `account_auth_remote_providers` (
	`id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
	`name` varchar(60) NOT NULL,
	`type` ENUM('oauth', 'ldap', 'pam', 'custom') NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


--
-- Table structure for table `account_auth_remote`
--
CREATE TABLE `account_auth_remote` (
	`id` varchar(255) NOT NULL,
	`account_id` int(10) unsigned NOT NULL,
	`provider_id` tinyint(3) unsigned NOT NULL,
	`data` text,
	`last_used` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `fk_account_auth_remote_account_id_idx` (`account_id`),
	CONSTRAINT `fk_account_auth_remote_account_id` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	KEY `fk_account_auth_remote_provider_idx` (`provider_id`),
	CONSTRAINT `fk_account_auth_remote_provider` FOREIGN KEY (`provider_id`) REFERENCES `account_auth_remote_providers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--
-- Table structure for table `account_logins`
--
CREATE TABLE `account_logins` (
	`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	`user_ip` varchar(46) NOT NULL,
	`account_id` int(10) unsigned NOT NULL,
	`datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`status` ENUM('ok', 'passfail', 'notverified', 'disabled') NOT NULL,
	`remote_provider_id` tinyint(3) unsigned DEFAULT NULL,
	`browser_os` mediumint(8) unsigned NOT NULL,
	PRIMARY KEY (`id`),
	KEY `fk_account_logins_account_id_idx` (`account_id`),
	CONSTRAINT `fk_account_logins_account_id` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	KEY `fk_account_logins_remote_provider_id_idx` (`remote_provider_id`),
	CONSTRAINT `fk_account_logins_remote_provider_id` FOREIGN KEY (`remote_provider_id`) REFERENCES `account_auth_remote_providers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	KEY `fk_account_logins_browser_os_idx` (`browser_os`),
	CONSTRAINT `fk_account_logins_browser_os` FOREIGN KEY (`browser_os`) REFERENCES `account_browser_os` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


--
-- Table structure for table `account_auth_tokens`
--
CREATE TABLE `account_auth_tokens` (
	`id` char(12) NOT NULL,
	`hash` char(64) NOT NULL,
	`account_id` int(10) unsigned NOT NULL,
	`created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`last_used` datetime,
	`browser_os` mediumint(8) unsigned NOT NULL,
	PRIMARY KEY (`id`),
	KEY `fk_account_auth_tokens_account_id_idx` (`account_id`),
	CONSTRAINT `fk_account_auth_tokens_account_id` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	KEY `fk_account_auth_tokens_browser_os_idx` (`browser_os`),
	CONSTRAINT `fk_account_auth_tokens_browser_os` FOREIGN KEY (`browser_os`) REFERENCES `account_browser_os` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


--
-- Table structure for table `account_known_logins`
--
CREATE TABLE `account_known_logins` (
	`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	`account_id` int(10) unsigned NOT NULL,
	`last_used` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`confirmed` BOOLEAN NOT NULL,
	`browser_os` mediumint(8) unsigned NOT NULL,
	`token` char(64) NOT NULL,
	PRIMARY KEY (`id`),
	KEY `fk_account_known_logins_id_idx` (`account_id`),
	CONSTRAINT `fk_account_known_logins_id` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	KEY `fk_account_known_logins_browser_os_idx` (`browser_os`),
	CONSTRAINT `fk_account_known_logins_browser_os` FOREIGN KEY (`browser_os`) REFERENCES `account_browser_os` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


--
-- Table structure for table `account_groups`
--
CREATE TABLE `account_groups` (
	`id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
	`name` varchar(45) DEFAULT NULL,
	`description` varchar(200) DEFAULT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `account_groups_unique_1` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1000001 DEFAULT CHARSET=utf8;


INSERT INTO `account_groups` VALUES
(1,'root','Full access to everything.'),
(4,'admin','Administrator. Can log in to admin, and edit almost all stuff.'),
(99,'usrqst','User has requested access to admin system.'),
(100,'users','Permission to log into the admin system, and access to the parts of it that does not requre extened access.');


--
-- Table structure for table `account_group_connections`
--
CREATE TABLE `account_group_connections` (
	`account_id` int(10) unsigned NOT NULL,
	`group_id` mediumint(8) unsigned NOT NULL,
	UNIQUE KEY `account_group_connections_unique_1` (`account_id`,`group_id`),
	KEY `fk_account_group_connections_account_id_idx` (`account_id`),
	CONSTRAINT `fk_account_group_connections_account_id` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	KEY `fk_account_group_connections_group_id_idx` (`group_id`),
	CONSTRAINT `fk_account_group_connections_group_id` FOREIGN KEY (`group_id`) REFERENCES `account_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--
-- Table definitions complete.
--



/*
\b  Backspace (ascii code 08)
\f  Form feed (ascii code 0C)
\n  New line
\r  Carriage return
\t  Tab
\"  Double quote
\\  Backslash
\/  Solidus
*/

--
-- Function definition for function `G_JSON_STRING`
--
DROP FUNCTION IF EXISTS `G_JSON_STRING`;
DELIMITER $$
CREATE FUNCTION `G_JSON_STRING` (value TEXT) RETURNS TEXT
BEGIN
	DECLARE result TEXT;
	SELECT REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(value, "\\", '\\\\'), '\/', '\\/'), '"', '\\"'), "\n", '\\n'), "\r", '\\r'), "\t", '\\t'), "", '\\f'), "\b", '\\b') INTO result;
RETURN result;
END$$
DELIMITER ;


--
-- View structure for view `account_info_view`
--
DROP VIEW IF EXISTS `account_info_view`;
CREATE VIEW `account_info_view` AS
SELECT
	`accounts`.`id`,
	`accounts`.`first_name`,
	`accounts`.`middle_name`,
	`accounts`.`last_name`,
	IF (isnull(`accounts`.`middle_name`), CONCAT(`accounts`.`first_name`, ' ', `accounts`.`last_name`), CONCAT(`accounts`.`first_name`, ' ', `accounts`.`middle_name`, ' ', `accounts`.`last_name`)) as `full_name`,
	IF (isnull(`accounts`.`screen_name`), `accounts`.`first_name`, `accounts`.`screen_name`) as `screen_name`,
	`accounts`.`email`,
	`accounts`.`created`,
	`accounts`.`first_signin`,
	`accounts`.`disabled`,
	`accounts`.`disabled_reason`,
	`account_auth_local`.`id` AS `local`,
	`account_auth_remote`.`id` AS `remote`,
	`account_group_connections`.`group_id` AS `groups`
FROM
	`accounts`
LEFT JOIN
	(SELECT
		CONCAT('[{', GROUP_CONCAT('"id":"', G_JSON_STRING(`account_auth_local`.`id`), '","verification":', IF(`account_auth_local`.`verification` IS NOT NULL, CONCAT('"', G_JSON_STRING(`account_auth_local`.`verification`), '"'), 'null') SEPARATOR ','), '}]') AS `id`,
		`account_auth_local`.`account_id` AS `account_id`
	FROM
		`account_auth_local`
	GROUP BY
		`account_auth_local`.`account_id`
	) `account_auth_local` ON `account_auth_local`.`account_id` = `accounts`.`id`
LEFT JOIN
	(SELECT
		CONCAT('[{', GROUP_CONCAT('"id":"', G_JSON_STRING(`account_auth_remote`.`id`), '","provider_id":', `account_auth_remote`.`provider_id` SEPARATOR ','), '}]') AS `id`,
		`account_auth_remote`.`account_id` AS `account_id`
	FROM
		`account_auth_remote`
	GROUP BY
		`account_auth_remote`.`account_id`
	) `account_auth_remote` ON `account_auth_remote`.`account_id` = `accounts`.`id`
LEFT JOIN
	(SELECT
		CONCAT('[', GROUP_CONCAT(`account_group_connections`.`group_id`
	ORDER BY
		`account_group_connections`.`group_id` ASC SEPARATOR ','), ']') AS `group_id`,
		`account_group_connections`.`account_id` AS `account_id`
	FROM
		`account_group_connections`
	GROUP BY
		`account_group_connections`.`account_id`
	) `account_group_connections` ON `account_group_connections`.`account_id` = `accounts`.`id`
;
