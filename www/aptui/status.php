<?php
#
# Copyright (c) 2000-2015 University of Utah and the Flux Group.
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
include_once("osinfo_defs.php");
include_once("geni_defs.php");
include_once("webtask.php");
chdir("apt");
include("quickvm_sup.php");
include_once("profile_defs.php");
include_once("instance_defs.php");
$page_title = "Experiment Status";
$ajax_request = 0;

#
# Get current user.
#
$this_user = CheckLogin($check_status);
if (isset($this_user)) {
    CheckLoginOrDie(CHECKLOGIN_NONLOCAL);
}
#
# We do not set the isfadmin flag if the user has normal permission
# to see this experiment, since that would change what the user sees.
# Okay for real admins, but not for foreign admins.
#
$isfadmin = 0;

#
# Verify page arguments.
#
$reqargs = OptionalPageArguments("uuid",    PAGEARG_STRING,
				 "extend",  PAGEARG_INTEGER,
				 "oneonly", PAGEARG_BOOLEAN);

if (!isset($uuid)) {
    SPITHEADER(1);
    echo "<div class='align-center'>
            <p class='lead text-center'>
              What experiment would you like to look at?
            </p>
          </div>\n";
    SPITFOOTER();
    return;
}

#
# See if the instance exists. If not, redirect back to the create page
#
$instance = Instance::Lookup($uuid);
if (!$instance) {
    SPITHEADER(1);
    echo "<div class='align-center'>
            <p class='lead text-center'>
              Experiment does not exist. Redirecting to the front page.
            </p>
          </div>\n";
    SPITFOOTER();
    flush();
    sleep(3);
    PAGEREPLACE("instantiate.php");
    return;
}
$creator = GeniUser::Lookup("sa", $instance->creator_uuid());
if (! $creator) {
    $creator = User::LookupByUUID($instance->creator_uuid());
}
if (!$creator) {
    SPITHEADER(1);
    echo "<div class='align-center'>
            <p class='lead text-center'>
               Hmm, there seems to be a problem.
            </p>
          </div>\n";
    SPITFOOTER();
    TBERROR("No creator for instance: $uuid", 0);
    return;
}
#
# Only logged in admins can access an experiment created by someone else.
#
if (! (isset($this_user) && ISADMIN())) {
    # An experiment created by a real user, can be accessed by that user only.
    # Ditto a guest user; must be the same guest.
    if (! ((get_class($creator) == "User" && isset($this_user) &&
            $instance->CanView($this_user)) ||
	   (get_class($creator) == "GeniUser" &&
	    isset($_COOKIE['quickvm_user']) &&
	    $_COOKIE['quickvm_user'] == $creator->uuid()))) {
        if (ISFOREIGN_ADMIN()) {
            # See comment above.
            $isfadmin = 1;
        }
        else {
            PAGEERROR("You do not have permission to look at this experiment!");
        }
    }
}
$slice = GeniSlice::Lookup("sa", $instance->slice_uuid());

$instance_status = $instance->status();
$creator_uid     = $creator->uid();
$creator_email   = $creator->email();
if ($instance->profile_id() &&
    $profile = Profile::Lookup($instance->profile_id(),
			       $instance->profile_version())) {
    $profile_name   = $profile->name();
    $profile_uuid   = $profile->uuid();
    $profile_public = ($profile->ispublic() ? "true" : "false");
    $cansnap        = ((isset($this_user) &&
			$this_user->idx() == $creator->idx() &&
			$this_user->idx() == $profile->creator_idx()) ||
		       ISADMIN() ? 1 : 0);
    $canclone       = ((isset($this_user) &&
                        $profile->CanClone($this_user)) ||
		       ISADMIN() ? 1 : 0);
    $public_url     = ($instance->public_url() ?
		       "'" . $instance->public_url() . "'" : "null");
    $ispprofile     = $profile->script() ? 1 : 0;
}
else {
    $profile_name   = "";
    $profile_uuid   = "";
    $profile_public = "false";
    $cansnap        = 0;
    $canclone       = 0;
    $public_url     = "null";
    $ispprofile     = 0;

}
if ($slice) {
    $slice_urn       = $slice->urn();
    $instance_name   = $instance->name();
    # Until old instances are gone.
    if (!$instance_name) {
        list ($a,$b,$instance_name) = Instance::ParseURN($slice_urn);
    }
    $slice_expires   = DateStringGMT($slice->expires());
    $slice_expires_text = gmdate("m-d\TH:i\Z", strtotime($slice->expires()));
    $slice_created   = DateStringGMT($instance->created());
}
else {
    $slice_urn = "";
    $slice_expires = "";
    $slice_expires_text = ""; 
    $slice_created  = "";
    $instance_name  = "";
}
$registered      = (isset($this_user) ? "true" : "false");
$snapping        = 0;
$oneonly         = (isset($oneonly) && $oneonly ? 1 : 0);
$isadmin         = (ISADMIN() ? 1 : 0);
$lockdown        = ($instance->admin_lockdown() ||
                    $instance->user_lockdown() ? 1 : 0);
$extension_reason= ($instance->extension_reason() ?
                    CleanString($instance->extension_reason()) : "");
$extension_history= ($instance->extension_history() ?
                    CleanString($instance->extension_history()) : "");
