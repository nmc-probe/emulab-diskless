<?php
#
# Copyright (c) 2000-2016 University of Utah and the Flux Group.
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
chdir("apt");
include("quickvm_sup.php");

#
# Get current user.
#
RedirectSecure();
$this_user = CheckLogin($check_status);
if (isset($this_user)) {
    # Allow unapproved users to edit their profile ...
    CheckLoginOrDie(CHECKLOGIN_UNAPPROVED);
}

#
# Verify page arguments.
#
$optargs = OptionalPageArguments("uid", PAGEARG_STRING);

if (!isset($uid)) {
    $uid = $this_user->uid();
    $target_user = $this_user;
}
elseif (!ISADMIN()) {
    SPITUSERERROR("Not enough permission");
    return;
}
elseif (!TBvalid_uid($uid)) {
    SPITUSERERROR("Invalid user");
}
else {
    $target_user = User::LookupByUid($uid);
    if (!$target_user) {
        sleep(2);
        SPITUSERERROR("No such user");
        return;
    }
}

# We use a session. in case we need to do verification
session_start();
session_unset();

$defaults = array();

# Default to start
$defaults["uid"]         = $target_user->uid();
$defaults["name"]        = $target_user->name();
$defaults["email"]       = $target_user->email();
$defaults["city"]        = $target_user->city();
$defaults["state"]       = $target_user->state();
$defaults["country"]     = $target_user->country();
$defaults["affiliation"] = $target_user->affil();

SPITHEADER(1);
echo "<link rel='stylesheet' href='css/bootstrap-formhelpers.min.css'>\n";
echo "<div id='page-body'></div>\n";
echo "<div id='oops_div'></div>\n";
echo "<div id='waitwait_div'></div>\n";
echo "<script type='text/plain' id='form-json'>\n";
echo htmlentities(json_encode($defaults)) . "\n";
echo "</script>\n";
echo "<script src='js/lib/jquery-2.0.3.min.js'></script>\n";
echo "<script src='js/lib/bootstrap.js'></script>\n";
echo "<script src='js/lib/require.js' data-main='js/myaccount'></script>";
SPITFOOTER();

?>