<?php

//////////////////////////////////
// Initialize

//Ensure that the configuration file has a database section, all other
//sections are optional and we'll supply sane defaults instead
$config_constraints = array(
    'database' => array(
        'required' => true,
        'defaults' => array()
    ),
    'debug' => array(
        'required' => false,
        'defaults' => array('debug_level'=>1)
    ),
    'extracolumns' => array(
        'required' => false,
        'defaults' => array()
    ),
    'uniqueargs' => array(
        'required' => false,
        'defaults' => array()
    ),
);

// If we can't find and load the configuration file just die immediately
// SendGrid will put the event into a deferred queue and try again later
$config_file = realpath(dirname(__FILE__).'/../config.ini');
if( !$config = parse_ini_file($config_file,true) )
    error_out(500,"Configuration file not found at '$config_file'.");

// Enforce the constraints and throw errors as necessary, in the future it
// would probably be better to accumulate configuration errors and die after
// they have all been generated.
foreach($config_constraints as $section => $constraints) {
    if(!array_key_exists($section, $config)) {
        if($constraints['required'])
            _log(500,"Invalid config file. '$section' section required");
        else
            $config[$section] = array();
    }

        $config[$section] = array_merge($constraints['defaults'],$config[$section]);
    }

//Log the request parameters, encoded as a string for replication (curl)
$db = get_db_connection();
log_("NOTICE",mysql_real_escape_string(http_build_query($_POST),$db));

//////////////////////////////////////
// Clean the incoming data
//

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
    'unsubscribe' => array()
);
$unique_keys = array_keys($config['uniqueargs']);
$basic_keys = array('email', 'event', 'category');

// We require a valid event_type to be specified
$event_types = array_keys($event_keys);
if(! ($event_type = get_default('event', $_POST, false)) )
    error_out(400,"Event parameter must be specified and non-empty");

elseif( array_search($event_type,array_keys($event_keys)) === FALSE )
    error_out(400,"Event type '$event_type' is invalid.");

// Filter out all unexpected keys and validate the keyset
// Also sanitize the SQL arguments for safety against injection
$cleaned_data = array();
$expected_keys = array_merge($basic_keys, $event_keys[$event_type], $unique_keys);
foreach($_POST as $key => $value)
    if(array_search($key, $expected_keys) !== FALSE)
        $cleaned_data[$key] = mysql_real_escape_string($value,$db);

//Issue warnings if the incoming data isn't complete
if( $diff = array_diff_key(array_flip($expected_keys),$cleaned_data) ) {
    $keys = implode(', ',array_keys($diff));
    log_("WARN","Expected keys missing for event type '$event_type': $keys");
}

//Issue warnings if more data was sent than was expected.
if( $diff = array_diff_key($_POST,array_flip($expected_keys)) ) {
    $keys = implode(', ',array_keys($diff));
    log_("WARN","Unexpected keys found for event type '$event_type': $keys");
}

////////////////////////////////////////
// Create the new event
//

// Build the generic event table insert statement. We need to be careful
// here because unique arguments can be string or non string types and must
// be written into the sql statement differently (with respect to ''s)
// TODO: Validate that an numeric column has an integer value going into it
$numeric_types = array('int','integer','smallint','decimal','float','real',
                       'double','numeric','fixed','dec','bool','tinyint');

$event = get_default('event',$cleaned_data,'');
$email = get_default('email',$cleaned_data,'');
$category = get_default('category',$cleaned_data,'');

$matches = array();
$fields = "event, email, category, dt_received";
$values = "'$event','$email','$category',NOW()";
foreach( $config['uniqueargs'] as $key => $value ) {
    if( preg_match('/^ *([A-Za-z0-9_]+).*/', $value, $matches) == 0 )
        error_out(400,"UniqueArg '$key' has an improper value. Must start with the column type.");

    $fields .= ",$key";
    if( array_search(strtolower($matches[1]), $numeric_types) === FALSE )
        $values .= ",'".(isset($cleaned_data[$key]) ? $cleaned_data[$key] : "")."'";
    else
        $values .= ",".(isset($cleaned_data[$key]) ? $cleaned_data[$key] : 0);
}
$insert_event = "INSERT INTO event ($fields) VALUES ($values)";

