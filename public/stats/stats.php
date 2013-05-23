<?php
session_start();
if (!isset($_SESSION['groups'])) {
  die("You are not authorized to view this page.");
}
require_once('summary.php');
require_once('tc_calendar.php');

$fm_date_start = date('Y-m-d', strtotime('-4 week'));
$fm_date_end = date('Y-m-d');
$fm_instance = '';

if (isset($_POST['fm_date_start'])) {
  $fm_date_start = $_POST['fm_date_start'];
}
if (isset($_POST['fm_date_end'])) {
  $fm_date_end = $_POST['fm_date_end'];
}
if (isset($_POST['fm_instance'])) {
  $fm_instance = $_POST['fm_instance'];
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
<html>
<head>
<link href="stats.css" rel="stylesheet" type="text/css"/>
<link href="calendar.css" rel="stylesheet" type="text/css"/>
<script language="javascript" src="calendar.js"></script>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js">
</script>
<script>
/*written in jquery to run post-load*/
function getAllSenatorTotal()
{
  document.write('<div class="result"><div class="senatorName">Total</div><div class="date"><div class="mailingID"><div>Totals</div><div class="text">Amongst All Senators</div></div>');
    var arrayTypes = new Array("processed","delivered","dropped","deferred","bounce","open","click","spamreport","unsubscribe");
    for (var i=0;i < arrayTypes.length; i++)
    {
      var itemProcessed = new Array();
      var selectorName = '.item .'+arrayTypes[i]+'.total';
      $(selectorName).each(function(i){
        itemProcessed[i] = $(this).html();
      })
      var itemProcessedTotal = 0;
      for (var j=0;j < itemProcessed.length; j++)
      {
        itemProcessedTotal += parseInt(itemProcessed[j]);     
      }
      document.write('<div class="item"><div>'+arrayTypes[i]+'</div>');
      document.write('<div class="'+arrayTypes[i]+' value">'+itemProcessedTotal+'</div></div>');
    }
  document.write('</div></div></div>');
}
</script>
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

<input type="submit" style="float:left; margin-left:25px; margin-top:7px;" />
</form>

<form action="logout.php" method="post">
  <input type="hidden" name="doLogout" value="1">
  <input type="submit" value="Log Out" style="float:left; margin-left:25px; margin-top:7px;">
</form>

</div>

<div class="dateRange">
<span><?php print('Current Query Begins: '. $fm_date_start);?></span>
<span><?php print('and Ends: '. $fm_date_end);?></span>
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
  displayStats($_SESSION['config'], $instances, $fm_date_start, $fm_date_end);
?>
<script>
getAllSenatorTotal();
</script>

</body>
</html>
