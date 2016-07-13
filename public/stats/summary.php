<?php
error_reporting(E_ERROR);

$eventTypes = array(
    'processed' => array('label'=>'Processed', 'width'=>70, 'rate'=>null),
    'delivered' => array('label'=>'Delivered', 'width'=>105, 'rate'=>'processed'),
    'dropped' => array('label'=>'Dropped', 'width'=>65, 'rate'=>null),
    'deferred' => array('label'=>'Deferred', 'width'=>65, 'rate'=>null),
    'bounce' => array('label'=>'Bounced', 'width'=>80, 'rate'=>'processed'),
    'open' => array('label'=>'Opened', 'width'=>95, 'rate'=>'delivered'),
    'click' => array('label'=>'Clicked', 'width'=>85, 'rate'=>'delivered'),
    'spamreport' => array('label'=>'Spamreps', 'width'=>65, 'rate'=>null),
    'unsubscribe' => array('label'=>'Unsubs', 'width'=>65, 'rate'=>null)
);
$csvFields = array(
  'name' => 'Senator Name',
  'mailid' => 'Mailing ID',
  'subdate' => 'Submission Date',
  'mailname' => 'Mailer Name',
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



function getStats($cfg, $instances, $dt_start, $dt_end)
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
  SELECT instance, category, summary.mailing_id, event, count, dt_first
  FROM summary
  JOIN (
    SELECT DISTINCT summ.instance, mailing_id, MIN(summ.dt_first) AS start
    FROM summary AS summ
    WHERE summ.install_class='prod'
      AND summ.event = 'processed'
    GROUP BY instance, mailing_id
    HAVING start >= '".$dt_start." 00:00:00'
       AND start <= '".$dt_end." 23:59:59'
  ) AS mailing
  USING (instance, mailing_id)
  WHERE install_class='prod' AND instance IN (".$instance_in.")
  GROUP BY instance, mailing_id, event
  ORDER BY instance ASC, mailing_id DESC;";

  $res = mysql_query($q, $dbcon);

  $stats = array();

  while ($row = mysql_fetch_assoc($res)) {
    $inst = $row['instance'];
    $mid = $row['mailing_id'];
    $ev = $row['event'];
    $cnt = $row['count'];
    $cat = $row['category'];
    $dt = $row['dt_first'];

    if (isset($stats[$inst][$mid])) {
      $stats[$inst][$mid]['events'][$ev] = $cnt;
    }
    else {
      $stats[$inst][$mid] = array('events' => array($ev=>$cnt),
                                  'category' => $cat,
                                  'date' => $dt);
    }
  }

  mysql_close($dbcon);
  return $stats;
} // getStats()

function getExportHeaderFields() {
  global $csvFields, $eventTypes;
  return array_merge(array_values($csvFields), array_column($eventTypes,'label'));
}

function exportStats($stats, $fm_summary = false)
{
  global $eventTypes;
  $ret = false;
  $grand_totals = array();
  $all_stats = array();

  foreach ($eventTypes as $event_type => $event_prop) {
    $grand_totals[$event_type] = 0;
  }

  if (count($stats) > 0) {
    foreach ($stats as $senator => $mailings) {
      // get the stats for an entire senator
      $one_senator_stats = exportSenatorStats($senator, $mailings, $fm_summary);
      // the last row is the totals row for that senator
      $one_senator_totals = array_slice($one_senator_stats,-1)[0];
      // for each data point in the totals, add to the grand totals
      foreach ($eventTypes as $event_type => $event_prop) {
        $grand_totals[$event_type] += $one_senator_totals[$event_type];
      }
      // add the senator's stats to the overall stats
      foreach ($one_senator_stats as $one_mailer) {
        $all_stats[] = $one_mailer;
      }
    }

    // compile the export
    $export = implode(',',getExportHeaderFields()) . "\n";
    foreach ($all_stats as $key=>$val) {
      $export .= implode(',',$val) . "\n";
    }

    // add the final totals row
    $totals_row = array_merge(
      array("All Selected Senators","Grand Total",'',"All Mailings"),
      array_values($grand_totals)
    );
    $export .= implode(',',$totals_row) . "\n";

    header('Content-Type: application/csv');
    header('Content-Transfer-Encoding: Binary');
    header('Content-disposition: attachment; filename="SendGrid-Statistics-' . time() . '.csv"');
    echo $export;
    $ret = true;
  }

  return $ret;
}

