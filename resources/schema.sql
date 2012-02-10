CREATE TABLE log (
    id int(10) unsigned PRIMARY KEY AUTO_INCREMENT,
    dt_logged datetime,
    debug_level varchar(10),
    message text,
    INDEX(debug_level),
    INDEX(dt_logged)
);

CREATE TABLE event (
    id int(10) unsigned PRIMARY KEY AUTO_INCREMENT,
    email varchar(255),
    category varchar(255),
    `timestamp` datetime,
    event varchar(50),
    INDEX(event),
    INDEX(email),
    INDEX(category),
    INDEX(`timestamp`)
) ENGINE=InnoDB;


CREATE TABLE bounce (
    event_id int(10) unsigned PRIMARY KEY,
    reason text,
    type varchar(20),
    status varchar(8),
    INDEX(type),
    INDEX(status),
    smtp_id varchar(255),

    FOREIGN KEY (event_id) REFERENCES event(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE click (
    event_id int(10) unsigned PRIMARY KEY,
    url varchar(255),

    FOREIGN KEY (event_id) REFERENCES event(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE deferred (
    event_id int(10) unsigned PRIMARY KEY,
    reason text,
    attempt_num  int(10) unsigned,

    FOREIGN KEY (event_id) REFERENCES event(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE delivered (
    event_id int(10) unsigned PRIMARY KEY,
    response text,
    smtp_id varchar(255),

    FOREIGN KEY (event_id) REFERENCES event(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE dropped (
    event_id int(10) unsigned PRIMARY KEY,
    reason varchar(255),
    smtp_id varchar(255),
    INDEX(reason),

    FOREIGN KEY (event_id) REFERENCES event(id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- Some shell events that don't contain unique information
-- just here to keep consistency in access and join methods

CREATE TABLE open (
    event_id int(10) unsigned PRIMARY KEY,
    FOREIGN KEY (event_id) REFERENCES event(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE processed (
    event_id int(10) unsigned PRIMARY KEY,
    smtp_id varchar(255),
    FOREIGN KEY (event_id) REFERENCES event(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE spamreport (
    event_id int(10) unsigned PRIMARY KEY,
    FOREIGN KEY (event_id) REFERENCES event(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE unsubscribe (
    event_id int(10) unsigned PRIMARY KEY,
    FOREIGN KEY (event_id) REFERENCES event(id) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE `summary` (
    `mailing_id` int(10) NOT NULL,
    `instance` varchar(32) NOT NULL,
    `install_class` varchar(8) NOT NULL,
    `event` varchar(50) NOT NULL,
    `category` varchar(255) NOT NULL,
    `count` int(10) NOT NULL,
    `dt_first` datetime NOT NULL,
    `dt_last` datetime NOT NULL,
    UNIQUE KEY (`mailing_id`,`instance`,`install_class`,`event`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DELIMITER |

    -- On every event insert, sync with the summary table
    DROP TRIGGER IF EXISTS insert_stats_table |
    CREATE TRIGGER insert_stats_table AFTER INSERT ON event
        FOR EACH ROW BEGIN

            -- If queue_id=0 we don't process the event
            -- we need to exclude these from summaries.
            IF NEW.queue_id <> 0 THEN

                INSERT INTO summary(
                    mailing_id, instance, install_class, event, category, count, dt_first, dt_last
                ) VALUES (
                    NEW.mailing_id, NEW.instance, NEW.install_class, NEW.event, NEW.category, 1, NEW.timestamp, NEW.timestamp
                ) ON DUPLICATE KEY UPDATE
                    count = ( count +1 ),
                    dt_last = IF( dt_last < NEW.timestamp, NEW.timestamp, dt_last ),
                    dt_first = IF( dt_first > NEW.timestamp, NEW.timestamp, dt_first );

            END IF;
        END
    |

DELIMITER ;
