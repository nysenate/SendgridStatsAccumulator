<?php

class SenLDAP
{
  const DEFAULT_LDAP_PORT = 389;
  const DEFAULT_BASE_DN = '';

  private $ldapConn;
  private $ldapUser;
  private $groupNames;


  function __construct()
  {
    $ldapConn = null;
    $ldapUser = null;
    $groupNames = null;
  }



  function login($user, $pass, $host, $port, &$err)
  {
    if ($this->ldapConn) {
      $err = "You are already logged in.  Please logout first.";
      return false;
    }
    else if (!$host || !$user || !$pass) {
      $err = "Must specify a host, user, and pass.";
      return false;
    }
    else if (!$port) {
      $port = self::DEFAULT_LDAP_PORT;
    }

    $conn = ldap_connect($host, $port);
    if (!$conn) {
      $err = "Unable to connect to LDAP host [$host] on port [$port].";
      return false;
    }

    $ldapBind = ldap_bind($conn, $user, $pass);
    if (!$ldapBind) {
      $err = "Wrong Username and/or Password.";
      ldap_unbind($conn);
      return false;
    }

    // Confirm that the provided username is truly a username.
    $attrs = array('uid', 'gidnumber');
    // BASE_DN must be empty, because o=senate will miss those persons
    // who have the OU attribute in their DN.
    $sr = ldap_list($conn, self::DEFAULT_BASE_DN, "uid=$user", $attrs);
    if (!$sr) {
      $err = "Unable to validate username.";
      ldap_unbind($conn);
      return false;
    }

    $ent_count = ldap_count_entries($conn, $sr);
    if ($ent_count !== 1) {
      $err = "Login [$user] is not a valid username.";
      ldap_unbind($conn);
      return false;
    }

    $ent = ldap_first_entry($conn, $sr);
    $uids = ldap_get_values($conn, $ent, "uid");

    if ($uids['count'] > 1 || $uids[0] != $user) {
      $err = "Provided username does not match looked-up username.";
      ldap_unbind($conn);
      return false;
    }

    $gids = ldap_get_values($conn, $ent, "gidnumber");
    unset($gids['count']);
    $groupNames = $this->convertGroupIdsToNames($conn, $gids);

    $this->ldapConn = $conn;
    $this->ldapUser = $user;
    $this->groupNames = $groupNames;
    $err = null;
    return true;
  } // login()



  function logout()
  {
    if ($this->ldapConn && ldap_unbind($this->ldapConn)) {
      $this->ldapConn = null;
      $this->ldapUser = null;
      $this->groupNames = null;
      return true;
    }
    else {
      return false;
    }
  } // logout()



  function getGroups()
  {
    return $this->groupNames;
  } // getGroups()



  private function convertGroupIdsToNames($ldapConn, $gids)
  {
    $gidcount = count($gids);
    $groupNames = array();

    if ($gidcount > 0) {
      $attr = array("displayname");
      $filter = '(&(objectClass=groupOfNames)(|';
      for ($i = 0; $i < $gidcount; $i++) {
        $filter .= '(gidnumber='.$gids[$i].')';
      }
      $filter .= '))';

      // BASE_DN must be empty, because groups are not in o=senate.
      $sr = ldap_list($ldapConn, self::DEFAULT_BASE_DN, $filter, $attr);
      $ents = ldap_get_entries($ldapConn, $sr);
      $ent_count = ldap_count_entries($ldapConn, $sr);
      for ($i = 0; $i < $ent_count; $i++) {
        $groupNames[] = $ents[$i]['displayname'][0];
      }
    }
    return $groupNames;
  } // convertGroupIdsToNames()

} // SenLDAP

?>
