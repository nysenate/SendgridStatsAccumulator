DELIMITER $$

DROP PROCEDURE IF EXISTS archiveEvents$$
CREATE PROCEDURE archiveEvents() READS SQL DATA
BEGIN
    DECLARE id INT(10);
    DECLARE result VARCHAR(255);
    DECLARE email VARCHAR(255);
    DECLARE category VARCHAR(255);
    DECLARE timestamp DATETIME;
    DECLARE event VARCHAR(50);
    DECLARE mailing_id INT(10);
    DECLARE job_id INT(10);
    DECLARE queue_id INT(10);
    DECLARE instance VARCHAR(32);
    DECLARE install_class VARCHAR(8);
    DECLARE servername VARCHAR(64);
    DECLARE processed INT(1);
    DECLARE dt_processed DATETIME;
    DECLARE is_test TINYINT;
    
    DECLARE num_rows INT(11);
    DECLARE no_more_rows TINYINT(1);
    DECLARE instance_id INT(11) DEFAULT NULL;
    DECLARE message_id INT(11) DEFAULT NULL;

    DECLARE BATCH_SIZE INT(11) DEFAULT 50;
    DECLARE BATCH_OFFSET INT(11) DEFAULT 0;
    DECLARE all_event_cursor CURSOR FOR SELECT COUNT(*) FROM incoming;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET no_more_rows = TRUE;

    OPEN all_event_cursor;
    FETCH all_event_cursor INTO num_rows;
    CLOSE all_event_cursor;

    WHILE num_rows > 0 DO
    BEGIN
        DECLARE event_cursor CURSOR FOR 
            SELECT * FROM incoming LIMIT BATCH_SIZE OFFSET BATCH_OFFSET;

        OPEN event_cursor;
        the_loop: LOOP

            IF no_more_rows THEN
                no_more_rows = TRUE;
                CLOSE event_cursor;
                LEAVE the_loop;
            END IF;

            FETCH event_cursor INTO
                        id, email, category, timestamp,
                        event, mailing_id, job_id, queue_id,
                        instance, install_class, servername,
                        processed, dt_processed, is_test;

            -- Get and create if necessary the instance and message ids
            SET instance_id = returnInstance(install_class, servername, instance);
            SET message_id = returnMessage(instance_id, mailing_id, category);
            
            -- Determine what the result should be recorded as
            IF processed = 1 THEN
                SET result = "ARCHIVED";
            ELSEIF event = "processed" OR event = "delivered" THEN
                SET result = "SKIPPED";
            ELSE
                SET result = "FAILED";
            END IF;

            -- move to archive, delete later
            INSERT INTO `archive`(
                event_id, message_id, job_id, queue_id, event_type, result, email, is_test, dt_created, dt_received, dt_processed)
            VALUES(
                id, message_id, job_id, queue_id, event, result, email, is_test, timestamp, NULL, dt_processed);
            
        END LOOP the_loop;

        SET BATCH_OFFSET = BATCH_OFFSET + BATCH_SIZE;
        SET num_rows = num_rows - BATCH_SIZE;
    END;
    END WHILE;

    TRUNCATE TABLE event;
END$$

# returnInstance
# Tries to match the current row to a row in the instance table
# if it can't, it creates the row and returns the new id.
DROP FUNCTION IF EXISTS returnInstance$$
CREATE FUNCTION returnInstance(in_install_class VARCHAR(8),
                                in_servername VARCHAR(255),
                                in_name VARCHAR(255)) RETURNS int
BEGIN
    DECLARE instance_id INT(11) DEFAULT NULL;
    DECLARE no_more_rows TINYINT(1);
    DECLARE instance_cursor CURSOR FOR
        SELECT id FROM `instance`
        WHERE install_class=in_install_class AND
                servername=in_servername AND
                name=in_name;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND
        SET no_more_rows = TRUE;

    OPEN instance_cursor;
    FETCH instance_cursor INTO instance_id;
    CLOSE instance_cursor;

    IF instance_id IS NULL THEN
        INSERT INTO `instance`(install_class, servername, name)
            VALUES(in_install_class, in_servername, in_name);
        SET instance_id = LAST_INSERT_ID();
    END IF;

    RETURN instance_id;
END$$

# returnMessage
# Tries to match the current row to a row in the message table
# if it can't, it creates the row and returns the new id.
DROP FUNCTION IF EXISTS returnMessage$$
CREATE FUNCTION returnMessage(in_instance_id INT(11),
                                in_mailing_id INT(11),
                                in_category VARCHAR(255)) RETURNS int
BEGIN
    DECLARE message_id INT(11) DEFAULT NULL;
    DECLARE no_more_rows TINYINT(1);
    DECLARE message_cursor CURSOR FOR
        SELECT id FROM `message`
        WHERE   instance_id = in_instance_id AND
                mailing_id = in_mailing_id AND
                category=in_category;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET no_more_rows = TRUE;

    OPEN message_cursor;
    FETCH message_cursor INTO message_id;
    CLOSE message_cursor;

    IF message_id IS NULL THEN
        INSERT INTO `message`(instance_id, mailing_id, category)
            VALUES(in_instance_id, in_mailing_id, in_category);
        SET message_id = LAST_INSERT_ID();
    END IF;

    RETURN message_id;
END$$

DELIMITER ;

#call archiveEvents();