$freenodes_url   = Aggregate::Lookup($instance->aggregate_urn())->FreeNodesURL();
$lockout         = $instance->extension_lockout();
$paniced         = $instance->paniced();

#
# We give ssh to the creator (real user or guest user).
#
$dossh =
    (((isset($this_user) && $instance->CanDoSSH($this_user)) ||
      (isset($_COOKIE['quickvm_user']) &&
       $_COOKIE['quickvm_user'] == $creator->uuid())) ? 1 : 0);

#
# See if we have a task running in the background for this instance.
# At the moment it can only be a snapshot task. If there is one, we
# have to tell the js code to show the status of the snapshot.
#
# XXX we could be imaging for a new profile (Cloning) instead. In that
# case the webtask object will not be attached to the instance, but to
# whatever profile is cloning. We do not know that profile here, so we
# cannot show that progress. Needs more thought.
#
if ($instance_status == "imaging") {
    $webtask = WebTask::LookupByObject($instance->uuid());
    if ($webtask && ! $webtask->exited()) {
	$snapping = 1;
    }
}

SPITHEADER(1);

# Place to hang the toplevel template.
echo "<div id='status-body'></div>\n";

echo "<script type='text/javascript'>\n";
echo "  window.APT_OPTIONS.uuid = '" . $uuid . "';\n";
echo "  window.APT_OPTIONS.name = '" . $instance_name . "';\n";
echo "  window.APT_OPTIONS.instanceStatus = '" . $instance_status . "';\n";
echo "  window.APT_OPTIONS.profileName = '" . $profile_name . "';\n";
echo "  window.APT_OPTIONS.profileUUID = '" . $profile_uuid . "';\n";
echo "  window.APT_OPTIONS.profilePublic = " . $profile_public . ";\n";
echo "  window.APT_OPTIONS.sliceURN = '" . $slice_urn . "';\n";
echo "  window.APT_OPTIONS.sliceExpires = '" . $slice_expires . "';\n";
echo "  window.APT_OPTIONS.sliceExpiresText = '" . $slice_expires_text . "';\n";
echo "  window.APT_OPTIONS.sliceCreated = '" . $slice_created . "';\n";
echo "  window.APT_OPTIONS.creatorUid = '" . $creator_uid . "';\n";
echo "  window.APT_OPTIONS.creatorEmail = '" . $creator_email . "';\n";
echo "  window.APT_OPTIONS.thisUid = '" . $this_user->uid() . "';\n";
echo "  window.APT_OPTIONS.registered = $registered;\n";
echo "  window.APT_OPTIONS.isadmin = $isadmin;\n";
echo "  window.APT_OPTIONS.isfadmin = $isfadmin;\n";
echo "  window.APT_OPTIONS.cansnap = $cansnap;\n";
echo "  window.APT_OPTIONS.canclone = $canclone;\n";
echo "  window.APT_OPTIONS.snapping = $snapping;\n";
echo "  window.APT_OPTIONS.oneonly = $oneonly;\n";
echo "  window.APT_OPTIONS.dossh = $dossh;\n";
echo "  window.APT_OPTIONS.ispprofile = $ispprofile;\n";
echo "  window.APT_OPTIONS.publicURL = $public_url;\n";
echo "  window.APT_OPTIONS.lockdown = $lockdown;\n";
echo "  window.APT_OPTIONS.lockout = $lockout;\n";
echo "  window.APT_OPTIONS.paniced = $paniced;\n";
echo "  window.APT_OPTIONS.extension_requested = " .
    $instance->extension_requested() . ";\n";
echo "  window.APT_OPTIONS.AJAXURL = 'server-ajax.php';\n";
echo "  window.APT_OPTIONS.physnode_count = " .
    $instance->physnode_count() . ";\n";
echo "  window.APT_OPTIONS.virtnode_count = " .
    $instance->virtnode_count() . ";\n";
echo "  window.APT_OPTIONS.physnode_hours = " .
    sprintf("%.2f;\n", $instance->physnode_count() *
            ((time() - strtotime($instance->created())) / 3600));
echo "  window.APT_OPTIONS.freenodesurl = '$freenodes_url';\n";
if (isset($extend) && $extend != "") {
    echo "  window.APT_OPTIONS.extend = $extend;\n";
}
echo "var FOO = null;\n";
echo "</script>\n";
echo "<script src='js/lib/jquery-2.0.3.min.js'></script>\n";
echo "<script src='js/lib/jquery-ui.js'></script>\n";
echo "<script src='js/lib/codemirror-min.js'></script>\n";
echo "<script src='js/lib/bootstrap.js'></script>\n";
echo "<script src='js/lib/require.js' data-main='js/status'></script>";
echo "<link rel='stylesheet'
            href='css/jquery-ui-1.10.4.custom.min.css'>\n";
# For progress bubbles in the imaging modal.
echo "<link rel='stylesheet' href='css/progress.css'>\n";
echo "<link rel='stylesheet' href='css/codemirror.css'>\n";
echo "<div class='hidden'><textarea id='extension_reason'>$extension_reason</textarea></div>\n";
if ($extension_reason != "") {
   echo "<pre class='hidden' id='extension_history'>$extension_history</pre>\n";
}

SPITFOOTER();
?>
