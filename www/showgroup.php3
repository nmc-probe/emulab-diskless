<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2002 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php3");
include("showstuff.php3");

#
# Standard Testbed Header
#
PAGEHEADER("Show Group Information");


#
# Note the difference with which this page gets it arguments!
# I invoke it using GET arguments, so uid and pid are are defined
# without having to find them in URI (like most of the other pages
# find the uid).
#

#
# Only known and logged in users can end experiments.
#
$uid = GETLOGIN();
LOGGEDINORDIE($uid);

$isadmin = ISADMIN($uid);

#
# Verify form arguments.
# 
if (!isset($pid) ||
    strcmp($pid, "") == 0) {
    USERERROR("You must provide a project ID.", 1);
}

if (!isset($gid) ||
    strcmp($gid, "") == 0) {
    USERERROR("You must provide a group ID.", 1);
}

#
# Check to make sure thats this is a valid PID/GID.
#
$query_result = 
    DBQueryFatal("SELECT * FROM groups WHERE pid='$pid' and gid='$gid'");
if (mysql_num_rows($query_result) == 0) {
  USERERROR("The group $pid/$gid is not a valid group", 1);
}

#
# Verify that this uid is a member of the project being displayed. 
#
if (!$isadmin) {
    $query_result = 
        DBQueryFatal("SELECT trust FROM group_membership ".
		     "WHERE uid='$uid' and pid='$pid' and gid='$gid'");
    if (mysql_num_rows($query_result) == 0) {
        USERERROR("You are not a member of Project $pid.", 1);
    }
}

SUBPAGESTART();
SUBMENUSTART("Group Options");
WRITESUBMENUBUTTON("Edit this Group",
		   "editgroup_form.php3?pid=$pid&gid=$gid");

#
# A delete option, but not for the default group!
#
if (strcmp($gid, $pid)) {
    WRITESUBMENUBUTTON("Delete this Group",
		       "deletegroup.php3?pid=$pid&gid=$gid");
}
SUBMENUEND();

SHOWGROUP($pid, $gid);
SHOWGROUPMEMBERS($pid, $gid);
SUBPAGEEND();

#
# A list of Group experiments.
#
$query_result =
    DBQueryFatal("SELECT eid,expt_name FROM experiments ".
		 "WHERE pid='$pid' and gid='$gid'");
if (mysql_num_rows($query_result)) {
    echo "<center>
          <h3>Group Experiments</h3>
          </center>
          <table align=center border=1>\n";

    while ($row = mysql_fetch_row($query_result)) {
        $eid  = $row[0];
        $name = $row[1];
	if (!$name)
	    $name = "--";
        echo "<tr>
                  <td>
                      <A href='showexp.php3?pid=$pid&eid=$eid'>$eid</a>
                      </td>
                  <td>$name</td>
              </tr>\n";
    }
    echo "</table>\n";
}

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
