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
