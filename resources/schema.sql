CREATE TABLE summary (
    -- Keys representing the bucket to be summarized
    mailing_id int(10) NOT NULL,
    instance varchar(32) NOT NULL,
    install_class varchar(8) NOT NULL,
    event varchar(50) NOT NULL,

    category varchar(255) NOT NULL COMMENT 'A shortcut to the category for the mailing',
    count int(10) NOT NULL COMMENT 'The total number of events in the bucket',
    dt_first datetime NOT NULL COMMENT 'The datetime of the first event in the bucket',
    dt_last datetime NOT NULL COMMENT 'The datetime of the last event in the bucket',

    UNIQUE KEY (mailing_id,instance,install_class,event)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- TODO: set the auto increment KEY
CREATE TABLE incoming (
  id int(10) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
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
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci; -- Currently 20530724

CREATE TABLE bounce (
    event_id int(10) unsigned PRIMARY KEY,
    reason text,
    type varchar(20),
    status varchar(8),
    smtp_id varchar(255),
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE click (
    event_id int(10) unsigned PRIMARY KEY,
    url varchar(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE deferred (
    event_id int(10) unsigned PRIMARY KEY,
    reason text,
    attempt_num  int(10) unsigned,
    smtp_id varchar(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE delivered (
    event_id int(10) unsigned PRIMARY KEY,
    response text,
    smtp_id varchar(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE dropped (
    event_id int(10) unsigned PRIMARY KEY,
    reason varchar(255),
    smtp_id varchar(255),
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE open (
    event_id int(10) unsigned PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE processed (
    event_id int(10) unsigned PRIMARY KEY,
    smtp_id varchar(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE spamreport (
    event_id int(10) unsigned PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE unsubscribe (
    event_id int(10) unsigned PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


-- On every event insert, sync with the summary table
-- Must be rebuilt due to the event table alterations/renaming
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

DROP VIEW IF EXISTS events;
CREATE VIEW events AS
SELECT  a.event_id as id,
        a.email,
        m.category,
        a.event_type,
        m.mailing_id,
        a.queue_id,
        i.name as instance,
        i.install_class,
        i.servername,
        a.dt_created,
        a.dt_received,
        a.dt_processed,
        a.is_test
FROM `archive` a
JOIN `message` m ON `a`.`message_id`=`m`.`id`
JOIN `instance` i ON `m`.`instance_id`=`i`.`id`;
