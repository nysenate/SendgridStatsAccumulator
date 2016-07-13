<?php
session_start();
if (!isset($_SESSION['groups'])) {
  die("You are not authorized to view this page.");
}
require_once('summary.php');
require_once('tc_calendar.php');
define('USE_JAVASCRIPT_TOTALS', false);

$fm_date_start = date('Y-m-d', strtotime('-1 month'));
$fm_date_end = date('Y-m-d');
$fm_instance = '';
$fm_summary = false;

if (isset($_POST['fm_date_start'])) {
  $fm_date_start = $_POST['fm_date_start'];
}
if (isset($_POST['fm_date_end'])) {
  $fm_date_end = $_POST['fm_date_end'];
}
if (isset($_POST['fm_instance'])) {
  $fm_instance = $_POST['fm_instance'];
}
if (isset($_POST['fm_summary']) && $_POST['fm_summary'] == 'on') {
  $fm_summary = true;
}

// Get an array of all instances that the logged in user is allowed to see.
$instanceList = getInstances($_SESSION['config'], $_SESSION['groups']);


function generateCalendarScript($name, $default_date)
{
  $myCalendar = new tc_calendar($name, true, false);
  $myCalendar->setIcon("images/iconCalendar.gif");
  $myCalendar->setDate(date('d', strtotime($default_date)),
                       date('m', strtotime($default_date)),
                       date('Y', strtotime($default_date)));
  $myCalendar->setPath("/stats/");
  $myCalendar->setYearInterval(1970, 2020);
  $myCalendar->setAlignment('left', 'bottom');
  $myCalendar->setDatePair('fm_date_start', 'fm_date_end');
  $myCalendar->writeScript();
} // generateCalendarScript()


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
<meta content="text/html;charset=utf-8" http-equiv="Content-Type"/>
<meta content="utf-8" http-equiv="encoding"/>
<link href="stats.css" rel="stylesheet" type="text/css"/>
<link href="calendar.css" rel="stylesheet" type="text/css"/>
<script language="javascript" src="calendar.js"></script>
<?php
if (USE_JAVASCRIPT_TOTALS) {
?>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js">
</script>
<script>
function getAllSenatorTotal()
{
  document.write('<div class="result"><div class="senatorName">Total</div><div class="date"><div class="mailingID"><div>Totals</div><div class="text">Amongst All Senators</div></div>');
    var arrayTypes = new Array("processed","delivered","dropped","deferred","bounce","open","click","spamreport","unsubscribe");
    for (var i = 0; i < arrayTypes.length; i++)
    {
      var itemProcessed = new Array();
      var selectorName = '.item .'+arrayTypes[i]+'.total';
      $(selectorName).each(function(i){
        itemProcessed[i] = $(this).html();
      })
      var itemProcessedTotal = 0;
      for (var j = 0; j < itemProcessed.length; j++)
      {
        itemProcessedTotal += parseInt(itemProcessed[j]);     
      }
      document.write('<div class="item"><div>'+arrayTypes[i]+'</div>');
      document.write('<div class="'+arrayTypes[i]+' value">'+itemProcessedTotal+'</div></div>');
    }
  document.write('</div></div></div>');
}
</script>
<?php
}
?>
<title>
Sendgrid Stats
</title>
</head>

<body>

<div class="header">
Sendgrid Accumulator Stats

<form action="" method="post">
<div style="float:left; padding:10px;">
<div style="float:left;">From:</div>
<?php generateCalendarScript('fm_date_start', $fm_date_start);?>
</div>

<div style="float:left; padding:10px;">
<div style="float:left;">To:</div>
<?php generateCalendarScript('fm_date_end', $fm_date_end);?>
</div>

<div style="float:left; padding:10px;">
<select name="fm_instance">
<option value="">All Available Senators</option>
<?php
foreach ($instanceList as $instance) {
  $flag = $fm_instance == $instance ? "selected" : "";
  echo "<option value=\"$instance\" $flag>".ucfirst($instance)."</option>\n";
}
?>
</select>
</div>

<div style="float:left; padding:10px;">
<input type="checkbox" name="fm_summary">Summary Only</input>
</div>

<input type="submit" name="fm_submit" style="float:left; margin-left:25px; margin-top:7px;" value="Submit"/>
<input type="submit" name="fm_export" style="float:left; margin-left:25px; margin-top:7px;" value="Export as CSV"/>
</form>

<form action="logout.php" method="post">
  <input type="hidden" name="doLogout" value="1">
  <input type="submit" value="Log Out" style="float:left; margin-left:25px; margin-top:7px;">
</form>

</div>

<div class="dateRange">
Statistics from <?php echo $fm_date_start;?> to <?php echo $fm_date_end;?>
</div>

<!-- kz debugging: group list for logged in user
<?php
  print_r($_SESSION['groups']);
?>
-->
<?php
  if ($fm_instance && in_array($fm_instance, $instanceList)) {
    $instances = array($fm_instance);
  }
  else {
    $instances = $instanceList;
  }
  $stats = getStats($_SESSION['config'], $instances, $fm_date_start, $fm_date_end);
  displayStats($stats, $fm_summary);
?>
<?php
if (USE_JAVASCRIPT_TOTALS) {
?>
<script>
getAllSenatorTotal();
</script>
<?php
}
?>
</body>
</html>