// Build the event specific insert statement. Make sure to supply a default
// value for each one of these fields because we may have previously issued
// a warning about missing parameters from the POST request.
$response = get_default('response',$cleaned_data,'');
$attempt = get_default('attempt',$cleaned_data,0);
$reason = get_default('reason',$cleaned_data,'');
$status = get_default('status',$cleaned_data,'');
$event = get_default('event',$cleaned_data,'');
$type = get_default('type',$cleaned_data,'');
$type = get_default('url',$cleaned_data,'');

$insert_type = "INSERT INTO $event ";
switch ($event) {
    case 'bounce': $insert_type .= "(event_id, mta_response, type, status) VALUES (@event_id, '$response','$type', '$status')"; break;
    case 'click': $insert_type .= "(event_id, url) VALUES (@event_id, '$url')"; break;
    case 'deferred': $insert_type .= "(event_id, mta_response, attempt_num) VALUES (@event_id, '$response', $attempt)";break;
    case 'delivered': $insert_type .= "(event_id, mta_response) VALUES (@event_id, '$response')"; break;
    case 'dropped': $insert_type .= "(event_id, reason) VALUES (@event_id, '$reason')"; break;
    case 'open':
    case 'processed':
    case 'spamreport':
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

// I can't believe php doesn't let you execute multiple queries at once...
foreach($queries as $sql)
    exec_query($sql);


//Return the success code to SendGrid
header("HTTP/1.1 200:", true, 200);
echo "SUCCESS";







///////////////////////////////////
// Support Functions

function error_out($type, $message) {
    log_("ERROR", "[statserver] $type: $message");
    header("HTTP/1.1 $type:",true,$type);
    exit();
}


function log_($debug_level, $message) {
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
    if( $config_key < $message_key )
        return;

    //Log to the filesystem
    if( $filepath=get_default('log_file', $debug_config, false) ) {
        if( $handle = fopen($filepath,'a') ) {
            fwrite($handle, "[$debug_level] $message\n");
            fclose($handle);
        } else {
            error_log("[statserver] Could not open '$filepath' for writing.");
            if($debug_level == 'ERROR')
                 error_log("[statserver] $message");
        }
    }

    //Log to the database
    $safe_message = mysql_real_escape_string($message);
    exec_query("INSERT INTO log (dt_logged, debug_level, message)
                VALUES (NOW(), '$debug_level', '$safe_message')");
}

function exec_query($sql) {
    static $conn = null;
    if($conn == null)
        $conn = get_db_connection();

    if(mysql_query($sql,$conn) === FALSE)
        error_out(500,"MySQL Error: ".mysql_error($conn)."; running query: $sql");
}

function get_db_connection() {
    $dbconfig = $GLOBALS['config']['database'];

    //Validate the database configuration settings
    $required_keys = array('host','name','user','pass','port');
    if($missing_keys = array_diff_key(array_flip($required_keys), $dbconfig)) {
        $missing_key_msg = implode(', ',array_keys($diff));
        error_out(500, "Section [database] missing keys: $missing_key_msg");
    }

    $host = $dbconfig['host'];
    $name = $dbconfig['name'];
    $user = $dbconfig['user'];
    $pass = $dbconfig['pass'];
    $port = $dbconfig['port'];

    if( !$conn = mysql_connect("$host:$port",$user,$pass) )
        error_out(500,"Could not connect to: $user:$pass@$host:$port");

    if( !mysql_select_db($name,$conn) )
        error_out(500,"Database '$name' could not be selected.");

    return $conn;
}

function get_default($key, $data, $default) {
    //Also check for the '' because we might want to default that to 0
    return (isset($data[$key]) && $data[$key] != '') ? $data[$key] : $default;
}

?>
