<?php

class SenLDAP
{
  const DEFAULT_LDAP_PORT = 389;

  private $ldapConn;
  private $ldapUser;


  function __construct()
  {
    $ldapConn = null;
    $ldapUser = null;
  }


  function login($user, $pass, $host, $port = self::DEFAULT_LDAP_PORT, &$err)
  {
    if ($this->ldapConn) {
      $err = "You are already logged in.  Please logout first.";
      return false;
    }
    else if (!$host || !$user || !$pass) {
      $err = "Must specify a host, user, and pass.";
      return false;
    }

    $conn = ldap_connect($host, $port);
    if (!$conn) {
      $err = "Unable to connect to LDAP host [$host] on port [$port].";
      return false;
    }

    $ldapBind = ldap_bind($conn, $user, $pass);
    if (!$ldapBind) {
      //$err = "Unable to log in to LDAP server as user [$user].";
      $err = "Wrong Username and/or Password.";
      return false;
    }

    $this->ldapConn = $conn;
    $this->ldapUser = $user;
    $err = null;
    return true;
  } // login()


  function logout()
  {
    if ($this->ldapConn && ldap_unbind($this->ldapConn)) {
      $this->ldapConn = null;
      $this->ldapUser = null;
      return true;
    }
    else {
      return false;
    }
  } // logout()



  function getGroups()
  {
    $dn = '';
    $filter = '(uid='.$this->ldapUser.')';
    $attr = array("gidnumber");
    $sr = ldap_search($this->ldapConn, $dn, $filter, $attr);
    if (!$sr) {
      echo "ldap_search() failed\n";
      return null;
    }

    //Gets the entries and reads their length. Each array starts with a
    //namespace and then gives the data, hence the -1 to move the cursor up one
    $entries = ldap_get_entries($this->ldapConn, $sr);
    $gidarray = $entries[0]['gidnumber'];
    $gidcount = $gidarray['count'];
    $attr = array("displayname");
    $groupNames = array();

    for ($i = 0; $i < $gidcount; $i++) {
      $filter = '(&(objectClass=groupOfNames)(gidnumber='.$gidarray[$i].'))';
      $sr = ldap_search($this->ldapConn, $dn, $filter, $attr);
      $groupEntry = ldap_get_entries($this->ldapConn, $sr);
      $groupNames[] = $groupEntry[0]['displayname'][0];
    }
    return $groupNames;
  } // getGroups()

} // SenLDAP

?>
