<?php
include("defs.php3");
include("showstuff.php3");

#
# Standard Testbed Header
#
PAGEHEADER("Show Experiment Information");

#
# Only known and logged in users can end experiments.
#
$uid = GETLOGIN();
LOGGEDINORDIE($uid);

#
# Verify page arguments.
# 
if (!isset($pid) ||
    strcmp($pid, "") == 0) {
    USERERROR("You must provide a Project ID.", 1);
}

if (!isset($eid) ||
    strcmp($eid, "") == 0) {
    USERERROR("You must provide an Experiment ID.", 1);
}
$exp_eid = $eid;
$exp_pid = $pid;

#
# Check to make sure this is a valid PID/EID tuple.
#
if (! TBValidExperiment($exp_pid, $exp_eid)) {
  USERERROR("The experiment $exp_eid is not a valid experiment ".
            "in project $exp_pid.", 1);
}

#
# Verify Permission.
#
if (! TBExptAccessCheck($uid, $exp_pid, $exp_eid, $TB_EXPT_READINFO)) {
    USERERROR("You do not have permission to view experiment $exp_eid!", 1);
}

SUBPAGESTART();
SUBMENUSTART("Experiment Options");
WRITESUBMENUBUTTON("View NS File and Node Assignment",
		   "shownsfile.php3?pid=$exp_pid&eid=$exp_eid");
WRITESUBMENUBUTTON("Terminate this experiment",
		   "endexp.php3?pid=$exp_pid&eid=$exp_eid");

# Swap option.
$expstate = TBExptState($exp_pid, $exp_eid);
if ($expstate) {
    if (strcmp($expstate, $TB_EXPTSTATE_SWAPPED) == 0) {
	WRITESUBMENUBUTTON("Swap this Experiment in",
		      "swapexp.php3?inout=in&pid=$exp_pid&eid=$exp_eid");
	WRITESUBMENUBUTTON("Graphic Visualization of Topology",
		      "vistopology.php3?pid=$exp_pid&eid=$exp_eid");
    }
    elseif (strcmp($expstate, $TB_EXPTSTATE_ACTIVE) == 0) {
	WRITESUBMENUBUTTON("Swap this Experiment out",
		      "swapexp.php3?inout=out&pid=$exp_pid&eid=$exp_eid");
	WRITESUBMENUBUTTON("Graphic Visualization of Topology",
		      "vistopology.php3?pid=$exp_pid&eid=$exp_eid");
    }
}

#
# Admin folks get a swap request link to send email.
#
if (ISADMIN($uid)) {
    WRITESUBMENUBUTTON("Send a swap/terminate request",
			  "request_swapexp.php3?&pid=$exp_pid&eid=$exp_eid");
}
SUBMENUEND();

#
# Dump experiment record.
# 
SHOWEXP($exp_pid, $exp_eid);

SUBPAGEEND();

#
# Dump the node information.
#
SHOWNODES($exp_pid, $exp_eid);

if ($expstate &&
    (strcmp($expstate, $TB_EXPTSTATE_SWAPPING) == 0 ||
     strcmp($expstate, $TB_EXPTSTATE_ACTIVATING) == 0)) {

    echo "<script language=\"JavaScript\">
              <!--
	          doLoad(30000);
              //-->
          </script>\n";
}

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
