<?php

$config = parse_ini_file('../../config.ini', true);
$db_host = $config['database']['host'].":".$config['database']['port'];
$db_user = $config['database']['user'];
$db_pwd  = $config['database']['pass'];
$database = $config['database']['name'];
$instances = $config['viewer']['instances'];

$dbLink = mysql_connect($db_host, $db_user, $db_pwd);
if (!$dbLink) {
    die("Can't connect to database");
}

if (!mysql_select_db($database)) {
    die("Can't select database");
}

//gets a list of instances from config file
//on election, update the config file
//or set a secondary config.ini to be written by
//procuring data from external api on cron
$instanceList = array_map('trim', explode(',', $instances));

//the query to garner data and some other stuff (like setting date ranges via post & instances to receive)
function getCountBySenatorQuery($instanceList)
{
    $date4_default = (isset($_POST['date4']) ? $_POST['date4'] : date('Y-m-d'));
    $date3_default = (isset($_POST['date3']) ? $_POST['date3'] : date('Y-m-d', strtotime('-4 week'))); 

    // Generate SQL for the list of instances to be viewed.
    $instance_in = '';
    foreach ($instanceList as $instance) {
        if (!empty($instance_in)) {
            $instance_in .= ',';
        }
        $instance_in .= "'$instance'";
    }

    $senatorsQuery = mysql_query("
    select instance, category, summary.mailing_id, event, count, dt_first, dt_last
    from summary 
    join (
            select DISTINCT summ.instance, mailing_id, MIN(summ.dt_first) AS start
            from summary as summ
            where summ.install_class='prod'
            and summ.event = 'processed' 
            GROUP by instance, mailing_id
            HAVING start >= '".$date3_default." 00:00:00'
               and start <= '".$date4_default." 23:59:59'
          ) AS mailing
        USING (instance,mailing_id)
    where install_class='prod' and instance in(".$instance_in.")
    group by instance, mailing_id, event;
    ");
    getDataSenatorTable($senatorsQuery);
}
//Prints the individual items of 'bounce, click'... etc in a smaller package.
function printItems($dataOutput, $arrayType)
{
    //this is the order of array amounts, individual changes &/or additions could be added as if statements in the foreach
    $typeArray = array("processed","delivered","dropped","deferred","bounce","open","click","spamreport","unsubscribe");
    foreach($typeArray as $value)
    {
        print('<div class="item">'); 
            print('<div>');
            print($dataOutput[$value]['name']);
            print('</div>');
            print('<div class="'.$value.' '. $arrayType .'">'); 
            print($dataOutput[$value][$arrayType]);
            print('</div>');
        print('</div>'); 
    }
}
//collates and arranges the data and provides the sum of amounts as it traverses the array
function totalSenator($data,$value)
{
    unset($dataOutput);
    $dataOutput = array();
    $dataOutput['bounce']['name'] = 'Bounced';
    $dataOutput['click']['name'] = 'Clicked';
    $dataOutput['deferred']['name'] = 'Deferred';
    $dataOutput['delivered']['name'] = 'Delivered';
    $dataOutput['dropped']['name'] = 'Dropped';
    $dataOutput['open']['name'] = 'Opened';
    $dataOutput['processed']['name'] = 'Processed';
    $dataOutput['spamreport']['name'] = 'Spamreport';
    $dataOutput['unsubscribe']['name'] = 'Unsubscribed';
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
            /* This happens at the beginning of each individual mailing the if (like the closing if below) checks if the previous mailing id is equal to the current one OR is the instance different, but the mailing id the same */
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
            /*this happens inside the mailings so that we can eventually order by whatever we choose above*/
            switch($data[$i]['event'])
            {
                case "bounce":  $dataOutput['bounce']['value'] += $data[$i]['count'];$dataOutput['bounce']['total'] += $data[$i]['count']; break;
                case "click": $dataOutput['click']['value'] += $data[$i]['count'];$dataOutput['click']['total'] += $data[$i]['count']; break;
                case "deferred": $dataOutput['deferred']['value'] += $data[$i]['count'];$dataOutput['deferred']['total'] += $data[$i]['count']; break;
                case "delivered": $dataOutput['delivered']['value'] += $data[$i]['count'];$dataOutput['delivered']['total'] += $data[$i]['count']; break;
                case "dropped": $dataOutput['dropped']['value'] += $data[$i]['count'];$dataOutput['dropped']['total'] += $data[$i]['count']; break;
                case "open": $dataOutput['open']['value'] += $data[$i]['count'];$dataOutput['open']['total'] += $data[$i]['count']; break;
                case "processed": $dataOutput['processed']['value'] += $data[$i]['count'];$dataOutput['processed']['total'] += $data[$i]['count']; break;
                case "spamreport": $dataOutput['spamreport']['value'] += $data[$i]['count'];$dataOutput['spamreport']['total'] += $data[$i]['count']; break;
                case "unsubscribe": $dataOutput['unsubscribe']['value'] += $data[$i]['count'];$dataOutput['unsubscribe']['total'] += $data[$i]['count']; break;
            }
            
            /*this closes each mailing*/
            if(($data[$i+1]['mailing_id'] != $data[$i]['mailing_id']) || 
                (
                    ($data[$i+1]['instance'] != $data[$i]['instance']) && ($data[$i+1]['mailing_id'] == $data[$i]['mailing_id'])
                )  && isset($data[$i]['mailing_id'])) 
            {
                printItems($dataOutput, 'value');
                print('</div>');
            }
            /*this closes each instance*/
            if(($data[$i]['instance'] != $data[$i+1]['instance']) && isset($data[$i]['instance']) )
            {
                print('<div class="date"><div class="mailingID"><div>Mailing ID: Total </div></div>');
                printItems($dataOutput, 'total');
                print('</div>');
            }
            
        }
    }
}
//procures the data from the query and produces the array, breaks it up into instances and then sends the data to be processed per instance
function getDataSenatorTable($datesQuery)
{
    $i=0;
    $data[] = array();
    while($row = mysql_fetch_array($datesQuery)) {
        $uniqueDates[$i] = $row[0];
        $i++;
        $data[] = $row;
    }
    //tests if data has usable length. supplies a length of 1 if there's no data.
    if(count($data) > 1)
    {
        $results = array_unique($uniqueDates);
        $j = 0;
        foreach ($results as $senator) {
            print('<div class="result">');
            print('<div class="senatorName">'. $senator . '</div>');
            totalSenator($data, $senator);
            print('</div>');
        }
    }
    else
    {
        print('No Data Found');
    }
}
?>