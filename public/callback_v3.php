<?php

// Define these constants prior to calling parse_ini_file().  That way, the
// function will translate the debug_level string into an integer value.
define('ERROR', 1);
define('WARN', 2);
define('INFO', 3);
define('DEBUG', 4);

define('HTTP_OK', 200);
define('HTTP_BADREQ', 400);
define('HTTP_SRVERR', 500);

$g_debug_level = WARN;  // set the default logging level

// Set up some global variables for event data.
$g_event_keys = array(
  'bounce'      => array('smtp-id', 'reason', 'status', 'type'),
  'click'       => array('useragent', 'url', 'url_offset'),
  'deferred'    => array('smtp-id', 'attempt', 'response'),
  'delivered'   => array('smtp-id', 'response'),
  'dropped'     => array('smtp-id', 'reason'),
  'open'        => array('useragent'),
  'processed'   => array('smtp-id'),
  'spamreport'  => array('useragent'),
  'unsubscribe' => array('useragent')
);
$g_basic_keys = array('event', 'email', 'category', 'timestamp', 'sg_event_id', 'sg_message_id', 'ip', 'tls', 'cert_err');
$g_unique_keys = array('mailing_id', 'job_id', 'is_test', 'queue_id',
                       'instance', 'install_class', 'servername');

// Load up the configuration.
$config_path = realpath(dirname(__FILE__).'/../config.ini');
$config = load_config($config_path);
if ($config === false) {
  reply_and_exit(HTTP_SRVERR);
}

// Set up the debug log level and log file, and open db connection.
$g_debug_level = get_debug_level($config);
$g_log_file = get_log_file($config);
$dbcon = get_db_connection($config);
if ($dbcon === false) {
  reply_and_exit(HTTP_SRVERR);
}

if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
  //Process the batched data, separated json objects by new lines
  $jsonData = file_get_contents("php://input");
  log_(DEBUG, "Incoming JSON data: $jsonData");
  $batchData = json_decode($jsonData, true);
  if ($batchData) {
    log_(INFO, "Processing batch of ".count($batchData)." event record(s)");
    foreach ($batchData as $eventData) {
      $http_status = create_event($config, $eventData, $dbcon);
      if ($http_status == HTTP_SRVERR) {
        break;
      }
    }
  }
  else {
    log_(ERROR, "Unable to decode JSON event data");
    $http_status = HTTP_SRVERR;
  }
}
else if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
  log_(DEBUG, http_build_query($_POST));
  log_(INFO, "Processing a single POST event record");
  $http_status = create_event($config, $_POST, $dbcon);
}
else if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET') {
  log_(DEBUG, http_build_query($_GET));
  log_(INFO, "Processing a single GET event record");
  $http_status = create_event($config, $_GET, $dbcon);
}
else {
  log_(ERROR, "Only POST, GET, and application/json requests supported");
  $http_status = HTTP_BADREQ;
}


if ($dbcon) {
  mysqli_close($dbcon);
}

reply_and_exit($http_status);



