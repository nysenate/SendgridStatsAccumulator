<?php
//*************************************
// Date handling class for tc_calendar
// for php version lower than 5.3.0
// written by TJ @triconsole
//*************************************

class tc_date_main{
    protected $mydate;

    public function __construct(){
        $this->mydate = strtotime(date('Y-m-d'));
    }

    public function getDayOfWeek($cdate = ""){
        if(($cdate != "" && $this->validDate($cdate)) || $cdate == ""){
            $tmp_date = ($cdate != "") ? strtotime($cdate) : $this->mydate;
            return date('w', $tmp_date);
        }
    }

    public function getWeekNumber($cdate = ""){
        if(($cdate != "" && $this->validDate($cdate)) || $cdate == ""){
            $tmp_date = ($cdate != "") ? strtotime($cdate) : $this->mydate;
            return date('W', $tmp_date);
        }
    }

    public function setDate($sdate){
        if($this->validDate($sdate))
            $this->mydate = strtotime($sdate);
    }

    public function getDate($format = "Y-m-d", $cdate = ""){
        if(($cdate != "" && $this->validDate($cdate)) || $cdate == ""){
            $tmp_date = ($cdate != "") ? strtotime($cdate) : $this->mydate;
            return date($format, $tmp_date);
        }else return "";
    }

    public function setTimestamp($stime){
        $this->mydate = $stime;
    }

    public function getTimestamp($cdate = ""){
        if(($cdate != "" && $this->validDate($cdate)) || $cdate == ""){
            $tmp_date = ($cdate != "") ? strtotime($cdate) : $this->mydate;
            return $tmp_date;
        }else return 0;
    }

    public function getDateFromTimestamp($stime, $format = "Y-m-d"){
        if($stime && $stime > 0){
            return date($format, $stime);
        }else return "0000-00-00";
    }

    public function addDay($format, $timespan, $cdate = ""){
        if(($cdate != "" && $this->validDate($cdate)) || $cdate == ""){
            $tmp_date = ($cdate != "") ? strtotime($cdate) : $this->mydate;
            return date($format, mktime(0,0,0,date('m', $tmp_date),(date('d', $tmp_date)+$timespan),date('Y', $tmp_date)));
        }else return "0000-00-00";
    }

    public function addMonth($format, $timespan, $cdate = ""){
        if(($cdate != "" && $this->validDate($cdate)) || $cdate == ""){
            $tmp_date = ($cdate != "") ? strtotime($cdate) : $this->mydate;
            return date($format, mktime(0,0,0,(date('m', $tmp_date)+$timespan),date('d', $tmp_date),date('Y', $tmp_date)));
        }else return "0000-00-00";
    }

    public function addYear($format, $timespan, $cdate = ""){
        if(($cdate != "" && $this->validDate($cdate)) || $cdate == ""){
            $tmp_date = ($cdate != "") ? strtotime($cdate) : $this->mydate;
            return date($format, mktime(0,0,0,date('m', $tmp_date),date('d', $tmp_date),(date('Y', $tmp_date)+$timespan)));
        }else return "0000-00-00";
    }

    //return the number of day different between date1 and date2
    //if date1 omitted use set date
    public function differentDate($date2, $date1 = ""){
        if($this->validDate($date2)){
            $date1 = ($date1 != "") ? strtotime($date1) : $this->mydate;

            $date_diff = $date1-strtotime($date2);
            return abs($date_diff);
        }else return false;
    }

    //check if date1 is before date2
    //if date1 omitted use set date
    public function dateBefore($date2, $date1 = "", $equal = true){
        if($this->validDate($date2)){
            $date1 = ($date1 != "") ? strtotime($date1) : $this->mydate;
            $date2 = strtotime($date2);
            return ($equal) ? $date1<=$date2 : $date1<$date2;
        }else return false;
    }

    //check if date1 is after date2
    //if date1 omitted use set date
    public function dateAfter($date2, $date1 = "", $equal = true){
        if($this->validDate($date2)){
            $date1 = ($date1 != "") ? strtotime($date1) : $this->mydate;
            $date2 = strtotime($date2);
            return ($equal) ? $date1>=$date2 : $date1>$date2;
        }else return false;
    }

    public function validDate($date_str){
        if($date_str != ""){
            $date_arr = explode("-", $date_str, 3);

            if((isset($date_arr[0]) && is_numeric($date_arr[0])) && (isset($date_arr[1]) && is_numeric($date_arr[1])) && (isset($date_arr[2]) && is_numeric($date_arr[2]))){
                return (checkdate($date_arr[1], $date_arr[2], $date_arr[0])) ? true : false;
            }else return false;
        }else return false;
    }
}
?>
