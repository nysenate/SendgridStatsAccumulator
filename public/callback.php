<?php

global $INT_TYPES;
$INT_TYPES = array('int','integer','smallint','decimal','float','real','double','numeric','fixed','dec','bool','tinyint');


$config_file = realpath(dirname(__FILE__).'/../config.ini');
if( !$config = parse_ini_file($config_file,true) )
    _log(500,"Configuration file not found at '$config_file'.");
elseif(!array_key_exists('database',$config))
    _log(500,"Invalid config file. 'database' section required");

$uniqueArgs = array();
if( array_key_exists('uniqueargs', $config) )
    $uniqueArgs = $config['uniqueargs'];

create_event(retrieve_data($_POST, array_keys($uniqueArgs)), get_connection($config['database']), $uniqueArgs);
header("HTTP/1.1 200:", true, 200);
echo "SUCCESS";

function _log($type, $message) {
    error_log("[statserver] $type: $message\n", 0);
    header("HTTP/1.1 $type:",true,$type);
    exit();
}

function assert_required($data, $required_keys, $failcode) {
    if( $diff = array_diff_key(array_flip($required_keys), $data) )
        _log($failcode,"Missing required keys:\n".print_r(array_keys($diff),TRUE)."\nKeys Supplied: \n".print_r($_POST,TRUE));
}

function get_connection($dbconfig) {
    assert_required($dbconfig, array('host','port','user','pass'), 500);

    if( !$conn = mysql_connect("{$dbconfig['host']}:{$dbconfig['port']}",$dbconfig['user'],$dbconfig['pass']))
        _log(500,"Could not connect with connection string: {$dbconfig['user']}:{$dbconfig['pass']}@{$dbconfig['host']}:{$dbconfig['port']}");
    elseif( !mysql_select_db($dbconfig['name'],$conn) )
        _log(500,"Database '{$dbconfig['name']}' could not be selected.");

    return $conn;
}

function retrieve_data($source, $unique_keys) {
    // The combination of event specific, basic, and unique keys creates a set
    // of required key values that we can use to strictly validate the data source.
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
    $basic_keys = array('email', 'event', 'category');

    // We require a valid event type to be specified
    if( array_key_exists('event', $source) === FALSE )
        _log(400,"Event parameter is required. Valid choices:\n".print_r($event_types,TRUE));
    elseif( array_search($source['event'],array_keys($event_keys)) === FALSE )
        _log(400,"Event type '{$source['event']}' is invalid. Valid choices:\n".print_r($event_types,TRUE));

    // Filter out all unexpected keys and validate the keyset
    $data = array();
    $required_keys = array_merge($basic_keys, $event_keys[$source['event']], $unique_keys);
    foreach($source as $key => $value)
        if(array_search($key, $required_keys) !== FALSE)
            $data[$key] = $value;

    // SendGrid doesn't seem to follow their own contracts, don't be as strict
    assert_required($data, array_merge($basic_keys, $unique_keys), 400);

    return $data;
}

function get_default($key, $data, $default) {
    return ((array_key_exists($key,$data) && $data[$key]!='') ? $data[$key] : $default);
}

function create_event($data, $db, $uniqueArgs) {
    // Sanitize the SQL arguments for safely against injection
    foreach($data as $key => $value)
        $data[$key] = mysql_real_escape_string($value, $db);

    // Build the generic event table insert statement
    global $INT_TYPES;
    $matches = array();
    $fields = "event, email, category, dt_received";
    $values = "'{$data['event']}','{$data['email']}','{$data['category']}',NOW()";
    foreach( $uniqueArgs as $key => $value ) {
        if( preg_match('/^ *([A-Za-z0-9_]+).*/', $value, $matches) == 0 )
            _log(400,"UniqueArg '$key' has an improper value. Must start with the column type.");

        $fields .= ",$key";
        if( array_search(strtolower($matches[1]), $INT_TYPES) === FALSE )
            $values .= ",'".(array_key_exists($key,$data) ? $data[$key] : "")."'";
        else
            $values .= ",".(array_key_exists($key,$data) ? $data[$key] : 0);
    }
    $insert_event = "INSERT INTO event ($fields) VALUES ($values)";

    $response = get_default('response',$data,'');
    $attempt = get_default('attempt',$data,0);
    $reason = get_default('reason',$data,'');
    $status = get_default('status',$data,'');
    $event = get_default('event',$data,'');
    $type = get_default('type',$data,'');
    $type = get_default('url',$data,'');

    // Build the event specific insert statement
    $insert_type = "INSERT INTO {$data['event']} ";
    switch ($data['event']) {
        case 'bounce': $insert_type .= "(event_id, mta_response, type, status) VALUES (@event_id, '$response','$type', '$status')"; break;
        case 'click': $insert_type .= "(event_id, url) VALUES (@event_id, '$url')";break;
        case 'deferred': $insert_type .= "(event_id, mta_response, attempt_num) VALUES (@event_id, '$response', $attempt)";break;
        case 'delivered': $insert_type .= "(event_id, mta_response) VALUES (@event_id, '$response')"; break;
        case 'dropped': $insert_type .= "(event_id, reason) VALUES (@event_id, '$reason')"; break;
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
