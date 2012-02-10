-- Respawn the summary table
DROP TABLE IF EXISTS summary;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


-- Make sure we get a write lock so that we don't double
-- count events with the trigger and the @max_id boundry
LOCK TABLES event WRITE;
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

    SELECT @max_id:=max(id) FROM event |

DELIMITER ;
UNLOCK TABLES ;

-- Now backfill the summary table with old event stats
-- The tables don't need to be locked for this because
-- We saved the upper event.id bound while we had the
-- table lock
INSERT INTO summary(mailing_id, instance, install_class, event, category, count, dt_first, dt_last)
    SELECT event.mailing_id, event.instance, event.install_class, event.event, event.category, count(*) as `count`, min(event.timestamp) as dt_first, max(event.timestamp) as dt_last
    FROM event
    WHERE event.id <= @max_id and event.queue_id <> 0
    GROUP BY event.install_class, event.instance, event.mailing_id, event.event
    HAVING `count` > 0
ON DUPLICATE KEY UPDATE
    count = count + VALUES(`count`),
    dt_first = IF( dt_first > VALUES(dt_first), VALUES(dt_first), dt_first ),
    dt_last =  IF( dt_last < VALUES(dt_last), VALUES(dt_last), dt_last );
