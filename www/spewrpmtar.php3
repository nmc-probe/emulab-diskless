<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2003, 2004, 2005 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php3");

function SPITERROR($code, $msg)
{
    header("HTTP/1.0 $code $msg");
    exit();
}

#
# Must be SSL, even though we do not require an account login.
#
if (!isset($SSL_PROTOCOL)) {
    SPITERROR(400, "Must use https:// to access this page!");
}

#
# Verify page arguments.
# 
if (!isset($nodeid) ||
    strcmp($nodeid, "") == 0) {
    SPITERROR(400, "You must provide a node ID.");
}
$nodeid = addslashes($nodeid);
if (!isset($key) ||
    strcmp($key, "") == 0) {
    SPITERROR(400, "You must provide an key.");
}

#
# A variant allows us to pass the Emulab source code back to an ElabInElab
# experiment. 
#
if (!isset($elabinelab_source)) {
    if (!isset($file) ||
	strcmp($file, "") == 0) {
	SPITERROR(400, "You must provide an filename.");
    }
    if (!isset($stamp) || !strcmp($stamp, "")) {
	unset($stamp);
    }
    # We ignore MD5 for now. 
    if (!isset($md5) || !strcmp($md5, "")) {
	unset($md5);
    }
}

#
# Make sure a reserved node.
#
if (! TBNodeIDtoExpt($nodeid, $pid, $eid, $gid)) {
    SPITERROR(400, "$nodeid is not reserved to an experiment!");
}
TBExpLeader($pid, $eid, $creator);
TBGroupUnixInfo($pid, $gid, $unix_gid, $unix_name);

#
# We need the secret key. 
#
$query_result =
    DBQueryFatal("select keyhash,elab_in_elab from experiments ".
		 "where pid='$pid' and eid='$eid'");

if (mysql_num_rows($query_result) == 0) {
    SPITERROR(403, "No key defined for this experiment!");
}
$row = mysql_fetch_array($query_result);

if (!isset($row["keyhash"]) || !$row["keyhash"]) {
    SPITERROR(403, "No key defined for this experiment!");
}
if (strcmp($row["keyhash"], $key)) {
    SPITERROR(403, "Wrong Key!");
}

#
# A cleanup function to keep the child from becoming a zombie, since
# the script is terminated, but the children are left to roam.
#
$fp = 0;

function SPEWCLEANUP()
{
    global $fp;

    if (!$fp || !connection_aborted()) {
	exit();
    }
    pclose($fp);
    exit();
}
ignore_user_abort(1);
register_shutdown_function("SPEWCLEANUP");

#
# Special case. If requesting elab source code, the experiment must
# be an elabinelab experiment. 
#
if (isset($elabinelab_source)) {
    #
    # Make sure the IP really matches. 
    #
    $realid = TBIPtoNodeID($REMOTE_ADDR);
    if ($realid != $nodeid) {
	SPITERROR(403, "Not an elabinelab experiment!");
    }
    
    #
    # Must be an elabinelab experiment of course.
    #
    if ($row["elab_in_elab"] != "1") {
	SPITERROR(403, "Not an elabinelab experiment!");
    }

    #
    # If a specific tag is requested, call out to the spewsource program.
    # Otherwise send it the usual file.
    #
    if (isset($cvstag)) {
	if (! preg_match("/^[-\w\@\/\.]+$/", $cvstag)) {
	    SPITERROR(400, "Invalid characters in cvstag!");
	}

	# Do it anyway.
	$cvstag = escapeshellarg($cvstag);

	if ($fp = popen("$TBSUEXEC_PATH $creator $pid,$unix_gid ".
			"spewsource -t $cvstag", "r")) {
	    header("Content-Type: application/x-gzip");
	    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	    header("Cache-Control: no-cache, must-revalidate");
	    header("Pragma: no-cache");

	    flush();
	    fpassthru($fp);
	    $fp = 0;
	    flush();
	    return;
	}
	else {
	    SPITERROR(404, "Could not find $file!");
	}
    }
    else {
	if (!is_readable("/usr/testbed/src/emulab-src.tar.gz")) {
	    SPITERROR(404, "Could not find $file!");
	}
	header("Content-Type: application/octet-stream");
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	header("Cache-Control: no-cache, must-revalidate");
	header("Pragma: no-cache");
	flush();
	readfile("/usr/testbed/src/emulab-src.tar.gz");
	exit(0);
    }
}

#
# MUST DO THIS!
#
$nodeid = escapeshellarg($nodeid);
$file   = escapeshellarg($file);
$arg    = (isset($stamp) ? "-t " . escapeshellarg($stamp) : "");

#
# Run once with just the verify option to see if the file exists.
# Then do it for real, spitting out the data. Sure, the user could
# delete the file in the meantime, but thats his problem. 
#
$retval = SUEXEC($creator, "$pid,$unix_gid",
		 "spewrpmtar -v $arg $nodeid $file",
		 SUEXEC_ACTION_IGNORE);

if ($retval < 0) {
    SUEXECERROR(SUEXEC_ACTION_CONTINUE);
    SPITERROR(500, "Could not verify file!");
}

#
# An expected error.
# 
if ($retval) {
    if ($retval == 2) {
	SPITERROR(304, "File has not changed");
    }
    SPITERROR(404, "Could not verify file: $retval!");
}

#
# Okay, now do it for real. 
# 
if ($fp = popen("$TBSUEXEC_PATH $creator $pid,$unix_gid ".
		"spewrpmtar $nodeid $file", "r")) {
    header("Content-Type: application/octet-stream");
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");

    fpassthru($fp);
    $fp = 0;
    flush();
}
else {
    SPITERROR(404, "Could not find $file!");
}

?>
