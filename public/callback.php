<?php

if( !$config = parse_ini_file("../config.ini",true) )
    _log(500,"Configuration file not found at '$location'.");
elseif(!array_key_exists('database',$config))
    _log(500,"Invalid config file. 'database' section required");

create_event(retrieve_data($_POST), get_connection($config['database']));

function _log($type, $message) {
    error_log("[statserver] $type: $message\n", 0);
    header("HTTP/1.1 $type:",true,$type);
    exit();
}

function assert_required($data, $required_keys, $failcode) {
    if( $diff = array_diff_key(array_flip($required_keys), $data) )
        _log($failcode,"Missing required keys:\n".print_r(array_keys($diff),TRUE));
}

function get_connection($dbconfig) {
    assert_required($dbconfig, array('host','port','user','pass'), 500);

    if( !$conn = mysql_connect("{$dbconfig['host']}:{$dbconfig['port']}",$dbconfig['user'],$dbconfig['pass']))
        _log(500,"Could not connect with connection string: {$dbconfig['user']}:{$dbconfig['pass']}@{$dbconfig['host']}:{$dbconfig['port']}");
    elseif( !mysql_select_db($dbconfig['name'],$conn) )
        _log(500,"Database '{$dbconfig['name']}' could not be selected.");

    return $conn;
}

function retrieve_data($source) {
    // The combination of event specific and basic keys creates a set of required
    // key values that we can use to strictly validate the data source.
    $event_keys = array(
            'bounce'      => array('response','type','status'),
            'click'       => array('url'),
            'deferred'    => array('response','attempt'),
            'delivered'   => array('response'),
            'dropped'     => array('reason'),
            'open'        => array(),
            'processed'   => array(),
            'spamreport'  => array(),
            'unsubscribe' => array());
    $event_types = array_keys($event_keys);
    $basic_keys = array('instance', 'mailing_id', 'job_id', 'email', 'event', 'category');

    // We require a valid event type to be specified
    if( array_key_exists('event', $source) === FALSE )
        _log(400,"Event parameter is required. Valid choices:\n".print_r($event_types,TRUE));
    elseif( array_search($source['event'],array_keys($event_keys)) === FALSE )
        _log(400,"Event type '{$source['event']}' is invalid. Valid choices:\n".print_r($event_types,TRUE));

    // Filter out all unexpected keys and validate the keyset
    $data = array();
    $required_keys = array_merge($basic_keys, $event_keys[$source['event']]);
    foreach($source as $key => $value)
        if(array_search($key, $required_keys) !== FALSE)
            $data[$key] = $value;
    assert_required($data, $required_keys, 400);

    return $data;
}

function create_event($data, $db) {
    // Sanitize the SQL arguments for safely against injection
    foreach($data as $key => $value)
        $data[$key] = mysql_real_escape_string($value, $db);

    // Build the generic event table insert statement
    $insert_event = "INSERT INTO event
                       (email, category, instance, mailing_id, job_id, dt_received)
                     VALUES
                       ('{$data['email']}','{$data['category']}','{$data['instance']}',{$data['mailing_id']},{$data['job_id']},NOW())";

    // Build the event specific insert statement
    $insert_type = "INSERT INTO {$data['event']} ";
    switch ($data['event']) {
        case 'bounce': $insert_type .= "(event_id, mta_response, type, status) VALUES (@event_id, '{$data['reponse']}', '{$data['type']}', '{$data['status']}')"; break;
        case 'click': $insert_type .= "(event_id, url) VALUES (@event_id, '{$data['url']}')";break;
        case 'deferred': $insert_type .= "(event_id, mta_response, attempt_num) VALUES (@event_id, '{$data['response']}', {$data['attempt']})";break;
        case 'delivered': $insert_type .= "(event_id, mta_response) VALUES (@event_id, '{$data['response']}')";break;
        case 'dropped': $insert_type .= "(event_id, reason) VALUES (@event_id, '{$data['reason']}')"; break;
        case 'open':
        case 'processed':
        case 'spamreport':
        case 'unsubscribe': $insert_type .= "(event_id) VALUES (@event_id)"; break;
    };

    // Run a sequence of SQL queries, locked in a transaction for consistency
    foreach(array("SET autocommit=0",
                  "BEGIN",
                  $insert_event,
                  "SET @event_id := LAST_INSERT_ID()",
                  $insert_type,
                  "COMMIT") as $sql)
        if( mysql_query($sql, $db) === FALSE )
            _log(500,"MySQL Error: ".mysql_error($db)." running query: $sql");

    return TRUE;
}
?>
