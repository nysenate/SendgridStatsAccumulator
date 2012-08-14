RENAME TABLE incoming TO incoming_innodb;

CREATE TABLE `incoming` (
  `event_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `category` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `event_type` enum('processed','bounce','open','delivered','click','spamreport','dropped','deferred','unsubscribe') COLLATE utf8_unicode_ci DEFAULT NULL,
  `mailing_id` int(10) unsigned DEFAULT NULL,
  `job_id` int(10) unsigned DEFAULT NULL,
  `queue_id` int(10) unsigned DEFAULT NULL,
  `instance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `install_class` varchar(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `servername` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dt_created` datetime DEFAULT NULL,
  `dt_received` datetime DEFAULT NULL,
  `is_test` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`event_id`),
  KEY `event_type` (`event_type`),
  KEY `email` (`email`),
  KEY `category` (`category`),
  KEY `mailing_id` (`mailing_id`),
  KEY `job_id` (`job_id`),
  KEY `queue_id` (`queue_id`),
  KEY `instance` (`instance`),
  KEY `install_class` (`install_class`),
  KEY `servername` (`servername`),
  KEY `dt_created` (`dt_created`),
  KEY `dt_received` (`dt_received`),
  KEY `is_test` (`is_test`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Assign the autoincrement id appropriately
SELECT @stmt := CONCAT("ALTER TABLE incoming AUTO_INCREMENT = ", max(event_id)+1) FROM archive;
PREPARE transfer_auto_inc FROM @stmt;
EXECUTE transfer_auto_inc;

-- INSERT INTO incoming (email, category, event_Type, mailing_id, job_id, queue_id, instance, install_class, servername, dt_created, dt_received, is_test)
-- SELECT email, category, event_Type, mailing_id, job_id, queue_id, instance, install_class, servername, dt_created, dt_received, is_test FROM incoming_innodb;

-- drop table incoming_innodb
