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

-- Queries for the event table transform
ALTER TABLE event RENAME TO incoming;
ALTER TABLE incoming CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;
ALTER TABLE incoming CHANGE COLUMN id event_id int(10) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE incoming CHANGE COLUMN event event_type ENUM('processed','bounce','open','delivered','click','spamreport','dropped','deferred','unsubscribe');
ALTER TABLE incoming CHANGE COLUMN timestamp dt_created datetime DEFAULT NULL;
ALTER TABLE incoming ADD COLUMN dt_received datetime DEFAULT NULL;
ALTER TABLE incoming DROP COLUMN processed;
ALTER TABLE incoming DROP COLUMN dt_processed;

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
drop table if exists instance;
drop table if exists message;
drop table if exists archive;

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
    id int unsigned PRIMARY KEY auto_increment,
    message_id int unsigned COMMENT 'Ties request back to the specific instance-mailing.',
    job_id int unsigned COMMENT 'Marks which subjob of a mailing made the request',
    queue_id int unsigned COMMENT 'Instance-unique id assigned by bluebird.',
    type ENUM('processed','bounce','open','delivered','click','spamreport','dropped','deferred','unsubscribe'),
    result ENUM('FAILED','SKIPPED','ARCHIVED'),
    email varchar(255),
    is_test boolean,
    dt_created datetime,
    dt_received datetime,
    dt_processed datetime,

    KEY (message_id),
    KEY (job_id),
    KEY (queue_id),
    KEY (type),
    KEY (result),
    KEY (email),
    KEY (is_test),
    KEY (dt_created),
    KEY (dt_received),
    KEY (dt_processed),
    FOREIGN KEY (message_id) REFERENCES message(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

COMMIT;
