<?php

//Load up the configuration
$config_path = realpath(dirname(__FILE__).'/../config.ini');
$config = load_config($config_path);

//Log the request parameters, encoded as a string for replication (curl)
$db = get_db_connection();

if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    //Process the batched data, separated json objects by new lines
    $batchData = file_get_contents("php://input");
    log_("NOTICE", print_r($batchData, true));
    $jsonData = json_decode($batchData, true);
    foreach ($jsonData as $eventData) {
        create_event($config, $eventData, $db);
    }
}
else if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
    log_("NOTICE", http_build_query($_POST), $db);
    create_event($config, $_POST, $db);
}
else if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET') {
    log_("NOTICE", http_build_query($_GET), $db);
    create_event($config, $_GET, $db);
}
else {
    error_out(400, "Only POST, GET, and application/json requests supported.");
}

//Return the success code to SendGrid
//We would have died already if something went wrong
header("HTTP/1.1 200:", true, 200);
echo "SUCCESS";
exit(0);



function create_event($config, $data, $db)
{
    // The combination of event specific, basic, and unique keys creates a set
    // of required key values that we use to strictly validate the data source.
    $event_keys = array(
        'bounce'      => array('smtp-id', 'reason', 'status', 'type', 'sg_message_id'),
        'click'       => array('useragent', 'ip', 'url'),
        'deferred'    => array('smtp-id', 'attempt', 'response', 'sg_event_id'),
        'delivered'   => array('smtp-id', 'response', 'sg_event_id'),
        'dropped'     => array('smtp-id', 'reason'),
        'open'        => array('useragent', 'ip'),
        'processed'   => array('smtp-id', 'sg_event_id', 'sg_message_id'),
        'spamreport'  => array('sg_message_id'),
        'unsubscribe' => array()
    );
    $basic_keys = array('event', 'email', 'category', 'timestamp');
    $unique_keys = array('mailing_id', 'job_id', 'is_test', 'queue_id',
                         'instance', 'install_class', 'servername');

    // We require a valid event_type to be specified.
    if (!($event_type = get_default('event', $data, false))) {
        error_out(400, "Event parameter must be specified and non-empty");
    }
    else if (!isset($event_keys[$event_type])) {
        error_out(400, "Event type '$event_type' is invalid.");
    }

    // Generate an array of all possible valid keys for the current event,
    // then flip it so the possible values are actually keys themselves.
    $expected_keys = array_merge($basic_keys, $event_keys[$event_type], $unique_keys);
    $expected_keys = array_flip($expected_keys);

    // Also sanitize the SQL arguments for safety against injection.
    $cleaned_data = array();
    foreach ($data as $key => $value) {
        if (array_key_exists($key, $expected_keys)) {
            $cleaned_data[$key] = mysql_real_escape_string(urldecode($value), $db);
        }
    }

    // Besides event_type, the following 3 fields are present in all
    // Sendgrid event packets.
    $email = get_default('email', $cleaned_data, '');
    $category = get_default('category', $cleaned_data, '');
    $timestamp = date('Y-m-d H:i:s', get_default('timestamp', $cleaned_data, 0));

    // The following 3 fields should be present in all Bluebird-originated
    // event packets, whether resulting from blast e-mail or other e-mails.
    $instance = get_default('instance', $cleaned_data, '');
    $install_class = get_default('install_class', $cleaned_data, '');
    $servername = get_default('servername', $cleaned_data, '');

    // The remaining fields will be present in Bluebird-originated event
    // packets for blast e-mails only.
    $mailing_id = get_default('mailing_id', $cleaned_data, 0);
    $job_id = get_default('job_id', $cleaned_data, 0);
    $queue_id = get_default('queue_id', $cleaned_data, 0);
    $is_test = get_default('is_test', $cleaned_data, 0);

    if (!$install_class && !$instance) {
        // Quit early with an error, but still send back an HTTP 200.
        error_out(200, "No install_class or CRM instance available in data for event '$event_type'; Received data: ".print_r($data, true));
    }

    //Issue warnings if the incoming data isn't complete
    if ($diff = array_diff_key($expected_keys, $cleaned_data)) {
        $keys = implode(', ', array_keys($diff));
        log_("WARN", "[$install_class/$instance#$mailing_id] Expected keys missing for event type '$event_type': $keys");
    }

    //Issue warnings if more data was sent than was expected.
    // Note: Must use $data, not $cleaned_data, since $cleaned_data will
    //       contain only keys that were expected.
    if ($diff = array_diff_key($data, $expected_keys)) {
        $keys = implode(', ', array_keys($diff));
        log_("WARN", "[$install_class/$instance#$mailing_id] Unexpected keys found for event type '$event_type': $keys");
    }

    // Build the generic event insert statement. Make sure to supply a default
    // value for each one of these fields because we may have previously issued
    // a warning about missing parameters from the POST request.

    $fields = "event_type, email, category, dt_created, dt_received, mailing_id, job_id, queue_id, instance, install_class, servername, is_test";
    $values = "'$event_type','$email','$category','$timestamp', NOW(), $mailing_id, $job_id, $queue_id, '$instance', '$install_class', '$servername', $is_test";
    $insert_event = "INSERT INTO incoming ($fields) VALUES ($values)";

    // Build the event specific insert statement. Make sure to supply a default
    // value for each one of these fields because we may have previously issued
    // a warning about missing parameters from the POST request.
    $response = get_default('response', $cleaned_data, '');
    $smtp_id = get_default('smtp-id', $cleaned_data, '');
    $attempt = get_default('attempt', $cleaned_data, 0);
    $reason = get_default('reason', $cleaned_data, '');
    $status = get_default('status', $cleaned_data, '');
    $type = get_default('type', $cleaned_data, '');
    $url = get_default('url', $cleaned_data, '');

    $insert_type = "INSERT INTO $event_type ";
    switch ($event_type) {
        case 'bounce': $insert_type .= "(event_id, reason, type, status, smtp_id) VALUES (@event_id, '$reason','$type', '$status','$smtp_id')"; break;
        case 'click': $insert_type .= "(event_id, url) VALUES (@event_id, '$url')"; break;
        case 'deferred': $insert_type .= "(event_id, reason, attempt_num, smtp_id) VALUES (@event_id, '$response', $attempt, '$smtp_id')";break;
        case 'delivered': $insert_type .= "(event_id, response, smtp_id) VALUES (@event_id, '$response', '$smtp_id')"; break;
        case 'dropped': $insert_type .= "(event_id, reason , smtp_id) VALUES (@event_id, '$reason', '$smtp_id')"; break;
        case 'processed': $insert_type .= "(event_id, smtp_id) VALUES (@event_id,'$smtp_id')"; break;
        case 'open': //fall through to next case
        case 'spamreport': //fall through to next case
        case 'unsubscribe': $insert_type .= "(event_id) VALUES (@event_id)"; break;
    };

    // Run a sequence of SQL queries, locked in a transaction for consistency
    $queries = array(
        "SET autocommit=0",
        "BEGIN",
        $insert_event,
        "SET @event_id := LAST_INSERT_ID()",
        $insert_type,
        "COMMIT"
    );

    foreach ($queries as $sql) {
        exec_query($sql);
    }
} // create_event()


