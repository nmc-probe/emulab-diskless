<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2003 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php3");
include("showstuff.php3");

#
# Standard Testbed Header
#
PAGEHEADER("Edit Image Descriptor");

#
# Only known and logged in users allowed.
#
$uid = GETLOGIN();
LOGGEDINORDIE($uid);
$isadmin = ISADMIN($uid);

#
# Verify form arguments.
# 
if (!isset($imageid) ||
    strcmp($imageid, "") == 0) {
    USERERROR("You must provide an ImageID.", 1);
}

if (! TBValidImageID($imageid)) {
    USERERROR("ImageID '$imageid' is not a valid ImageID!", 1);
}

#
# Verify permission.
#
if (!TBImageIDAccessCheck($uid, $imageid, $TB_IMAGEID_MODIFYINFO)) {
    USERERROR("You do not have permission to access ImageID $imageid!", 1);
}

#
# Need the gid for path checking.
#
$query_result =
    DBQueryFatal("select * from images where imageid='$imageid'");
$row = mysql_fetch_array($query_result);
$gid = $row['gid'];
$pid = $row['pid'];
$shared = $row['shared'];

#
# Sanitize values and create string pieces.
#
if (isset($description) && strcmp($description, "")) {
    $foo = addslashes($description);
    
    $description = "'$foo'";
}
else {
    $description = "NULL";
}

if (isset($magic) && strcmp($magic, "")) {
    $foo = addslashes($magic);
    
    $magic = "'$foo'";
}
else {
    $magic = "NULL";
}

if (isset($path) && strcmp($path, "")) {
    if (! ereg("^[-_a-zA-Z0-9\/\.+]+$", $path)) {
	USERERROR("The path must not contain special characters!", 1);
    }

    if (!$isadmin) {
	$pdef = "";
	
	if (!$shared && strcmp($gid, $pid)) {
	    $pdef = "/groups/" . $pid . "/" . $gid . "/";
	}
	else {
	    $pdef = "/proj/" . $pid . "/images/";
	}

	if (strpos($path, $pdef) === false) {
	    USERERROR("Invalid path! Must reside in /proj or /groups.", 1);
	}
    }
    $path = "'$path'";
}
else {
    $path = "NULL";
}
if (isset($loadaddr) && strcmp($loadaddr, "")) {
    $foo = addslashes($loadaddr);

    if (strcmp($loadaddr, $foo)) {
	USERERROR("The load address must not contain special characters!", 1);
    }
    $loadaddr = "'$loadaddr'";
}
else {
    $loadaddr = "NULL";
}

#
# Create an update string
#
$query_string =
	"UPDATE images SET             ".
	"description=$description,     ".
	"path=$path,                   ".
	"magic=$magic,                 ".
        "load_address=$loadaddr        ";

$query_string = "$query_string WHERE imageid='$imageid'";

$insert_result = DBQueryFatal($query_string);

SHOWIMAGEID($imageid, 0, $isadmin);

#
# Edit option.
#
$fooid = rawurlencode($imageid);
echo "<br><center>
           <A href='editimageid_form.php3?imageid=$fooid'>
              Edit this ImageID?</a>
         </center>\n";

#
# Delete option.
#
echo "<br><center>
           <A href='deleteimageid.php3?&imageid=$fooid'>
              Delete this ImageID?</a>
         </center>\n";

echo "<br><br>\n";

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
