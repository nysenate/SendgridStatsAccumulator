CREATE TABLE event (
    id int(10) unsigned PRIMARY KEY AUTO_INCREMENT,
    email varchar(255),
    category varchar(255),
    dt_received datetime,
    instance varchar(50),
    event varchar(50),
    mailing_id int(10) unsigned,
    job_id int(10) unsigned,
    processed int(1) unsigned DEFAULT 0,
    dt_processed datetime DEFAULT NULL,
    INDEX(event),
    INDEX(instance),
    INDEX(mailing_id),
    INDEX(job_id),
    INDEX(email),
    INDEX(category),
    INDEX(dt_received),
    INDEX(processed),
    INDEX(dt_processed)
) ENGINE=InnoDB;


CREATE TABLE bounce (
    event_id int(10) unsigned PRIMARY KEY,
    mta_response text,
    type varchar(20),
    status varchar(3),
    INDEX(type),
    INDEX(status),

    FOREIGN KEY (event_id) REFERENCES event(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE click (
    event_id int(10) unsigned PRIMARY KEY,
    url varchar(255),

    FOREIGN KEY (event_id) REFERENCES event(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE deferred (
    event_id int(10) unsigned PRIMARY KEY,
    mta_response text,
    attempt_num  int(10) unsigned,

    FOREIGN KEY (event_id) REFERENCES event(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE delivered (
    event_id int(10) unsigned PRIMARY KEY,
    mta_response text,

    FOREIGN KEY (event_id) REFERENCES event(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE dropped (
    event_id int(10) unsigned PRIMARY KEY,
    reason varchar(255),
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
