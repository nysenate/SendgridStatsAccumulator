<?php
error_reporting(E_ERROR);

//build config info here
isset($ldaphost) ? $ldaphost : $ldaphost= "webmail.senate.state.ny.us";
isset($ldapport) ? $ldapport : $ldapport= "389";
isset($_POST['loginname']) ? $ldapuname = $_POST['loginname'] : $ldapuname = '';
isset($_POST['loginpass']) ? $ldappass = $_POST['loginpass'] : $ldappass = '';
isset($_POST['attempted']) ? $ldapattempted = $_POST['attempted'] : $ldapattempted  = '0';
$ldapbasedn = " ";
$ldapdn = " ";
$ldapconn = ldap_connect($ldaphost, $ldapport) or die('Could not connect to ' . $ldaphost);
$ldapattributes = array("gidnumber");
$ldapfilter = "(sn=".$ldapuname."*)";

//when the post is submitted, it reads the non-empty variables and tries to connect
if(!empty($ldapuname) && !empty($ldappass) )
{
  //verifies connection
  if($ldapconn){
    //verifies bind (which means -u and -p are both correct). If it fails, it throws up a warning, which is why warnings are disabled on this page
    $ldapbind = ldap_bind($ldapconn, $ldapuname, $ldappass);
    if($ldapbind){
      //Does a search on the -u/-p combination for group id number
      $sr = ldap_search($ldapconn,$ldapdn,$ldapfilter,$ldapattributes);
      //Gets the entries & reads their length. Each array starts with a namespace and then gives the data, hence the -1 to move the cursor up one
      $entry = ldap_get_entries($ldapconn, $sr);
      //var_dump($entry);
      $gidlength = count($entry[0]['gidnumber'])-1;
      $groupnamearray = "";
      for($i = 0; $i < $gidlength; $i++)
      {
        $gidnumber = $entry[0]['gidnumber'][$i];
        //print('GidNumber: ' . $gidnumber . '<br>');
        //and then you do a second search for the group names, which returns ALL of the group names that you're looking at
        $searchresult = ldap_search($ldapconn," ", "(&(objectClass=groupOfNames)(gidnumber=".$gidnumber."))", array("displayname") );
        //var_dump($searchresult);
        $groupentry = ldap_get_entries($ldapconn, $searchresult);
        if($i != 0)
        {
          $groupnamearray .= ",";
        } 
        $groupnamearray .= $groupentry[0]['displayname'][0];
      }
      //print($groupnamearray);
      //$tovardup = explode(',',$groupnamearray);
      //var_dump($tovardup);
      //opens a session to pass the variables over, and then we go to index.php for authorization
      session_start();
      $_SESSION['groupnamearray'] = $groupnamearray;
      $_SESSION['loginname'] = $_POST['loginname'];
      //$_SESSION['loginpass'] = $_POST['loginpass'];
      header( 'Location: stats.php' ) ;
    }
    else {
      session_start();
      $_SESSION['kickbackerror'] = "Wrong User/Password";
    }
  }
}
elseif ($ldapattempted == 1)
{
  $_SESSION['kickbackerror'] ="Cannot leave fields blank";
}
?>
<html>
<head>

</head>
<body>
  <?php
    session_start();
    if(isset($_SESSION['kickbackerror']))
    {
      print('<script>alert("'.$_SESSION['kickbackerror'].'");</script>');
      unset($_SESSION['kickbackerror']);
    }
  ?>
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