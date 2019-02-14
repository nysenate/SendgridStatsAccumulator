<?php
require_once('SenLDAP.class.php');

if ($argc != 2) {
  die("Usage: ${argv[0]} username\n");
}

$user = $argv[1];

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

$pass = readline("Enter password for [$user]: ");

$nyssLdap = new SenLDAP();

try {
  $nyssLdap->bind($ldapCfg['host'], $ldapCfg['port'], $ldapCfg['binddn'], $ldapCfg['bindpw']);
  $nyssLdap->authenticate($user, $pass, $ldapCfg['userattr'], $ldapCfg['basedn']);
  $userGroups = $nyssLdap->getGroups();
  $nyssLdap->logout();
  echo "Login OK.  Loaded groups for user [$user]:\n";
  print_r($userGroups);
  exit(0);
}
catch (Exception $e) {
  echo "ERROR: ".$e->getMessage()."\n";
  $nyssLdap->logout();
  exit(1);
}

