<html>
<head>
<title>Terminate Experiment</title>
<link rel="stylesheet" href="tbstyle.css" type="text/css">
</head>
<body>
<?php
include("defs.php3");

#
# Only known and logged in users can end experiments.
#
$uid = "";
if ( ereg("php3\?([[:alnum:]]+)",$REQUEST_URI,$Vals) ) {
    $uid=$Vals[1];
    addslashes($uid);
} else {
    unset($uid);
}
LOGGEDINORDIE($uid);

#
# Must provide the EID!
# 
if (!isset($exp_eid) ||
    strcmp($exp_eid, "") == 0) {
  USERERROR("The experiment ID was not provided!", 1);
}

#
# Verify that this uid is a member of the project for the experiment.
#
# First get the project (PID) for the experiment (EID) requested.
# Then check to see if the user (UID) is a member of that PID.
#
$query_result = mysql_db_query($TBDBNAME,
	"SELECT * FROM experiments WHERE eid=\"$exp_eid\"");
if (($exprow = mysql_fetch_array($query_result)) == 0) {
  USERERROR("The experiment $exp_eid is not a valid experiment.", 1);
}
$pid = $exprow[pid];

$query_result = mysql_db_query($TBDBNAME,
	"SELECT pid FROM proj_memb WHERE uid=\"$uid\" and pid=\"$pid\"");
if (mysql_num_rows($query_result) == 0) {
  USERERROR("You are not a member of the Project for Experiment: $exp_eid.",
            1);
}

#
# As per what happened when the experiment was created, we need to
# go back to that directory and use the .ir file to terminate the
# experiment, and then delete the files and the directory.
#
# XXX These paths/filenames are setup in beginexp_process.php3.
#
# No need to tell me how bogus this is.
#
$exp_id  = substr($exp_eid, strlen($pid));
$dirname = "$TBWWW_DIR"."$TBNSSUBDIR"."/"."$exp_eid";
$nsname  = "$dirname" . "/" . "$exp_id"  . ".ns";
$irname  = "$dirname" . "/" . "$exp_eid" . ".ir";
$repname = "$dirname" . "/" . "$exp_id"  . ".report";
$logname = "$dirname" . "/" . "$exp_eid" . ".log";
$assname = "$dirname" . "/" . "assign"   . ".log";

#
# Make sure the experiment directory exists before continuing. 
# 
if (! file_exists($irname)) {
    TBERROR("IR file $irname for experiment $exp_eid does not exist!\n", 1);
}

echo "<center><br>";
echo "<h3>Terminating the experiment. This may take a few minutes ...</h3>";
echo "</center>";

#
# Run the scripts. We use a script wrapper to deal with changing
# to the proper directory and to keep some of these details out
# of this. 
#
$output = array();
$retval = 0;
$result = exec("$TBBIN_DIR/tbstopit $dirname $exp_eid.ir",
               $output, $retval);
if ($retval) {
    echo "<br><br><h2>
          Termination Failure($retval): Output as follows:
          </h2>
          <br>
          <XMP>\n";
          for ($i = 0; $i < count($output); $i++) {
	      echo "$output[$i]\n";
          }
    echo "</XMP>\n";

    die("");
}

#
# Remove all trace! 
#
if (file_exists($nsname))
    unlink("$nsname");
if (file_exists($irname))
    unlink("$irname");
if (file_exists($repname))
    unlink("$repname");
if (file_exists($logname))
    unlink("$logname");
if (file_exists($assname))
    unlink("$assname");
if (file_exists($dirname))
     rmdir("$dirname");

#
# From the database too!
#
$query_result = mysql_db_query($TBDBNAME,
	"DELETE FROM experiments WHERE eid='$exp_eid'");
if (! $query_result) {
    $err = mysql_error();
    TBERROR("Database Error deleting experiment $exp_eid: $err\n", 1);
}

echo "<center><br>";
echo "<h2>Experiment Terminated! Thanks!<br>";
echo "</h2></center><br>";

?>
</center>
</body>
</html>
