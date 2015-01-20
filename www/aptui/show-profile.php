<?php
#
# Copyright (c) 2000-2014 University of Utah and the Flux Group.
# 
# {{{EMULAB-LICENSE
# 
# This file is part of the Emulab network testbed software.
# 
# This file is free software: you can redistribute it and/or modify it
# under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or (at
# your option) any later version.
# 
# This file is distributed in the hope that it will be useful, but WITHOUT
# ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
# FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public
# License for more details.
# 
# You should have received a copy of the GNU Affero General Public License
# along with this file.  If not, see <http://www.gnu.org/licenses/>.
# 
# }}}
#
chdir("..");
include("defs.php3");
include_once("webtask.php");
chdir("apt");
include("quickvm_sup.php");
include("profile_defs.php");
include("instance_defs.php");
$page_title = "Show Profile";

#
# Get current user.
#
RedirectSecure();
$this_user = CheckLoginOrRedirect();
$this_idx  = $this_user->uid_idx();
$isadmin   = (ISADMIN() ? 1 : 0);

#
# Verify page arguments.
#
$reqargs = RequiredPageArguments("uuid", PAGEARG_STRING);

SPITHEADER(1);

if (!isset($uuid)) {
    SPITUSERERROR("Must provide uuid!");
}
$profile = Profile::Lookup($uuid);
if (!$profile) {
    SPITUSERERROR("No such profile!");
}
if (!$profile->CanView($this_user) && !ISADMIN()) {
    SPITUSERERROR("Not enough permission!");
}
$profile_uuid = $profile->profile_uuid();
$version_uuid = $profile->uuid();
$ispp         = ($profile->isParameterized() ? 1 : 0);

$defaults = array();
$defaults["profile_name"]        = $profile->name();
$defaults["profile_rspec"]       = $profile->rspec();
$defaults["profile_version"]     = $profile->version();
$defaults["profile_creator"]     = $profile->creator();
$defaults["profile_created"]     = DateStringGMT($profile->created());
$defaults["profile_version_url"] = $profile->URL();
$defaults["profile_profile_url"] = $profile->ProfileURL();
if ($profile->script() && $profile->script() != "") {
    $defaults["profile_script"] = $profile->script();
}

# Place to hang the toplevel template.
echo "<div id='page-body'></div>\n";

echo "<link rel='stylesheet'
            href='css/jquery-ui-1.10.4.custom.min.css'>\n";
echo "<link rel='stylesheet' href='css/codemirror.css'>\n";

# I think this will take care of XSS prevention?
echo "<script type='text/plain' id='form-json'>\n";
echo htmlentities(json_encode($defaults)) . "\n";
echo "</script>\n";

$amlist = array();
$amdefault = "";
if (($ISCLOUD || ISADMIN() || STUDLY())) {
    while (list($am) = each($am_array)) {
	$amlist[] = $am;
    }
    $amdefault = $DEFAULT_AGGREGATE;
    # Temporary override until constraint system in place.
    if ($profile->BestAggregate()) {
	$amdefault = $profile->BestAggregate();
    }
}
echo "<script type='text/plain' id='amlist-json'>\n";
echo htmlentities(json_encode($amlist));
echo "</script>\n";

echo "<script type='text/javascript'>\n";
echo "    window.PROFILE_UUID = '$profile_uuid';\n";
echo "    window.VERSION_UUID = '$version_uuid';\n";
echo "    window.AJAXURL      = 'server-ajax.php';\n";
echo "    window.ISADMIN      = $isadmin;\n";
echo "    window.ISPPPROFILE  = $ispp;\n";
echo "</script>\n";

echo "<script src='js/lib/codemirror-min.js'></script>\n";

SPITREQUIRE("show-profile");
SPITFOOTER();

?>
