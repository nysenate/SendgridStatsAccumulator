<?php
error_reporting(E_ALL ^ E_NOTICE);

$config = parse_ini_file('../../config.ini',true);
$db_host = $config['database']['host'].":".$config['database']['port'];
$db_user = $config['database']['user'];
$db_pwd  = $config['database']['pass'];
$database = $config['database']['name'];
$dbLink = mysql_connect($db_host, $db_user, $db_pwd);
if (!$dbLink)
    die("Can't connect to database");

if (!mysql_select_db($database))
    die("Can't select database");

function getCountBySenatorQuery()
{
    $date4_default = (isset($_POST['date4']) ? $_POST['date4'] : date('Y-m-d')); 
    $date3_default = (isset($_POST['date3']) ? $_POST['date3'] : date('Y-m-d', strtotime('-4 week'))); 
    $senatorsQuery = mysql_query("
    select instance, category, event.mailing_id, event, count(id)  as total
        from event
        join ( select DISTINCT e.mailing_id AS mid
        from event as e
            where e.install_class='prod'
            and e.timestamp > '".$date3_default." 00:00:00'
            and e.timestamp < '".$date4_default." 23:59:59'
            and e.event = 'processed' ) AS mailing
        ON event.mailing_id = mailing.mid
        where instance <> '' and instance <> 'sd99' and instance <> 'Training1' and install_class='prod'
        and (
        instance = 'lanza' or
        instance = 'golden' or
        instance = 'martins' or
        instance = 'hannon' or
        instance = 'marcellino' or
        instance = 'ojohnson' or
        instance = 'zeldin' or
        instance = 'flanagan' or
        instance = 'lavelle' or
        instance = 'fuschillo' or
        instance = 'skelos' or
        instance = 'bonacic' or
        instance = 'larkin' or
        instance = 'farley' or
        instance = 'mcdonald' or
        instance = 'lettle' or
        instance = 'saland' or
        instance = 'ball' or
        instance = 'griffo' or
        instance = 'defrancisco' or
        instance = 'seward' or
        instance = 'libous' or
        instance = 'omara' or
        instance = 'nozzolio' or
        instance = 'ritchie' or
        instance = 'robach' or
        instance = 'alesi' or
        instance = 'young' or
        instance = 'galavan' or
        instance = 'grisanti' or
        instance = 'ransenhofer' or
        instance = 'maziarz') 
    group by instance, mailing_id, event");
    getDataSenatorTable($senatorsQuery);
}
function printItems($dataOutput, $arrayType)
{
    $typeArray = array("bounce","click","deferred","delivered","dropped","open","processed","spamreport","unsubscribe");
    foreach($typeArray as $value)
    {
        print('<div class="item">'); 
            print('<div>');
            print($value);
            print('</div>');
            print('<div>'); 
            print($dataOutput[$value][$arrayType]);
            print('</div>');
        print('</div>'); 
    }
}
function totalSenator($data,$value)
{
    unset($dataOutput);
    $dataOutput = array();
    $dataOutput['bounce']['name'] = 'bounce';
    $dataOutput['click']['name'] = 'click';
    $dataOutput['deferred']['name'] = 'deferred';
    $dataOutput['delivered']['name'] = 'delivered';
    $dataOutput['dropped']['name'] = 'dropped';
    $dataOutput['open']['name'] = 'open';
    $dataOutput['processed']['name'] = 'processed';
    $dataOutput['spamreport']['name'] = 'spamreport';
    $dataOutput['unsubscribe']['name'] = 'unsubscribe';
    for($i=1;$i < count($data); $i++) {
        if($data[$i]['instance'] == $value){
            /* This happens at the beginning of each individual instance */
            if(($data[$i-1]['instance'] != $data[$i]['instance']) && isset($data[$i]['instance']) )
            {
                $dataOutput['bounce']['total'] = 0;
                $dataOutput['click']['total'] = 0;
                $dataOutput['deferred']['total'] = 0;
                $dataOutput['delivered']['total'] = 0;
                $dataOutput['dropped']['total'] = 0;
                $dataOutput['open']['total'] = 0;
                $dataOutput['processed']['total'] = 0;
                $dataOutput['spamreport']['total'] = 0;
                $dataOutput['unsubscribe']['total'] = 0;
            }
            /* This happens at the beginning of each individual mailing */
            if(
                ($data[$i-1]['mailing_id'] != $data[$i]['mailing_id']) || 
                (
                    ($data[$i-1]['instance'] != $data[$i]['instance']) && ($data[$i-1]['mailing_id'] == $data[$i]['mailing_id'])
                )  && isset($data[$i]['mailing_id']))
            {
                print('<div class="date"><div class="mailingID"><div>Mailing ID: ' . $data[$i]['mailing_id'] .' </div><div class="text">'. $data[$i]['category'] .'</div></div>');
                $dataOutput['bounce']['value'] = 0;
                $dataOutput['click']['value'] = 0;
                $dataOutput['deferred']['value'] = 0;
                $dataOutput['delivered']['value'] = 0;
                $dataOutput['dropped']['value'] = 0;
                $dataOutput['open']['value'] = 0;
                $dataOutput['processed']['value'] = 0;
                $dataOutput['spamreport']['value'] = 0;
                $dataOutput['unsubscribe']['value'] = 0;
            }
            /*this happens inside the mailings*/
            switch($data[$i]['event'])
            {
                case "bounce":  $dataOutput['bounce']['value'] += $data[$i]['total'];$dataOutput['bounce']['total'] += $data[$i]['total']; break;
                case "click": $dataOutput['click']['value'] += $data[$i]['total'];$dataOutput['click']['total'] += $data[$i]['total']; break;
                case "deferred": $dataOutput['deferred']['value'] += $data[$i]['total'];$dataOutput['deferred']['total'] += $data[$i]['total']; break;
                case "delivered": $dataOutput['delivered']['value'] += $data[$i]['total'];$dataOutput['delivered']['total'] += $data[$i]['total']; break;
                case "dropped": $dataOutput['dropped']['value'] += $data[$i]['total'];$dataOutput['dropped']['total'] += $data[$i]['total']; break;
                case "open": $dataOutput['open']['value'] += $data[$i]['total'];$dataOutput['open']['total'] += $data[$i]['total']; break;
                case "processed": $dataOutput['processed']['value'] += $data[$i]['total'];$dataOutput['processed']['total'] += $data[$i]['total']; break;
                case "spamreport": $dataOutput['spamreport']['value'] += $data[$i]['total'];$dataOutput['spamreport']['total'] += $data[$i]['total']; break;
                case "unsubscribe": $dataOutput['unsubscribe']['value'] += $data[$i]['total'];$dataOutput['unsubscribe']['total'] += $data[$i]['total']; break;
            }
            /*this closes each mailing*/
            if(($data[$i+1]['mailing_id'] != $data[$i]['mailing_id']) && isset($data[$i]['mailing_id']) )
            {
                printItems($dataOutput, 'value');
                print('</div>');
            }
            /*this closes each instance*/
            if(($data[$i]['instance'] != $data[$i+1]['instance']) && isset($data[$i]['instance']) )
            {
                print('<div class="date"><div class="mailingID"><div>Mailing ID: Total </div></div>');
                printItems($dataOutput, 'total');
            }
            
        }
    }
}
function getDataSenatorTable($datesQuery)
{
    $i=0;
    $data[] = array();
    while($row = mysql_fetch_array($datesQuery)) {
        $uniqueDates[$i] = $row[0];
        $i++;
        $data[] = $row;
    }
    //print_r($data);
    $results = array_unique($uniqueDates);
    $j = 0;
    foreach ($results as $senator) {
        print('<div class="result">');
            print('<div class="senatorName">'. $senator . '</div>');
            totalSenator($data,$senator);
         print('</div>');
    }

}
?>
<html>
<head>
<Style>
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
    getCountBySenatorQuery();
?>
<?php
mysql_close($dbLink);
?>
</body>
</html>