function create_event($config, $data, $dbcon)
{
  global $g_event_keys, $g_basic_keys, $g_unique_keys;

  // We require a valid event_type to be specified.
  if (!($event_type = get_default('event', $data, false))) {
    log_(ERROR, "Event parameter must be specified and non-empty");
    return HTTP_SRVERR;
  }
  else if (!isset($g_event_keys[$event_type])) {
    log_(ERROR, "Event type '$event_type' is invalid.");
    return HTTP_SRVERR;
  }

  // The combination of event specific, basic, and unique keys creates a set
  // of expected key values that we use to strictly validate the data source.
  // Generate an array of all possible valid keys for the current event,
  // then flip it so the possible values are actually keys themselves.
  $expected_keys = array_merge($g_basic_keys, $g_event_keys[$event_type], $g_unique_keys);
  $expected_keys = array_flip($expected_keys);

  // Also sanitize the SQL arguments for safety against injection.
  $cleaned_data = array();
  foreach ($data as $key => $value) {
    if (array_key_exists($key, $expected_keys)) {
      if (is_array($value)) {
        $first_elem = reset($value);
        log_(INFO, "Key '$key' in event '$event_type' has an array value; using first element '$first_elem' as value");
        log_(DEBUG, "Key '$key' value is ".print_r($value, true));
        $value = $first_elem;
      }
      $cleaned_data[$key] = mysqli_real_escape_string($dbcon, urldecode($value));
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

  log_(INFO, "[$install_class/$instance#$mailing_id|$event_type] Processing event for email=$email");

  if (!$install_class && !$instance) {
    log_(WARN, "No install_class or CRM instance available in data for event '$event_type'; email=[$email]; category=[$category]");
    return HTTP_OK;
  }
  else if ($mailing_id == 0) {
    log_(INFO, "[$install_class/$instance|$event_type] Skipping event for non-blast e-mail; email=[$email]; category=[$category]");
    return HTTP_OK;
  }

/********************************************************************
** No longer expecting ALL of the expected_keys to be present.
** Some keys are optional.
  //Issue warnings if the incoming data isn't complete
  if ($diff = array_diff_key($expected_keys, $cleaned_data)) {
    $keys = implode(', ', array_keys($diff));
    log_(WARN, "[$install_class/$instance#$mailing_id|$event_type] Expected keys missing: $keys [email=$email]");
  }
********************************************************************/

  //Issue warnings if more data was sent than was expected.
  // Note: Must use $data, not $cleaned_data, since $cleaned_data will
  //     contain only keys that were expected.
  if ($diff = array_diff_key($data, $expected_keys)) {
    $keys = implode(', ', array_keys($diff));
    log_(WARN, "[$install_class/$instance#$mailing_id|$event_type] Unexpected keys found: $keys [email=$email]");
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
  $url = get_default('url', $cleaned_data, '', 255);

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
    if (!exec_query($sql, $dbcon)) {
      return HTTP_SRVERR;
    }
  }
  return HTTP_OK;
} // create_event()



function load_config($config_file)
{
  // If we can't find and load the configuration file just die immediately
  // SendGrid will put the event into a deferred queue and try again later
  if (!$config = parse_ini_file($config_file, true)) {
    log_(ERROR, "Configuration file not found at '$config_file'.");
    return false;
  }

  if (!array_key_exists('database', $config)) {
    log_(ERROR, "Invalid config file: [database] section required");
    return false;
  }

  return $config;
} // load_config()



function log_($log_level, $message)
{
  global $g_debug_level, $g_log_file;

  //Get the integer level for each and ignore out of scope log messages
  if ($g_debug_level < $log_level) {
    return;
  }

  switch ($log_level) {
    case ERROR: $debug_level = 'ERROR'; break;
    case WARN: $debug_level = 'WARN'; break;
    case INFO: $debug_level = 'INFO'; break;
    case DEBUG: $debug_level = 'DEBUG'; break;
    default: $debug_level = $log_level; break;
  }
  $dt = date('YmdHis.').(int)(gettimeofday()['usec'] / 1000);

  //Log to a debug file, or to Apache if debug file was not opened.
  if ($g_log_file) {
    fwrite($g_log_file, "$dt [$debug_level] $message\n");
  }
  else {
    error_log("[statserver] $dt [$debug_level] $message\n");
  }
} // log_()



function exec_query($sql, $conn)
{
  if (mysqli_query($conn, $sql) === false) {
    log_(ERROR, "MySQL Error: ".mysqli_error($conn)."; running query: $sql");
    return false;
  }
  else {
    return true;
  }
} // exec_query()



function get_db_connection($cfg)
{
  $dbconfig = $cfg['database'];

  //Validate the database configuration settings
  $required_keys = array('host', 'port', 'user', 'pass', 'name');
  if ($missing_keys = array_diff_key(array_flip($required_keys), $dbconfig)) {
    $missing_key_msg = implode(', ', array_keys($diff));
    log_(ERROR, "Section [database] missing keys: $missing_key_msg");
    return false;
  }

  $host = $dbconfig['host'];
  $port = $dbconfig['port'];
  $user = $dbconfig['user'];
  $pass = $dbconfig['pass'];
  $name = $dbconfig['name'];

  $conn = mysqli_connect($host, $user, $pass, $name, $port);
  if (!$conn) {
    log_(ERROR, "Could not connect to: $user:$pass@$host:$port/$name");
    return false;
  }

  return $conn;
} // get_db_connection()



function get_default($key, $a, $default, $maxlen = 0)
{
  //Also check for the '' because we might want to default that to 0
  if (isset($a[$key]) && $a[$key] != '') {
    $val = $a[$key];
  }
  else {
    $val = $default;
  }

  if ($maxlen > 0 && strlen($val) > $maxlen) {
    log_(WARN, "Value for key [$key] exceeds maxlen=$maxlen; truncating");
    $val = substr($val, 0, $maxlen);
  }
  return $val;
} // get_default()



function get_debug_level($cfg)
{
  $debug_level = WARN;  // default debug level is WARN
  if (isset($cfg['debug']['debug_level'])) {
    $debug_level_val = $cfg['debug']['debug_level'];
    if (is_numeric($debug_level_val)) {
      $debug_level = $debug_level_val;
    }
    else {
      error_log("[statserver] $debug_level_val: Invalid debug level");
    }
  }
  return $debug_level;
} // get_debug_level()



function get_log_file($cfg)
{
  $log_file = false;

  if (isset($cfg['debug']['log_file'])) {
    $filepath = $cfg['debug']['log_file'];
    $log_file = fopen($filepath, 'a');
    if (!$log_file) {
      error_log("[statserver] $filepath: Unable to open for writing");
    }
  }
  return $log_file;
} // get_log_file()



function reply_and_exit($http_status)
{
  global $g_log_file;

  header("HTTP/1.1 $http_status", true, $http_status);
  if ($http_status != HTTP_OK) {
    log_(ERROR, "[statserver] Returned HTTP status=$http_status");
    $rc = 1;
  }
  else {
    $rc = 0;
  }

  if ($g_log_file) {
    fclose($g_log_file);
  }

  exit($rc);
} // reply_and_exit()

?>
