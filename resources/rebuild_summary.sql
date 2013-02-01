-- Just change the dt_created time to adjust the window that is rebuilt
REPLACE INTO summary (
  SELECT mailing_id, instance_name, install_class, event_type, category, count(*), min(dt_created), max(dt_created)
  FROM archive JOIN (
    SELECT DISTINCT message.id as message_id, message.mailing_id, message.category, instance.name as instance_name, instance.install_class
    FROM message
      JOIN instance ON message.instance_id=instance.id
      JOIN archive ON archive.message_id=message.id
    WHERE archive.dt_created > '2012-07-24 12:00:00'
      AND archive.queue_id != 0
      AND mailing_id != 0
      AND instance.servername != ''
    ) AS affected_messages USING (message_id)
  WHERE queue_id != 0 AND is_test != 0
  GROUP BY instance_name, install_class, mailing_id, event_type
);