function load_config($config_file)
{
    // If we can't find and load the configuration file just die immediately
    // SendGrid will put the event into a deferred queue and try again later
    if (!$config = parse_ini_file($config_file, true)) {
        error_out(500, "Configuration file not found at '$config_file'.");
    }

    if (!array_key_exists('database', $config)) {
        error_out(500, "Invalid config file: [database] section required");
    }

    if (!array_key_exists('debug', $config)) {
        $config['debug'] = array('debug_level'=>1);
    }

    return $config;
} // load_config()



function error_out($type, $message)
{
    log_("ERROR", "[statserver] $type: $message");
    header("HTTP/1.1 $type:", true, $type);
    exit(1);
} // error_out()



function log_($debug_level, $message)
{
    $debug_config = $GLOBALS['config']['debug'];

    $debug_levels = array(
        1 => 'ERROR',
        2 => 'WARN',
        3 => 'NOTICE',
        4 => 'INFO',
    );

    //Get the integer level for each and ignore out of scope log messages
    $message_key = array_search($debug_level, $debug_levels);
    $config_key = array_search($debug_config['debug_level'], $debug_levels);
    if ($config_key < $message_key) {
        return;
    }

    $date = date('Y-m-d H:i:s');

    //Log to a debug file
    if ($filepath = get_default('log_file', $debug_config, false)) {
        if ($handle = fopen($filepath, 'a')) {
            fwrite($handle, "$date [$debug_level] $message\n");
            fclose($handle);
        }
        else {
            //If the specified file can't be found log it to apache
            error_log("[statserver] Could not open '$filepath' for writing.");
            if ($debug_level == 'ERROR') {
                error_log("[statserver] $message");
            }
        }
    }
    else {
    //Or log to apache
        error_log("[statserver] $date [$debug_level] $message\n");
    }
} // log_()



function exec_query($sql)
{
    static $conn = null;
    if ($conn == null) {
        $conn = get_db_connection();
    }

    if (mysql_query($sql, $conn) === false) {
        error_out(500, "MySQL Error: ".mysql_error($conn)."; running query: $sql");
    }
} // exec_query()



function get_db_connection()
{
    $dbconfig = $GLOBALS['config']['database'];

    //Validate the database configuration settings
    $required_keys = array('host', 'name', 'user', 'pass', 'port');
    if ($missing_keys = array_diff_key(array_flip($required_keys), $dbconfig)) {
        $missing_key_msg = implode(', ', array_keys($diff));
        error_out(500, "Section [database] missing keys: $missing_key_msg");
    }

    $host = $dbconfig['host'];
    $name = $dbconfig['name'];
    $user = $dbconfig['user'];
    $pass = $dbconfig['pass'];
    $port = $dbconfig['port'];

    if (!$conn = mysql_connect("$host:$port", $user, $pass)) {
        error_out(500, "Could not connect to: $user:$pass@$host:$port");
    }

    if (!mysql_select_db($name, $conn)) {
        error_out(500, "Database '$name' could not be selected.");
    }

    return $conn;
} // get_db_connection()



function get_default($key, $data, $default)
{
    //Also check for the '' because we might want to default that to 0
    return (isset($data[$key]) && $data[$key] != '') ? $data[$key] : $default;
} // get_default()

?>
