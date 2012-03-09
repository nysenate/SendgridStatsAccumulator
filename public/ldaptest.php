<?php
error_reporting(E_ERROR | E_PARSE);
session_start();
//build config info here
isset($ldaphost) ? $ldaphost : $ldaphost= "webmail.senate.state.ny.us";
isset($ldapport) ? $ldapport : $ldapport= "389";

$ldapuname = $_POST['loginname'];
$ldappass= $_POST['loginpass'];
$ldapattempted= $_POST['attempted'];

$ldapconn = ldap_connect($ldaphost, $ldapport) or die('Could not connect to ' . $ldaphost);

if(!empty($ldapuname) && !empty($ldappass) )
{
	if($ldapconn){
		$ldapbind = ldap_bind($ldapconn, $ldapuname, $ldappass) or trigger_error('Could not bind to '.$ldaphost);
		if($ldapbind){
			$_SESSION['loginname'] = $_POST['loginname'];
			$_SESSION['loginpass'] = $_POST['loginpass'];
			header( 'Location: stats/index.php' ) ;
		}
		else {
			print('<script>alert("Username and/or Password are incorrect
				");</script>');
		}
	}
}
elseif ($ldapattempted == 1)
{
	(print('<script>alert("Cannot leave fields blank");</script>'));
}
?>
<html>
<head>

</head>
<body>
	<h1  style="text-align:center;">
		Sendgrid Stats Accumulator Login
	</h1>
	<h3  style="text-align:center;">
		Use your LDAP/Lotus Notes username and password.
	</h3>
	<div style="text-align:center">
		<form action="" method="post">
			<div style="text-align:center;">username: <input type="text" name="loginname" > </div>
			<div style="text-align:center;">password: <input type="password" name="loginpass"> </div>
			<input type="hidden" name="attempted" value="1">
			<div style="text-align:center;"><input type="submit" value="submit"> </div>
	</div>
</body>
</html>