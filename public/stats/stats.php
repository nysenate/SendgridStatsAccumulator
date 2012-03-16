<?php
session_start();
error_reporting(E_ALL ^ E_NOTICE);
if(!@file_exists('../../resources/summary.php') ) {
    echo 'Error';
} else {
   include('../../resources/summary.php');
}
isset($_POST['doLogout']) ? $logout = $_POST['doLogout'] : $logout = '';
if($logout == 1)
{
  session_unset();
  session_destroy();
  header('Location: ../index.php');
}

//Basically we're asking if you have a timestamp for the session... if you enter the page without it (and don't have a session) or the session is timedout (which happens after 10 minutes), you'll be kicked out for session timeout, to prevent unauthorized entry
if(!isset($_SESSION['allclear']))
{
  if(!isset($_SESSION['loginname']))
  {
    //just a last check to see if you entered with an all clear, but no-user/pass combo
    $_SESSION['kickbackerror'] = "Bad Login/User";
    header('Location: ../index.php');
  }
  else{
    //sets a timestamp on page init
    $_SESSION['allclear'] = time();
  }
}
//if refreshed (like, when you're changing dates/times), it will ask you for your timestamp
if(isset($_SESSION['allclear']))
{
  //10 minutes
  if($_SESSION['allclear'] < time() - 1800)
  {
     unset($_SESSION['allclear']);
     $_SESSION['kickbackerror'] = "Session Timeout";
     header('Location: ../index.php');
  }
  //regenerates the timestamp
  else{
    unset($_SESSION['allclear']);
    $_SESSION['allclear'] = time();
  }
}
//refreshes the page in 30 minutes and 5 seconds to invoke timeout
header("refresh:1805");

?>
<html>
<head>
<style>
html, body, div, span, applet, object, iframe,
h1, h2, h3, h4, h5, h6, p, blockquote, pre,
a, abbr, acronym, address, big, cite, code,
del, dfn, em, img, ins, kbd, q, s, samp,
small, strike, strong, sub, sup, tt, var,
b, u, i, center,
dl, dt, dd, ol, ul, li,
fieldset, form, label, legend,
table, caption, tbody, tfoot, thead, tr, th, td,
article, aside, canvas, details, embed, 
figure, figcaption, footer, header, hgroup, 
menu, nav, output, ruby, section, summary,
time, mark, audio, video {
    margin: 0;
    padding: 0;
    border: 0;
    font-size: 100%;
    font: inherit;
    vertical-align: baseline;
}
/* HTML5 display-role reset for older browsers */
article, aside, details, figcaption, figure, 
footer, header, hgroup, menu, nav, section {
    display: block;
}
body {
    line-height: 1; font-family:arial, verdana, sans-serif; font-size:14px; padding:10px; width:960px; margin: 0 auto;
}
ol, ul {
    list-style: none;
}
.header {font-size:24px; padding:10px;}
.result {text-transform: capitalize; width:900px; clear:both;}
.senatorName {font-size:18px; padding-bottom:5px; margin-bottom:5px; border-bottom:1px solid black;}
.item {padding:5px; text-align:center; width:70px; float:left;}
.item div {margin-bottom:5px;}
.date .mailingID {font-weight: bold; float:left; width: 150px; margin-right:10px; margin-bottom:5px;}
.date .mailingID .text {font-weight:normal; font-size:10px;}
.date {clear:both;}
.dateRange {text-align:left; padding:10px; clear:both;}
.dateRange span {}
</style>
<link href="calendar.css" rel="stylesheet" type="text/css">
<script language="javascript" src="calendar.js"></script>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
<script>
function getAllSenatorTotal()
{
  document.write('<div class="result"><div class="senatorName">Total</div><div class="date"><div class="mailingID"><div>Totals</div><div class="text">Amongst All Senators</div></div>');
    var arrayTypes = new Array("processed","delivered","dropped","deferred","bounce","open","click","spamreport","unsubscribe");
    for(var i=0;i < arrayTypes.length; i++)
    {
      var itemProcessed = new Array();
      var selectorName = '.item .'+arrayTypes[i]+'.total';
      $(selectorName).each(function(i){
        itemProcessed[i] = $(this).html();
      })
      var itemProcessedTotal = 0;
      for(var j=0;j < itemProcessed.length; j++)
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
<form action="index.php" method="post">
<?php
//get class into the page

require_once('../../lib/tc_calendar.php');

//instantiate class and set properties
    
      $date4_default = (isset($_POST['date4']) ? $_POST['date4'] : date('Y-m-d')); 
      $date3_default = (isset($_POST['date3']) ? $_POST['date3'] : date('Y-m-d', strtotime('-4 week'))); 
      $myCalendar = new tc_calendar("date3", true, false);
      $myCalendar->setIcon("iconCalendar.gif");
      $myCalendar->setDate(date('d', strtotime($date3_default))
            , date('m', strtotime($date3_default))
            , date('Y', strtotime($date3_default)));
      $myCalendar->setPath("/stats/");
      $myCalendar->setYearInterval(1970, 2020);
      $myCalendar->setAlignment('left', 'bottom');
      $myCalendar->setDatePair('date3', 'date4', $date4_default);
      print('<div style="float:left; padding:10px;"><div style="float:left;">From: </div>');
      $myCalendar->writeScript();
      print('</div>');
      $myCalendar = new tc_calendar("date4", true, false);
      $myCalendar->setIcon("iconCalendar.gif");
      $myCalendar->setDate(date('d', strtotime($date4_default))
           , date('m', strtotime($date4_default))
           , date('Y', strtotime($date4_default)));
      $myCalendar->setPath("/stats/");
      $myCalendar->setYearInterval(1970, 2020);
      $myCalendar->setAlignment('left', 'bottom');
      $myCalendar->setDatePair('date3', 'date4', $date3_default);
      print('<div style="float:left; padding:10px;"><div style="float:left;">To: </div>');
      $myCalendar->writeScript();
      print('</div>');
?>
<input type="submit" style="float:left; margin-left:25px; margin-top:7px;" />
    <form action="" method="post">
      <input type="hidden" name="doLogout" value="1">
      <input type="submit" value="Log Out" style="float:left; margin-left:25px; margin-top:7px;">
    </form>
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
<?php
    getCountBySenatorQuery($instanceList);
    mysql_close($dbLink);
?>
<script>
getAllSenatorTotal();
</script>
</body>
</html>
