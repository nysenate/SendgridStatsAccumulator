<?php
error_reporting(E_ERROR);

$eventTypes = array(
    'processed' => array('label'=>'Processed', 'width'=>70, 'rate'=>false),
    'delivered' => array('label'=>'Delivered', 'width'=>70, 'rate'=>false),
    'dropped' => array('label'=>'Dropped', 'width'=>70, 'rate'=>false),
    'deferred' => array('label'=>'Deferred', 'width'=>70, 'rate'=>false),
    'bounce' => array('label'=>'Bounced', 'width'=>70, 'rate'=>false),
    'open' => array('label'=>'Opened', 'width'=>95, 'rate'=>true),
    'click' => array('label'=>'Clicked', 'width'=>70, 'rate'=>false),
    'spamreport' => array('label'=>'Spamreps', 'width'=>65, 'rate'=>false),
    'unsubscribe' => array('label'=>'Unsubs', 'width'=>65, 'rate'=>false)
);


function getInstances($cfg, $groups)
{
  $perms = $cfg['permissions'];
  $instances = array();
  // $key is CRM instance name; $val is comma-separated list of LDAP groups
  foreach ($perms as $key => $val) {
    $allowedGroups = array_map('trim', explode(',', $val));
    foreach ($groups as $group) {
      if (in_array($group, $allowedGroups)) {
        $instances[] = trim($key);
        break;   // avoid adding an instance more than once
      }
    }
  }
  return $instances;
} // getInstances()



function displayStats($cfg, $instances, $dt_start, $dt_end)
{
  $dbhost = $cfg['database']['host'].":".$cfg['database']['port'];
  $dbuser = $cfg['database']['user'];
  $dbpass = $cfg['database']['pass'];
  $dbname = $cfg['database']['name'];

  $dbcon = mysql_connect($dbhost, $dbuser, $dbpass);
  if (!$dbcon) {
    die("Unable to connect to database");
  }

  if (!mysql_select_db($dbname)) {
    die("Unable to select database [$dbname]");
  }

  // Generate SQL for the list of instances to be viewed.
  $instance_in = '';
  foreach ($instances as $instance) {
    if (!empty($instance_in)) {
      $instance_in .= ',';
    }
    $instance_in .= "'$instance'";
  }

  $q = "
  select instance, category, summary.mailing_id, event, count, dt_first, dt_last
  from summary 
  join (
    select DISTINCT summ.instance, mailing_id, MIN(summ.dt_first) AS start
    from summary as summ
    where summ.install_class='prod'
      and summ.event = 'processed' 
    GROUP by instance, mailing_id
    HAVING start >= '".$dt_start." 00:00:00'
       and start <= '".$dt_end." 23:59:59'
  ) AS mailing
  USING (instance,mailing_id)
  where install_class='prod' and instance in(".$instance_in.")
  group by instance, mailing_id, event
  order by instance ASC, mailing_id DESC;";

  $res = mysql_query($q, $dbcon);

  $data = array();
  while ($row = mysql_fetch_assoc($res)) {
    $data[$row['instance']][$row['mailing_id']][$row['event']] = $row;
  }

  // Confirm that there is at least one senator/mailing/event.
  if (count($data) > 0) {
    foreach (array_keys($data) as $senator) {
      displaySenatorStats($data[$senator], $senator);
    }
  }
  else {
    print('<div class="noData">No Data Found</div>');
  }
} // displayStats()



//Prints the individual items of 'bounce, click'... etc in a smaller package.
function displayEventStats($events)
{
  global $eventTypes;

  //this is the order of array amounts, individual changes &/or additions could be added as if statements in the foreach
  foreach ($eventTypes as $eventType => $eventInfo) {
    $label = $eventInfo['label'];
    $width = $eventInfo['width'];
    $showRate = $eventInfo['rate'];

    print("<div class=\"item\" style=\"width:${width}px;\">"); 
    print('<div>');
    print($label);
    print('</div>');
    print("<div class=\"$eventType\">"); 
    print($events[$eventType]);
    if ($showRate) {
      print(" (".round(100*$events[$eventType]/$events['delivered'], 1)."%)");
    }
    print('</div>');
    print('</div>'); 
  }
} // displayEventStats()



//collates and arranges the data and provides the sum of amounts as it traverses the array
function displaySenatorStats($mailings, $senator)
{
  global $eventTypes;

  print('<div class="result">');
  print('<div class="senatorName">'.$senator.'</div>');
  $totals = array();
  foreach ($mailings as $mailing) {
    // Retrieve one of the event rows for this mailing.
    $row = reset($mailing);
    $dt_first_time = strtotime($row['dt_first']);
    $dt_first_date = date('m-d-y', $dt_first_time);
    print('<div class="date"><div class="mailingID">
           <div>Mailing ID: ' . $row['mailing_id'] .' </div>
           <div class="text" style="margin:3px 0;"><span style="font-weight:bold">Submission Date: </span>'. $dt_first_date .'</div>
           <div class="text">'. $row['category'] .'</div>
           </div>');
    $stats = array();
    foreach (array_keys($eventTypes) as $eventType) {
      if (isset($mailing[$eventType]['count'])) {
        $stats[$eventType] = $mailing[$eventType]['count'];
      }
      else {
        $stats[$eventType] = 0;
      }
      $totals[$eventType] += $stats[$eventType];
    }
    displayEventStats($stats);
    print('</div>');
  }

  print('<div class="date"><div class="mailingID"><div>Mailing ID: Total</div></div>');
  displayEventStats($totals);
  print('</div>');
  print('</div>');
} // displaySenatorStats()

?>
