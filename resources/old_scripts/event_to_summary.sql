#transfer based on x time and lock


INSERT INTO summary( mailing_id, instance, install_class, event, category, count, dt_first, dt_last )
	SELECT event.mailing_id, event.instance, event.install_class, event.event, event.category, 1, event.timestamp, event.timestamp
	FROM event
	WHERE event.id < 1436576 and event.queue_id <> 0
	ON DUPLICATE KEY UPDATE 
		count = ( count +1 ) , dt_last = IF( dt_last < event.timestamp, event.timestamp, dt_last ) , dt_first = IF( dt_first > event.timestamp, event.timestamp, dt_first );