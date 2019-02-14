<?php

class SenLDAP
{
  const DEFAULT_LDAP_PORT = 389;
  const DEFAULT_BIND_DN = '';
  const DEFAULT_BIND_PW = '';
  const DEFAULT_USER_ATTR = 'uid';
  const DEFAULT_BASE_DN = '';

  private $ldapConn;
  private $groupNames;


  function __construct()
  {
    $ldapConn = null;
    $groupNames = null;
  }


  function bind($host, $port = self::DEFAULT_LDAP_PORT,
                $binddn = self::DEFAULT_BIND_DN,
                $bindpw = self::DEFAULT_BIND_PW)
  {
    if ($this->ldapConn) {
      return true;
    }
    else if (!$host) {
      $msg = "Must specify a host in order to connect to LDAP";
      throw new Exception($msg);
    }

    if ($port === null) {
      $port = self::DEFAULT_LDAP_PORT;
    }
    if ($binddn === null) {
      $binddn = self::DEFAULT_BIND_DN;
    }
    if ($bindpw === null) {
      $bindpw = self::DEFAULT_BIND_PW;
    }

    $conn = ldap_connect($host, $port);
    if (!$conn) {
      $msg = "Unable to connect to LDAP host [$host] on port [$port]";
      throw new Exception($msg);
    }

    $rc = ldap_bind($conn, $binddn, $bindpw);
    if (!$rc) {
      $msg = "Unable to bind to LDAP server using service account [$binddn]";
      ldap_unbind($conn);
      throw new Exception($msg);
    }

    $this->ldapConn = $conn;
    return true;
  } // bind()


  function authenticate($uname, $pw,
                        $uattr = self::DEFAULT_USER_ATTR,
                        $basedn = self::DEFAULT_BASE_DN)
  {
    if (!$this->ldapConn) {
      $msg = "Unable to call authenticate() before binding to LDAP server";
      throw new Exception($msg);
    }

    // It is very important to check for a null or empty password.  The
    // ldap_bind() function will attempt an anonymous bind if the password
    // is empty.  Since we are using ldap_bind() to verify the user's login
    // credentials, an empty password would allow a user to authenticate
    // with only his/her username.  This is certainly not intended behavior.
    if (empty($uname) || empty($pw)) {
      $msg = "Username and password must be provided in order to authenticate";
      throw new Exception($msg);
    }

    if ($uattr === null) {
      $uattr = self::DEFAULT_USER_ATTR;
    }
    if ($basedn === null) {
      $basedn = self::DEFAULT_BASE_DN;
    }

    $conn = $this->ldapConn;
    $filter = "($uattr=$uname)";
    $attrs = array('memberOf', 'gidnumber');

    // Confirm that the provided username is a valid LDAP account.
    $sr = ldap_search($conn, $basedn, $filter, $attrs);
    if ($sr === false) {
      $msg = "Unable to locate user [$uname] in LDAP";
      throw new Exception($msg);
    }

    if (ldap_count_entries($conn, $sr) != 1) {
      $msg = "Lookup for user [$uname] did not return single result";
      throw new Exception($msg);
    }

    $ent = ldap_first_entry($conn, $sr);

    // Verify the password for the provided username using its DN.
    $dn = ldap_get_dn($conn, $ent);
    if ($dn === false) {
      $msg = "Unable to retrieve DN for user [$uname]";
      throw new Exception($msg);
    }

    $rc = ldap_bind($conn, $dn, $pw);
    if ($rc === false) {
      $msg = "Password failed for user [$uname]";
      throw new Exception($msg);
    }

    // Username and password were verified, so now retrieve group membership.
    // Start by getting the attributes for this user.  The attributes were
    // limited to 'memberOf' and 'gidnumber' in the ldap_search() call, so
    // those are the only possible attributes that will be available.
    $ent_attrs = ldap_get_attributes($conn, $ent);
    if ($ent_attrs === false) {
      $msg = "Unable to retrieve attributes for LDAP entry for [$uname]";
      throw new Exception($msg);
    }

    // If the 'memberOf' attribute exists, use that, since it contains
    // group names, rather than IDs.  Otherwise, fall back on 'gidnumber'.
    if (isset($ent_attrs['memberOf'])) {
      $grps = ldap_get_values($conn, $ent, 'memberOf');
      unset($grps['count']);
      $groupNames = $this->convertGroupDNsToNames($conn, $grps);
    }
    else if (isset($ent_attrs['gidnumber'])) {
      $gids = ldap_get_values($conn, $ent, 'gidnumber');
      unset($gids['count']);
      $groupNames = $this->convertGroupIdsToNames($conn, $gids);
    }
    else {
      $msg = "Unable to retrieve groups for user [$uname]";
      throw new Exception($msg);
    }

    $this->groupNames = $groupNames;
    return true;
  } // authenticate()


  function logout()
  {
    if ($this->ldapConn && ldap_unbind($this->ldapConn)) {
      $this->ldapConn = null;
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


  private function convertGroupDNsToNames($conn, $groupdns)
  {
    $groupCount = count($groupdns);
    $groupNames = array();

    for ($i = 0; $i < $groupCount; $i++) {
      $g = $groupdns[$i];
      if (strncasecmp($g, "CN=", 3) === 0) {
        $g = substr($g, 3);
      }
      $g_parts = explode(',', $g);
      $g = $g_parts[0];
      $groupNames[] = $g;
    }
    return $groupNames;
  } // convertGroupDNsToNames()


  private function convertGroupIdsToNames($conn, $gids)
  {
    $gidCount = count($gids);
    $groupNames = array();

    if ($gidCount > 0) {
      $attr = array("displayname");
      $filter = '(&(objectClass=groupOfNames)(|';
      for ($i = 0; $i < $gidCount; $i++) {
        $filter .= '(gidnumber='.$gids[$i].')';
      }
      $filter .= '))';

      // BASE_DN must be empty, because groups are not in o=senate.
      $sr = ldap_list($conn, self::DEFAULT_BASE_DN, $filter, $attr);
      $ents = ldap_get_entries($conn, $sr);
      $ent_count = ldap_count_entries($conn, $sr);
      for ($i = 0; $i < $ent_count; $i++) {
        $groupNames[] = $ents[$i]['displayname'][0];
      }
    }
    return $groupNames;
  } // convertGroupIdsToNames()

} // SenLDAP

?>
