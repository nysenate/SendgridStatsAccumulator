<?php
error_reporting(E_ERROR);
require('../../resources/ldap.php');
//build config info here
session_start();
$ldapkey = new ldapinit;
$config = parse_ini_file('../../config.ini', true);
$ldapkey->ldaphost = $config['ldapkeys']['host'];
$ldapkey->ldapport = $config['ldapkeys']['port'];
$ldapkey->ldapuname = isset($_POST['loginname']) ? $_POST['loginname'] : '';
$ldapkey->ldappass = isset($_POST['loginpass']) ? $_POST['loginpass'] : '';
$ldapkey->ldapattempted = isset($_POST['attempted']) ? $_POST['attempted'] : '0';
$ldapkey->ldapfilter = "(sn=".$ldapkey->ldapuname."*)";
$ldapkey->ldap_connection_script('stats.php');
?>
<html>
<head>

</head>
<body>
  <?php
    $error_kickback_popup = new alert_kickback;
    $error_kickback_popup->display_error_message();
  ?>
  <h1  style="text-align:center;">
    Sendgrid Stats Accumulator Login
  </h1>
  <h3  style="text-align:center;">
    Use your Lotus Notes username and password.
  </h3>
  <div style="text-align:center">
    <form action="" method="post">
      <div style="text-align:center;">Username: <input type="text" name="loginname" > </div>
      <div style="text-align:center;">Password: <input type="password" name="loginpass"> </div>
      <input type="hidden" name="attempted" value="1">
      <div style="text-align:center;"><input type="submit" value="submit"> </div>
    </form
  </div>
</body>
</html>
