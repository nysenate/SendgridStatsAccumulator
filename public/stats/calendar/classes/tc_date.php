<?php
//*************************************
// Date handling class for tc_calendar
// for php version higher than 5.3.0
// written by TJ @triconsole
//*************************************

require_once('tc_date_main.php');

class tc_date extends tc_date_main{
    public $compatible;

    public function __construct(){
        //check if we should use DateTime that comes with 5.3.0 and later
        if (version_compare(PHP_VERSION, '5.3.0') <= 0) {
            $this->compatible = false;
        }else $this->compatible = true;

        if(!$this->compatible){
            $this->tc_date_main();
        }else{
            $this->mydate = new DateTime('now');
        }
    }

    public function getDayOfWeek($cdate = ""){
        if(!$this->compatible){
            return tc_date_main::getDayOfWeek($cdate);
        }else{
            if(($cdate != "" && $this->validDate($cdate)) || $cdate == ""){
                $tmp_date = ($cdate != "") ? new DateTime($cdate) : $this->mydate;
                return $tmp_date->format('w');
            }else return "";
        }
    }

    public function getWeekNumber($cdate = ""){
        if(!$this->compatible){
            return tc_date_main::getWeekNumber($cdate);
        }else{
            if(($cdate != "" && $this->validDate($cdate)) || $cdate == ""){
                $tmp_date = ($cdate != "") ? new DateTime($cdate) : $this->mydate;
                return $tmp_date->format('W');
            }else return "";
        }
    }

    public function setDate($sdate){
        if(!$this->compatible){
            tc_date_main::setDate($sdate);
        }else{
            if(tc_date_main::validDate($sdate))
                $this->mydate = new DateTime($sdate);
        }
    }

    public function getDate($format = "Y-m-d", $cdate = ""){
        if(!$this->compatible){
            return tc_date_main::getDate($format, $cdate);
        }else{
            if(($cdate != "" && $this->validDate($cdate)) || $cdate == ""){
                $tmp_date = ($cdate != "") ? new DateTime($cdate) : $this->mydate;
                return $tmp_date->format($format);
            }else return "";
        }
    }

    public function setTimestamp($stime){
        if(!$this->compatible){
            tc_date_main::setTimestamp($stime);
        }else{
            $this->mydate->setTimestamp($stime);
        }
    }

    public function getTimestamp($cdate = ""){
        if(!$this->compatible){
            return tc_date_main::getTimestamp($cdate);
        }else{
            if(($cdate != "" && $this->validDate($cdate)) || $cdate == ""){
                $tmp_date = ($cdate != "") ? new DateTime($cdate) : $this->mydate;
                return $tmp_date->getTimestamp();
            }else return 0;
        }
    }

    public function getDateFromTimestamp($stime, $format = 'Y-m-d'){
        if($stime){
            if(!$this->compatible){
                return tc_date_main::getDateFromTimestamp($stime, $format);
            }else{
                $tmp_date = new DateTime();
                $tmp_date->setTimestamp($stime);
                return $tmp_date->format($format);
            }
        }else return "";
    }

    public function addDay($format, $timespan, $cdate = ""){
        if(!$this->compatible){
            return tc_date_main::addDay($format, $timespan, $cdate);
        }else{
            $timespan = "P".$timespan."D";
            return $this->addDate($format, $timespan, $cdate);
        }
    }

    public function addMonth($format, $timespan, $cdate = ""){
        if(!$this->compatible){
            return tc_date_main::addMonth($format, $timespan, $cdate);
        }else{
            $timespan = "P".$timespan."M";
            return $this->addDate($format, $timespan, $cdate);
        }
    }

    public function addYear($format, $timespan, $cdate = ""){
        if(!$this->compatible){
            return tc_date_main::addYear($format, $timespan, $cdate);
        }else{
            $timespan = "P".$timespan."Y";
            return $this->addDate($format, $timespan, $cdate);
        }
    }

    public function addDate($format, $timespan, $cdate = ""){
        if($this->compatible){
            $tmp_date = ($cdate != "") ? new DateTime($cdate) : $this->mydate;
            $tmp_date->add(new DateInterval($timespan));
            return $tmp_date->format($format);
        }else return "0000-00-00";
    }

    //return the number of day different between date1 and date2
    //if date1 omitted use set date
    public function differentDate($date2, $date1 = ""){
        if(!$this->compatible){
            return tc_date_main::differentDate($date2, $date1);
        }else{
            $date1 = ($date1 != "") ? $date1 : $this->getDate('Y-m-d');

            $date1 = new DateTime($date1);
            $date2 = new DateTime($date2);
            $interval = $date1->diff($date2, true);
            return $interval->format('%a');
        }
    }

    //check if date1 is before date2
    //if date1 omitted use set date
    public function dateBefore($date2, $date1 = "", $equal = true){
        if(!$this->compatible){
            return tc_date_main::dateBefore($date2, $date1, $equal);
        }else{
            $date1 = ($date1 != "") ? $date1 : $this->getDate('Y-m-d');

            $date1 = new DateTime($date1);
            $date2 = new DateTime($date2);
            return ($equal) ? $date1<=$date2 : $date1<$date2;
        }
    }

    //check if date1 is after date2
    //if date1 omitted use set date
    public function dateAfter($date2, $date1 = "", $equal = true){
        if(!$this->compatible){
            return tc_date_main::dateAfter($date2, $date1, $equal);
        }else{
            $date1 = ($date1 != "") ? $date1 : $this->getDate('Y-m-d');

            $date1 = new DateTime($date1);
            $date2 = new DateTime($date2);
            return ($equal) ? $date1>=$date2 : $date1>$date2;
        }
    }
}
?>
