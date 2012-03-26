<?php
error_reporting(E_ERROR);
require_once('SenLDAP.class.php');

//build config info here
session_start();
$scriptPopupMsg = '';

$config = parse_ini_file('../../config.ini', true);

if (!isset($config['ldap'])) {
  die("Config file must have [ldap] section");
}
else if (!isset($config['ldap']['host'])) {
  die("LDAP hostname must be set in config file");
}

$ldapHost = $config['ldap']['host'];
$ldapPort = (isset($config['ldap']['port'])) ? $config['ldap']['port'] : null;

if (isset($_SESSION['groups']) && isset($_SESSION['config'])) {
  header('Location: stats.php');
}
else if (isset($_POST['loginname']) && isset($_POST['loginpass'])) {
  $user = $_POST['loginname'];
  $pass = $_POST['loginpass'];
  if (!$user || !$pass) {
    $scriptPopupMsg = "Username and/or Password cannot be blank";
  }
  else {
    $nyssLdap = new SenLDAP();
    if (!$nyssLdap->login($user, $pass, $ldapHost, $ldapPort, $err)) {
      $scriptPopupMsg = $err;
    }
    else {
      $userGroups = $nyssLdap->getGroups();
      $nyssLdap->logout();
      $_SESSION['config'] = $config;
      $_SESSION['groups'] = $userGroups;
      header('Location: stats.php');
    }
  }
}
?>
<html>
<head>
<?php
  if ($scriptPopupMsg) {
    print("<script>alert(\"$scriptPopupMsg\");</script>");
  }
?>
</head>
<body>
<h1 style="text-align:center;">Sendgrid Stats Viewer Login</h1>
<h3 style="text-align:center;">Use your Lotus Notes username and password.</h3>
<div style="text-align:center">
<form action="" method="post">
<div style="text-align:center;">
Username: <input type="text" name="loginname">
</div>
<div style="text-align:center;">
Password: <input type="password" name="loginpass">
</div>
<input type="hidden" name="attempted" value="1">
<div style="text-align:center;">
<input type="submit" value="submit">
</div>
</form>
</div>
</body>
</html>
