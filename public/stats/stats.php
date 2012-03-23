<?php
session_start();
if (!isset($_SESSION['groups'])) {
  die("You are not authorized to view this page.");
}
require_once('summary.php');
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
<form action="stats.php" method="post">
<?php
//get class into the page

require_once('tc_calendar.php');

//instantiate class and set properties
$date4_default = (isset($_POST['date4']) ? $_POST['date4'] : date('Y-m-d')); 
$date3_default = (isset($_POST['date3']) ? $_POST['date3'] : date('Y-m-d', strtotime('-4 week'))); 

$myCalendar = new tc_calendar("date3", true, false);
$myCalendar->setIcon("images/iconCalendar.gif");
$myCalendar->setDate(date('d', strtotime($date3_default)),
                     date('m', strtotime($date3_default)),
                     date('Y', strtotime($date3_default)));
$myCalendar->setPath("/stats/");
$myCalendar->setYearInterval(1970, 2020);
$myCalendar->setAlignment('left', 'bottom');
$myCalendar->setDatePair('date3', 'date4', $date4_default);
print('<div style="float:left; padding:10px;"><div style="float:left;">From: </div>');
$myCalendar->writeScript();
print('</div>');

$myCalendar = new tc_calendar("date4", true, false);
$myCalendar->setIcon("images/iconCalendar.gif");
$myCalendar->setDate(date('d', strtotime($date4_default)),
                     date('m', strtotime($date4_default)),
                     date('Y', strtotime($date4_default)));
$myCalendar->setPath("/stats/");
$myCalendar->setYearInterval(1970, 2020);
$myCalendar->setAlignment('left', 'bottom');
$myCalendar->setDatePair('date3', 'date4', $date3_default);
print('<div style="float:left; padding:10px;"><div style="float:left;">To: </div>');
$myCalendar->writeScript();
print('</div>');
?>
<input type="submit" style="float:left; margin-left:25px; margin-top:7px;" />
</form>
<form action="logout.php" method="post">
  <input type="hidden" name="doLogout" value="1">
  <input type="submit" value="Log Out" style="float:left; margin-left:25px; margin-top:7px;">
</form>
</div>

<div class="dateRange">
    <span>
    <?php print('Current Query Begins: '. $date3_default);?>
    </span>
    <span>
    <?php print('and Ends: '. $date4_default);?>
    </span>
</div>
<!-- kz debugging: group list for logged in user
<?php
  print_r($_SESSION['groups']);
?>
-->
<?php
  $instanceList = getInstances($_SESSION['config'], $_SESSION['groups']);
  displayStats($_SESSION['config'], $instanceList);
?>
<script>
getAllSenatorTotal();
</script>
</body>
</html>
