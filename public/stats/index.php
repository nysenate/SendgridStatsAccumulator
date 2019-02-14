<?php
error_reporting(E_ERROR);
require_once('SenLDAP.class.php');

//build config info here
session_start();
$scriptPopupMsg = '';

$cfg = parse_ini_file('../../config.ini', true);
if ($cfg === false) {
  die('Unable to parse config file');
}

if (!isset($cfg['ldap'])) {
  die('Config file must have [ldap] section');
}

$ldapCfg = $cfg['ldap'];
if (!isset($ldapCfg['host'])) {
  die('LDAP hostname must be set in config file');
}

foreach (['host','port','binddn','bindpw','basedn','userattr'] as $param) {
  if (!array_key_exists($param, $ldapCfg)) {
    $ldapCfg[$param] = null;
  }
}

if (isset($_SESSION['groups']) && isset($_SESSION['config'])) {
  header('Location: stats.php');
}
else if (isset($_POST['loginname']) && isset($_POST['loginpass'])) {
  $user = $_POST['loginname'];
  $pass = $_POST['loginpass'];
  if (!$user || !$pass) {
    $scriptPopupMsg = "Username and/or password cannot be blank";
  }
  else {
    $nyssLdap = new SenLDAP();
    try {
      $nyssLdap->bind($ldapCfg['host'], $ldapCfg['port'], $ldapCfg['binddn'], $ldapCfg['bindpw']);
      $nyssLdap->authenticate($user, $pass, $ldapCfg['userattr'], $ldapCfg['basedn']);
      $userGroups = $nyssLdap->getGroups();
      $nyssLdap->logout();
      $_SESSION['config'] = $cfg;
      $_SESSION['groups'] = $userGroups;
      header('Location: stats.php');
    }
    catch (Exception $e) {
      $scriptPopupMsg = $e->getMessage();
      $nyssLdap->logout();
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
