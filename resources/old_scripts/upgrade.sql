START TRANSACTION;

-- Delete the foreign keys pointing to the incoming table before we change the column
ALTER TABLE `bounce` DROP FOREIGN KEY `bounce_ibfk_1`;
ALTER TABLE `click` DROP FOREIGN KEY `click_ibfk_1`;
ALTER TABLE `deferred` DROP FOREIGN KEY `deferred_ibfk_1`;
ALTER TABLE `delivered` DROP FOREIGN KEY `delivered_ibfk_1`;
ALTER TABLE `dropped` DROP FOREIGN KEY `dropped_ibfk_1`;
ALTER TABLE `open` DROP FOREIGN KEY `open_ibfk_1`;
ALTER TABLE `processed` DROP FOREIGN KEY `processed_ibfk_1`;
ALTER TABLE `spamreport` DROP FOREIGN KEY `spamreport_ibfk_1`;
ALTER TABLE `unsubscribe` DROP FOREIGN KEY `unsubscribe_ibfk_1`;

ALTER TABLE bounce ADD INDEX (event_id);
ALTER TABLE click ADD INDEX (event_id);
ALTER TABLE deferred ADD INDEX (event_id);
ALTER TABLE delivered ADD INDEX (event_id);
ALTER TABLE dropped ADD INDEX (event_id);
ALTER TABLE open ADD INDEX (event_id);
ALTER TABLE processed ADD INDEX (event_id);
ALTER TABLE spamreport ADD INDEX (event_id);
ALTER TABLE unsubscribe ADD INDEX (event_id);

DELIMITER |

    DROP TRIGGER IF EXISTS insert_stats_table |
    CREATE TRIGGER insert_stats_table AFTER INSERT ON incoming
        FOR EACH ROW BEGIN

            -- If queue_id=0 we don't process the event
            -- we need to exclude these from summaries.
            IF NEW.queue_id <> 0 THEN

                INSERT INTO summary(
                    mailing_id, instance, install_class, event, category, count, dt_first, dt_last
                ) VALUES (
                    NEW.mailing_id, NEW.instance, NEW.install_class, NEW.event_type, NEW.category, 1, NEW.dt_created, NEW.dt_created
                ) ON DUPLICATE KEY UPDATE
                    count = ( count +1 ),
                    dt_last = IF( dt_last < NEW.dt_created, NEW.dt_created, dt_last ),
                    dt_first = IF( dt_first > NEW.dt_created, NEW.dt_created, dt_first );

            END IF;
        END
    |

DELIMITER ;

-- New table schemas for long term storage and compression
-- Introduces two layers of compression from the incoming table
--   Instance: Compresses repetition from servername, instance, and install_class
--   Message: Compresses repetition from mailing_id and category
--
-- The rest of the fields are still located in the primary 'archive' table.
-- After processing, rows are removed from incoming inserted into archive
-- after compressing the data according to the layers listed above.
--
-- The archive table contains two new columns to reflect the fact that the
-- rows have been processed:
--   dt_processed: lets us know when
--   result: lets us know what happened
--
CREATE TABLE instance (
    id int unsigned PRIMARY KEY auto_increment,
    install_class ENUM('prod','test','dev'),
    servername varchar(255),
    name varchar(255),

    KEY (install_class),
    KEY (servername),
    KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


CREATE TABLE message (
    id int unsigned PRIMARY KEY auto_increment,
    instance_id int unsigned,
    mailing_id int unsigned,
    category varchar(255),

    KEY (category),
    KEY (mailing_id),
    FOREIGN KEY (instance_id) REFERENCES instance(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


CREATE TABLE archive (
    event_id int unsigned PRIMARY KEY,
    message_id int unsigned COMMENT 'Ties request back to the specific instance-mailing.',
    job_id int unsigned COMMENT 'Marks which subjob of a mailing made the request',
    queue_id int unsigned COMMENT 'Instance-unique id assigned by bluebird.',
    event_type ENUM('processed','bounce','open','delivered','click','spamreport','dropped','deferred','unsubscribe'),
    result ENUM('FAILED','SKIPPED','ARCHIVED'),
    email varchar(255),
    is_test boolean,
    dt_created datetime,
    dt_received datetime,
    dt_processed datetime,

    KEY (message_id),
    KEY (job_id),
    KEY (queue_id),
    KEY (event_type),
    KEY (result),
    KEY (email),
    KEY (is_test),
    KEY (dt_created),
    KEY (dt_received),
    KEY (dt_processed),
    FOREIGN KEY (message_id) REFERENCES message(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE incoming (
  event_id int(10) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  email varchar(255) DEFAULT NULL,
  category varchar(255) DEFAULT NULL,
  event_type ENUM('processed','bounce','open','delivered','click','spamreport','dropped','deferred','unsubscribe'),
  mailing_id int(10) unsigned DEFAULT NULL,
  job_id int(10) unsigned DEFAULT NULL,
  queue_id int(10) unsigned DEFAULT NULL,
  instance varchar(32) DEFAULT NULL,
  install_class varchar(8) DEFAULT NULL,
  servername varchar(64) DEFAULT NULL,
  dt_created datetime DEFAULT NULL,
  dt_received datetime DEFAULT NULL,
  is_test tinyint(1) DEFAULT '0',

  KEY (event_type),
  KEY (email),
  KEY (category),
  KEY (mailing_id),
  KEY (job_id),
  KEY (queue_id),
  KEY (instance),
  KEY (install_class),
  KEY (servername),
  KEY (dt_created),
  KEY (dt_received),
  KEY (is_test)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Assign the autoincrement id appropriately
SELECT @stmt := CONCAT("ALTER TABLE incoming AUTO_INCREMENT = ", max(id)+1) FROM event;
PREPARE transfer_auto_inc FROM @stmt;
EXECUTE transfer_auto_inc;

DROP VIEW IF EXISTS events;
CREATE VIEW events AS
SELECT  a.event_id,
        a.email,
        m.category,
        a.event_type,
        m.mailing_id,
        a.queue_id,
        i.name as instance,
        i.install_class,
        i.servername,
        a.result,
        a.dt_created,
        a.dt_received,
        a.dt_processed,
        a.is_test
FROM `archive` a
JOIN `message` m ON `a`.`message_id`=`m`.`id`
JOIN `instance` i ON `m`.`instance_id`=`i`.`id`;

COMMIT;
