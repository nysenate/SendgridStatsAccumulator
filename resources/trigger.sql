DELIMITER ;

DELIMITER |
DROP TRIGGER IF EXISTS insert_stats_table |
CREATE TRIGGER insert_stats_table AFTER INSERT ON event2
	FOR EACH
	ROW BEGIN 

		IF NEW.queue_id <> 0
		THEN
			INSERT INTO summary( mailing_id, instance, install_class, event, category, count, dt_first, dt_last ) 
			VALUES (
			NEW.mailing_id, NEW.instance, NEW.install_class, NEW.event, NEW.category, 1, NEW.timestamp, NEW.timestamp
			) ON DUPLICATE 
			KEY UPDATE count = ( count +1 ) , dt_last = IF( dt_last < NEW.timestamp, NEW.timestamp, dt_last ) , dt_first = IF( dt_first > NEW.timestamp, 
			NEW.timestamp, dt_first ) ;
		END IF;
	END
|

DELIMITER ;