<?php
//alert function
class alert_kickback{
	var $error_message;
	function create_error_message()
	{
 		$_SESSION['kickbackerror'] = $this->error_message;
	}
	function unset_session_var($toUnset)
	{
		unset($_SESSION[$toUnset]);
	}
	function redirect_to_page($direction)
	{
		header('Location: '. $direction);
	}
	function display_error_message()
	{

		if(isset($_SESSION['kickbackerror']))
		{
			print('<script>alert("'.$_SESSION['kickbackerror'].'");</script>');
			unset($_SESSION['kickbackerror']);
		}
	}

}

/*
session_start();
$ldapkey = new ldapinit;
$config = parse_ini_file('../../config.ini', true);
$ldapkey->ldaphost = $config['ldapkeys']['host'];
$ldapkey->ldapport = $config['ldapkeys']['port'];
$ldapkey->ldapuname = isset($_POST['loginname']) ? $_POST['loginname'] : '';
$ldapkey->ldappass = isset($_POST['loginpass']) ? $_POST['loginpass'] : '';
$ldapkey->ldapattempted = isset($_POST['attempted']) ? $_POST['attempted'] : '0';
$ldapkey->ldapfilter = "(sn=".$ldapkey->ldapuname."*)";
//stats.php is location to go to after connection set
$ldapkey->ldap_connection_script('stats.php');
*/
class ldapinit{
	var $ldaphost;
	var $ldapport;
	var $ldapuname;
	var $ldappass;
	var $ldapattempted;
	var $ldapdn = '';
	var $ldapattributes = array("gidnumber");
	var $ldapfilter;
	function ldap_connection_script($location){
		$ldapconn = ldap_connect($this->ldaphost, $this->ldapport) or die('Could not connect to ' . $this->ldaphost);
		//when post is submitted, it reads the non-empty variables and tries to connect
		if (!empty($this->ldapuname) && !empty($this->ldappass)) {
			//print('upassed');
			//verifies connection
			if ($ldapconn) {
			//verifies bind (which means -u and -p are both correct). If it fails, it throws up a warning, which is why warnings are disabled on this page
			    $ldapbind = ldap_bind($ldapconn, $this->ldapuname, $this->ldappass);
			    if ($ldapbind) {
			      //Does a search on the -u/-p combination for group id number
			      $sr = ldap_search($ldapconn, $this->ldapdn, $this->ldapfilter, $this->ldapattributes);
			      //Gets the entries & reads their length. Each array starts with a namespace and then gives the data, hence the -1 to move the cursor up one
			      $entry = ldap_get_entries($ldapconn, $sr);
			      var_dump($entry);
			      $gidlength = count($entry[0]['gidnumber']) - 1;
			      $groupnamearray = array();

			      for ($i = 0; $i < $gidlength; $i++) {
			        $gidnumber = $entry[0]['gidnumber'][$i];
			        //print('GidNumber: ' . $gidnumber . '<br>');
			        //and then you do a second search for the group names, which returns ALL of the group names that you're looking at
			        $searchresult = ldap_search($ldapconn, " ", "(&(objectClass=groupOfNames)(gidnumber=".$gidnumber."))", array("displayname"));
			        //var_dump($searchresult);
			        $groupentry = ldap_get_entries($ldapconn, $searchresult);
			        $groupnamearray[] = $groupentry[0]['displayname'][0];
			      }

			      //var_dump($tovardup);
			      //opens a session to pass the variables over, and then we go to index.php for authorization
			      $_SESSION['groupnamearray'] = $groupnamearray;
			      $_SESSION['loginname'] = $_POST['loginname'];
			      //$_SESSION['loginpass'] = $_POST['loginpass'];
			      header( 'Location: '. $location) ;
			    }
			    else {
			      $error_kickback_init = new alert_kickback;
			      $error_kickback_init->error_message = "Wrong User/Password";
			      $error_kickback_init->create_error_message();

			    }
			}
		}
		elseif ($this->ldapattempted == 1) {
		  $error_kickback_init = new alert_kickback;
		  $error_kickback_init->error_message = "Cannot Leave Field Blank";
		  $error_kickback_init->create_error_message();
		}
	}
}
class ldaplanding{
	//Basically we're asking if you have a timestamp for the session... 
	//if you enter the page without it (and don't have a session) or the session is timedout 
	//(which happens after 10 minutes), you'll be kicked out for session timeout, to prevent unauthorized entry
	function checktimestamped()
	{
		if(!isset($_SESSION['allclear']))
		{
		 	if(!isset($_SESSION['loginname']))
		  	{
			    //just a last check to see if you entered with an all clear, but no-user/pass combo
			    $error_kickback_init = new alert_kickback;
			    $error_kickback_init->error_message = "Session Expired.";
			    $error_kickback_init->create_error_message();
			    $error_kickback_init->redirect_to_page('index.php');
		  	}
			else{
				//sets a timestamp on page init
				$_SESSION['allclear'] = time();
			}
		}
		else{
			//if refreshed (like, when you're changing dates/times), it will ask you for your timestamp
			//of 30 minutes, if it's longer than 30, it fails
			if($_SESSION['allclear'] < time() - 1800)
			{
				$error_kickback_init = new alert_kickback;
				$error_kickback_init->error_message = "Session Expired.";
				$error_kickback_init->create_error_message();
				$error_kickback_init->unset_session_var('allclear');
				$error_kickback_init->redirect_to_page('index.php');
			}
			//regenerates the timestamp
			else{
				unset($_SESSION['allclear']);
				$_SESSION['allclear'] = time();
			}
		}
		//refreshes the page in 30 minutes and 1 seconds to invoke timeout
		header("refresh:1801");
	}
}
?>