DROP VIEW IF EXISTS archive_details;
CREATE VIEW archive_details AS
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