<?php
include("defs.php3");
include("showstuff.php3");

#
# Standard Testbed Header
#
PAGEHEADER("Show User Information");

#
# Note the difference with which this page gets it arguments!
# I invoke it using GET arguments, so uid and pid are are defined
# without having to find them in URI (like most of the other pages
# find the uid).
#

#
# Only known and logged in users can do this.
#
$uid = GETLOGIN();
LOGGEDINORDIE($uid);

$isadmin = ISADMIN($uid);

#
# Verify form arguments.
# 
if (!isset($target_uid) ||
    strcmp($target_uid, "") == 0) {
    USERERROR("You must provide a User ID.", 1);
}

#
# Check to make sure thats this is a valid UID.
#
$query_result =
    DBQueryFatal("SELECT * FROM users WHERE uid='$target_uid'");
if (mysql_num_rows($query_result) == 0) {
  USERERROR("The user $target_uid is not a valid user", 1);
}

#
# Verify that this uid is a member of one of the projects that the
# target_uid is in. Must have proper permission in that group too. 
#
if (!$isadmin &&
    strcmp($uid, $target_uid)) {

    if (! TBUserInfoAccessCheck($uid, $target_uid, $TB_USERINFO_READINFO)) {
	USERERROR("You do not have permission to view this user's ".
		  "information!", 1);
    }
}

#
# Show user info.
# 
SHOWUSER($target_uid);

#
# Lets show projects.
#
$query_result =
    DBQueryFatal("select distinct g.pid,p.name from group_membership as g ".
		 "left join projects as p on p.pid=g.pid ".
		 "where uid='$target_uid' order by pid");

if (mysql_num_rows($query_result)) {
    echo "<center>
          <h3>Project Membership</h3>
          </center>
          <table align=center border=1 cellpadding=1 cellspacing=2>\n";

    echo "<tr>
              <td align=center>PID</td>
              <td align=center>Name</td>
          </tr>\n";

    while ($projrow = mysql_fetch_array($query_result)) {
	$pid  = $projrow[pid];
	$name = $projrow[name];

        echo "<tr>
                 <td><A href='showproject.php3?pid=$pid'>$pid</A></td>
                 <td>$name</td>
             </tr>\n";
    }
    echo "</table>\n";
}

#
# And Experiments.
#
$query_result =
    DBQueryFatal("select * from experiments  ".
		 "where expt_head_uid='$target_uid' order by pid,eid");

if (mysql_num_rows($query_result)) {
    echo "<center>
          <h3>Current Experiments</h3>
          </center>
          <table align=center border=1 cellpadding=1 cellspacing=2>\n";

    echo "<tr>
              <td align=center>PID</td>
              <td align=center>EID</td>
              <td align=center>Name</td>
          </tr>\n";

    while ($projrow = mysql_fetch_array($query_result)) {
	$pid  = $projrow[pid];
	$eid  = $projrow[eid];
	$name = $projrow[expt_name];

        echo "<tr>
                 <td><A href='showproject.php3?pid=$pid'>$pid</A></td>
                 <td><A href='showexp.php3?pid=$pid&eid=$eid'>$eid</A></td>
                 <td>$name</td>
             </tr>\n";
    }
    echo "</table>\n";
}

echo "</center>\n";

#
# Edit option.
#
if ($isadmin ||
    TBUserInfoAccessCheck($uid, $target_uid, $TB_USERINFO_MODIFYINFO)) {

    echo "<p><p><center>
           <A href='modusr_form.php3?target_uid=$target_uid'>
              Edit User Info?</a>
         </center>\n";
}
    
#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
