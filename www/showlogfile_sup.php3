<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2005, 2006, 2007 University of Utah and the Flux Group.
# All rights reserved.
#
require_once("Sajax.php");
sajax_init();
sajax_export("GetPNodes", "GetExpState");

# If this call is to client request function, then turn off interactive mode.
# All errors will go to above function and get reported back through the
# Sajax interface.
if (sajax_client_request()) {
    $session_interactive = 0;
}

function CHECKPAGEARGS($pid, $eid) {
    global $this_user, $TB_EXPT_READINFO;
    
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
    if (!TBvalid_pid($pid)) {
	PAGEARGERROR("Invalid project ID.");
    }
    if (!TBvalid_eid($eid)) {
	PAGEARGERROR("Invalid experiment ID.");
    }

    #
    # If $this_user is not set, someone got confused. 
    #
    if (!isset($this_user)) {
	TBERROR("Current user is not defined in CHECKPAGEARGS()", 1);
    }

    #
    # Check to make sure this is a valid PID/EID tuple.
    #
    $experiment = Experiment::LookupByPidEid($pid, $eid);
    if (! $experiment) {
	USERERROR("The experiment $pid/$eid is not a valid experiment!", 1);
    }
    
    #
    # Verify permission.
    #
    if (! $experiment->AccessCheck($this_user, $TB_EXPT_READINFO)) {
	USERERROR("You do not have permission to view the log for $pid/$eid!", 1);
    }
}

function GetPNodes($pid, $eid) {
    CHECKPAGEARGS($pid, $eid);
    
    $retval = array();

    $query_result = DBQueryFatal(
	"select r.node_id from reserved as r ".
	"where r.eid='$eid' and r.pid='$pid' order by LENGTH(node_id) desc");

    while ($row = mysql_fetch_array($query_result)) {
      $retval[] = $row[node_id];
    }

    return $retval;
}

function GetExpState($pid, $eid)
{
    CHECKPAGEARGS($pid, $eid);
    
    $expstate = TBExptState($pid, $eid);

    return $expstate;
}

function STARTWATCHER($pid, $eid)
{
    echo "<script type='text/javascript' language='javascript'
                  src='showexp.js'></script>\n";

    $currentstate = TBExptState($pid, $eid);
    
    echo "<script type='text/javascript' language='javascript'>\n";
    sajax_show_javascript();
    echo "StartStateChangeWatch('$pid', '$eid', '$currentstate');\n";
    echo "</script>\n";
}

function STARTLOG($pid, $eid)
{
    global $BASEPATH;

    STARTWATCHER($pid, $eid);

    echo "<center>\n";
    echo "<img id='busy' src='busy.gif'>
                   <span id='loading'> Working ...</span>";
    echo "</center>\n";
    echo "<br>\n";
    
    echo "<div><iframe id='outputframe' src='busy.gif' ".
	"width=100% height=600 scrolling=auto border=4></iframe></div>\n";
    
    echo "<script type='text/javascript' language='javascript' src='json.js'>
          </script>".
	 "<script type='text/javascript' language='javascript'
                  src='mungelog.js'>
          </script>\n";
    echo "<script type='text/javascript' language='javascript'>\n";

    echo "SetupOutputArea('outputframe');\n"; 

    echo "exp_pid = \"$pid\";\n";
    echo "exp_eid = \"$eid\";\n";
    echo "</script><div>
         <iframe id='downloader' name='downloader' width=0 height=0
                 src='spewlogfile.php3?pid=$pid&eid=$eid'
                 onload='ml_handleReadyState(LOG_STATE_LOADED);'
                 border=0 frameborder=0>
         </iframe></div>\n";
}

# See if this request is to one of the above functions. Does not return
# if it is. Otherwise return and continue on.
sajax_handle_client_request();

#
# We return to the including script ...
#
