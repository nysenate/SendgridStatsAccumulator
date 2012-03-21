<?php
include('../../resources/ldap.php');
session_start();
session_unset();
session_destroy();
session_start();
$logoutinit = new alert_kickback;
$logoutinit->error_message = "Logged Out";
$logoutinit->create_error_message();
$logoutinit->redirect_to_page('index.php');
?>