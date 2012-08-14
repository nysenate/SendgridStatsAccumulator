SET @start_date := '2012-08-10 01:37:00';
SET @end_date := '2012-08-13 12:33:00';

DROP VIEW IF EXISTS events;
CREATE VIEW events AS
SELECT  a.event_id,
        a.email,
        m.category,
        a.event_type,
        m.mailing_id,
        a.job_id,
        a.queue_id,
        i.name as instance,
        i.install_class,
        i.servername,
        a.result,
        a.dt_created,
        a.dt_received,
        a.dt_processed,
        a.is_test
FROM archive a
JOIN message m ON a.message_id=m.id
JOIN instance i ON m.instance_id=i.id;


BEGIN;

INSERT INTO weekend_events
	(event_id, email, category, event_type, mailing_id, job_id, queue_id, instance, install_class, servername, dt_created, dt_received, is_test)
SELECT
	event_id, email, category, event_type, mailing_id, job_id, queue_id, instance, install_class, servername, dt_created, dt_received, is_test
FROM events WHERE dt_processed > @start_date AND dt_processed < @end_date;

DELETE FROM archive WHERE dt_processed > @start_date AND dt_processed < @end_date;

COMMIT;