function exportSenatorStats($senator, $mailings, $summary = false)
{
  global $eventTypes;

  // initialize the return array
  $export_rows = array();

  // initialize the totals row
  $totals = array();
  foreach(array_keys($eventTypes) as $one_type) {
    $totals[$one_type] = 0;
  }

  // for each mailing, build the entire row and add it to $export_rows
  foreach ($mailings as $mailing_id => $mailing) {
    $category = $mailing['category'];
    $dt_first_date = date('m-d-y', strtotime($mailing['date']));
    $one_row = array($senator, $mailing_id, $dt_first_date, $category);

    $events = $mailing['events'];

    foreach (array_keys($eventTypes) as $eventType) {
      $val = isset($events[$eventType]) ? $events[$eventType] : 0;
      $one_row[] = $val;
      $totals[$eventType] += $val;
    }

    if (!$summary) {
      $export_rows[] = $one_row;
    }
  }

  $export_rows[] = array_merge(array($senator, "$senator Total", '', "All Mailings"), $totals);

  return $export_rows;
} // exportSenatorStats()


function displayStats($stats, $fm_summary = false)
{
  // Confirm that there is at least one senator/mailing/event.
  if (count($stats) > 0) {
    $event_totals = array();
    foreach ($stats as $senator => $mailings) {
      $event_counts = displaySenatorStats($senator, $mailings, $fm_summary);
      foreach ($event_counts as $event_type => $event_count) {
        $event_totals[$event_type] += $event_count;
      }
    }

    print("<div class=\"result\">\n<div class=\"senatorName\">All Selected Senators</div>\n");
    print("<div class=\"date\">\n");
    print('<div class="mailingID"><div>Totals</div><div class="text">Amongst All Senators</div></div>'."\n");
    displayEventStats($event_totals);
    print("</div>\n</div>\n");
  }
  else {
    print('<div class="noData">No Data Found</div>');
  }
} // displayStats()



function displaySenatorStats($senator, $mailings, $summary = false)
{
  global $eventTypes;

  print("<div class=\"result\">\n<div class=\"senatorName\">$senator</div>\n");
  $totals = array();

  foreach ($mailings as $mailing_id => $mailing) {
    $events = $mailing['events'];
    $category = $mailing['category'];
    $dt_first_time = strtotime($mailing['date']);
    $dt_first_date = date('m-d-y', $dt_first_time);

    $stats = array();
    foreach (array_keys($eventTypes) as $eventType) {
      if (isset($events[$eventType])) {
        $stats[$eventType] = $events[$eventType];
      }
      else {
        $stats[$eventType] = 0;
      }
      $totals[$eventType] += $stats[$eventType];
    }

    if (!$summary) {
      print("<div class=\"date\">\n");
      print('<div class="mailingID"><div>Mailing ID: ' . $mailing_id . '</div><div class="text" style="margin:3px 0;"><span style="font-weight:bold">Submission Date: </span>' . $dt_first_date . '</div><div class="text">' . $category . '</div></div>' . "\n");

      displayEventStats($stats);
      print("</div>\n");
    }
  }

  print("<div class=\"date\">\n");
  print("<div class=\"mailingID\"><div>$senator Total</div></div>\n");
  displayEventStats($totals);
  print("</div>\n</div>\n");
  return $totals;
} // displaySenatorStats()



function displayEventStats($events)
{
  global $eventTypes;

  foreach ($eventTypes as $eventType => $eventInfo) {
    $label = $eventInfo['label'];
    $width = $eventInfo['width'];
    $rateBasedOn = $eventInfo['rate'];

    print("<div class=\"item\" style=\"width:${width}px;\">");
    print("<div>$label</div><div class=\"$eventType\">");
    print($events[$eventType]);
    if ($rateBasedOn) {
      print(" (".round(100*$events[$eventType]/$events[$rateBasedOn], 1)."%)");
    }
    print("</div></div>\n");
  }
} // displayEventStats()

?>
