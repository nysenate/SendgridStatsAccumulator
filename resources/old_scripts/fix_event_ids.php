<?php

//Load up the configuration
$config_path = realpath(dirname(__FILE__).'/../config.ini');
$config = load_config($config_path);
$db = get_db_connection();

exec_queries(array(
    "DROP TABLE IF EXISTS weekend_events",
    "CREATE TABLE `weekend_events` (
      `event_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
      `category` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
      `event_type` enum('processed','bounce','open','delivered','click','spamreport','dropped','deferred','unsubscribe') COLLATE utf8_unicode_ci DEFAULT NULL,
      `mailing_id` int(10) unsigned DEFAULT NULL,
      `job_id` int(10) unsigned DEFAULT NULL,
      `queue_id` int(10) unsigned DEFAULT NULL,
      `instance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
      `install_class` varchar(8) COLLATE utf8_unicode_ci DEFAULT NULL,
      `servername` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
      `dt_created` datetime DEFAULT NULL,
      `dt_received` datetime DEFAULT NULL,
      `is_test` tinyint(1) DEFAULT '0',
      PRIMARY KEY (`event_id`),
      KEY `event_type` (`event_type`),
      KEY `servername` (`servername`),
      KEY `is_test` (`is_test`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",

    "SELECT @next := AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'sendgridstats' AND TABLE_NAME = 'incoming'",
    'SELECT @stmt := CONCAT("ALTER TABLE weekend_events AUTO_INCREMENT = ", @next)',
    'PREPARE transfer_auto_inc FROM @stmt',
    'EXECUTE transfer_auto_inc',
));

$result = mysql_query("SELECT * FROM incoming_innodb", $db);
while ($row = mysql_fetch_assoc($result)) {
    foreach($row as $key => $value) {
        $row[$key] = mysql_real_escape_string($value, $db);
    }
    exec_queries(array(
        "SET autocommit=0",
        "begin",
        "insert into weekend_events (email, category, event_type, mailing_id, job_id, queue_id, instance, install_class, servername, dt_created, dt_received, is_test) VALUES ('{$row['email']}', '{$row['category']}', '{$row['event_type']}', {$row['mailing_id']}, {$row['job_id']}, {$row['queue_id']}, '{$row['instance']}', '{$row['install_class']}', '{$row['servername']}', '{$row['dt_created']}', '{$row['dt_received']}', {$row['is_test']})",
        "SET @new_id := LAST_INSERT_ID()",
        "update {$row['event_type']} SET event_id=@new_id WHERE event_id={$row['event_id']}",
        "commit",
    ));

}

exec_queries(array(
    "SELECT @next := AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'sendgridstats' AND TABLE_NAME = 'weekend_events'",
    'SELECT @stmt := CONCAT("ALTER TABLE incoming AUTO_INCREMENT = ", @next)',
    'PREPARE transfer_auto_inc FROM @stmt',
    'EXECUTE transfer_auto_inc',
));


///////////////////////////////////////////////////
// Copy pasta from callback.php past this point
//

function load_config($config_file) {
    // If we can't find and load the configuration file just die immediately
    // SendGrid will put the event into a deferred queue and try again later
    if( !$config = parse_ini_file($config_file,true) )
        error_out(500,"Configuration file not found at '$config_file'.");

    if(!array_key_exists('database',$config))
        _log(500,"Invalid config file. '$section' section required");

    if (!array_key_exists('debug',$config))
        $config['debug'] = array('debug_level'=>1);

    return $config;
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

function exec_queries($sql_commands) {
    foreach($sql_commands as $sql)
        exec_query($sql);
}

function exec_query($sql) {
    static $conn = null;
    if($conn == null)
        $conn = get_db_connection();

    if(mysql_query($sql,$conn) === FALSE)
        error_out(500,"MySQL Error: ".mysql_error($conn)."; running query: $sql");
}

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

    $date = date('Y-m-d H:i:s');

    //Log to a debug file
    if( $filepath=get_default('log_file', $debug_config, false) ) {
        if ( $handle = fopen($filepath,'a') ) {
            fwrite($handle, "$date [$debug_level] $message\n");
            fclose($handle);
        } else {
            //If the specified file can't be found log it to apache
            error_log("[statserver] Could not open '$filepath' for writing.");
            if($debug_level == 'ERROR') {
                 error_log("[statserver] $message");
             }
        }

    //Or log to apache
    } else {
        error_log("[statserver] $date [$debug_level] $message\n");
    }
}

function get_default($key, $data, $default) {
    //Also check for the '' because we might want to default that to 0
    return (isset($data[$key]) && $data[$key] != '') ? $data[$key] : $default;
}
?>
