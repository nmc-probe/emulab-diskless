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
include_once("instance_defs.php");
include_once("profile_defs.php");
# Must be after quickvm_sup.php since it changes the auth domain.
include_once("../session.php");
$page_title = "Instantiate a Profile";
$dblink = GetDBLink("sa");

#
# Get current user but make sure coming in on SSL. Guest users allowed
# via APT Portal.
#
RedirectSecure();
$this_user = CheckLogin($check_status);
if (isset($this_user)) {
    CheckLoginOrDie(CHECKLOGIN_NONLOCAL|CHECKLOGIN_WEBONLY);
}
elseif (!$ISAPT) {
    RedirectLoginPage();
}

#
# Verify page arguments.
#
$optargs = OptionalPageArguments("create",        PAGEARG_STRING,
				 "profile",       PAGEARG_STRING,
				 "version",       PAGEARG_INTEGER,
				 "project",       PAGEARG_PROJECT,
				 "asguest",       PAGEARG_BOOLEAN,
				 "default",       PAGEARG_STRING,
				 "formfields",    PAGEARG_ARRAY);

if ($ISAPT && !$this_user) {
    #
    # If user appears to have an account, go to login page.
    # Continue as guest on that page.
    #
    if (REMEMBERED_ID()) {
	if (isset($asguest) && $asguest) {
	    # User clicked on continue as guest. If we do not delete the
	    # cookie, then user will go through the same loop next time
            # they click the Home button, since that points here. So delete
	    # the UID cookie. Not sure I like this.
	    ClearRememberedID();
	}
	else {
            header("Location: login.php?from=instantiate&referrer=".
                   urlencode($_SERVER['REQUEST_URI']));
	}
    }
}
if ($this_user) {
    $projlist = $this_user->ProjectAccessList($TB_PROJECT_CREATEEXPT);
    #
    # Cull out the nonlocal projects, we do not want to show those
    # since they are just the holding projects.
    #
    $tmp = array();
    while (list($pid) = each($projlist)) {
        # Watch out for killing page variable called "project"
        $proj = Project::Lookup($pid);
        if ($proj && !$proj->IsNonLocal()) {
            $tmp[$pid] = $projlist[$pid];
        }
    }
    $projlist = $tmp;
    
    if (count($projlist) == 0) {
	SPITUSERERROR("You do not belong to any projects with permission to ".
                      "create new experiments. Please contact your project ".
                      "leader to grant you the neccessary privilege.");
	exit();
    }
}
if ($ISCLOUD) {
    $profile_default     = "OpenStack";
    $profile_default_pid = "emulab-ops";
}
elseif ($ISPNET) {
    $profile_default     = "OneVM";
    $profile_default_pid = $TBOPSPID;
}
else {
    $profile_default     = "OneVM";
    $profile_default_pid = $TBOPSPID;
}
$profile_array  = array();
$am_array       = Instance::DefaultAggregateList();

#
# if using the super secret URL, make sure the profile exists, and
# add to the array now since it might not be public or belong to the user.
#
if (isset($profile)) {
    #
    # Guest users must use the uuid, but logged in users may use the
    # internal index. But, we have to support simple the URL too, which
    # is /p/project/profilename, but only for public profiles.
    #
    if (isset($project) && isset($profile)) {
	$obj = Profile::LookupByName($project, $profile, $version);
    }
    elseif ($this_user || IsValidUUID($profile)) {
	$obj = Profile::Lookup($profile);
    }
    else {
	SPITUSERERROR("Illegal profile for guest user: $profile");
	exit();
    }
    if (! $obj || $obj->deleted()) {
	SPITUSERERROR("No such profile: $profile");
	exit();
    }
    if (IsValidUUID($profile)) {
	#
	# If uuid was to profile, then find the most recently published
	# version and instantiate that, since what we have is the most
	# recent version, but might not be published.
	#
	if (0 && $profile == $obj->profile_uuid() && !$obj->published()) {
	    $obj = $obj->LookupMostRecentPublished();
	    if (! $obj) {
		SPITUSERERROR("No published version for profile");
		exit();
	    }
	}
        $profile = $obj;
	$profile_array[$profile->uuid()] = $profile->name();
	$profilename = $profile->name();
    }
    else {
	#
	# If no version provided, then find the most recently published
	# version and instantiate that, since what we have is the most
	# recent version, but might not be published.
	#
	if (0 && !isset($version) && !$obj->published()) {
	    $obj = $obj->LookupMostRecentPublished();
	    if (! $obj) {
		SPITUSERERROR("No published version for profile");
		exit();
	    }
	}
	 
	#
	# Must be public or pass the permission test for the user.
	#
	if (! ($obj->ispublic() ||
	       (isset($this_user) && $obj->CanInstantiate($this_user)))) {
	    SPITUSERERROR("No permission to use profile: $profile");
	    exit();
	}
	$profile = $obj;
	$profile_array[$profile->uuid()] = $profile->name();
	$profilename = $profile->name();
    }
    if ($profile->isDisabled()) {
        SPITUSERERROR("This profile is disabled!");
        exit();
    }
}
else {
    #
    # Find all the public and user profiles. We use the UUID instead of
    # indicies cause we do not want to leak internal DB state to guest
    # users. Need to decide on what clause to use, depending on whether
    # a guest user or not.
    #
    $joinclause   = "";
    $whereclause  = "";
    if (!isset($this_user)) {
	$whereclause = "p.public=1";
    }
    else {
	$this_idx = $this_user->uid_idx();
	$joinclause =
	    "left join group_membership as g on ".
	    "     g.uid_idx='$this_idx' and ".
	    "     g.pid_idx=v.pid_idx and g.pid_idx=g.gid_idx";
	$whereclause =
	    "p.public=1 or p.shared=1 or v.creator_idx='$this_idx' or ".
	    "g.uid_idx is not null ";
    }

    $query_result =
	DBQueryFatal("select p.*,v.* from apt_profiles as p ".
		     "left join apt_profile_versions as v on ".
		     "     v.profileid=p.profileid and ".
		     "     v.version=p.version ".
		     "$joinclause ".
		     "where locked is null and p.disabled=0 and ".
                     "      v.disabled=0 and ($whereclause) ".
		     "order by p.topdog desc");
    while ($row = mysql_fetch_array($query_result)) {
	$profile_array[$row["uuid"]] = $row["name"];
        if ($row["pid"] == $profile_default_pid &&
            $row["name"] == $profile_default) {
	    $profile_default = $row["uuid"];
	}
    }
    #
    # A specific profile, but we still want to give the user the selection
    # list above, but the profile might not be in the list if it is not
    # the highest numbered version.
    #
    if (isset($default)) {
        if (IsValidUUID($default)) {
            $obj = Profile::Lookup($default);
            if (!$obj) {
                SPITUSERERROR("Unknown default profile: $default");
                exit();
            }
            if (! ($obj->ispublic() ||
                   (isset($this_user) && $obj->CanInstantiate($this_user)))) {
                SPITUSERERROR("No permission to use profile: $default");
                exit();
            }
            if ($obj->isDisabled()) {
                SPITUSERERROR("This profile is disabled!");
                exit();
            }
            $profile_array[$obj->uuid()] = $obj->name();
            $profile_default = $obj->uuid();
        }
        else {
            SPITUSERERROR("Illegal default profile: $default");
            exit();
        }
    }
}

#
# Rebuild the array with extra info for the profile picker.
#
$tmp_array = array();

while (list ($uuid, $title) = each ($profile_array)) {
    $tmp = Profile::Lookup($uuid);
    if ($tmp) {
        list ($lastused, $count) = $tmp->UsageInfo($this_user);
        
        $tmp_array[$uuid] =
            array("name"     => $tmp->name(),
                  "project"  => $tmp->pid(),
                  "favorite" => $tmp->isFavorite($this_user),
                  "lastused" => $lastused,
                  "usecount" => $count);
    }
}
#
# Now we want to order the list.
#
if ($this_user) {
    uasort($tmp_array, function($a, $b) {
        if ($a["lastused"] == $b["lastused"]) {
            return 0;
        }
        return ($a["lastused"] > $b["lastused"]) ? -1 : 1;
    });
}
else {
    uasort($tmp_array, function($a, $b) {
        if ($a["usecount"] == $b["usecount"]) {
            return 0;
        }
        return ($a["usecount"] > $b["usecount"]) ? -1 : 1;
    });
}
$profile_array = $tmp_array;
#TBERROR(print_r($profile_array, true), 0);

function SPITFORM($formfields, $newuser, $errors)
{
    global $TBBASE, $APTMAIL, $ISAPT, $ISCLOUD, $ISPNET, $PORTAL_NAME;
    global $profile_array, $this_user, $profilename, $profile, $am_array;
    global $projlist;
    $amlist     = array();
    $showabout  = ($ISAPT && !$this_user ? 1 : 0);
    $registered = (isset($this_user) ? "true" : "false");
    # We use webonly to mark users that have no project membership
    # at the Geni portal.
    $webonly    = (isset($this_user) &&
                   $this_user->webonly() ? "true" : "false");
    $cancopy    = (isset($this_user) && !$this_user->webonly() ? 1 : 0);
    $nopprspec  = (!isset($this_user) ? "true" : "false");
    $portal     = "";
    $showpicker = (isset($profile) ? 0 : 1);
    if (isset($profilename)) {
        $profilename = "'$profilename'";
        $profilevers = $profile->version();
    }
    else {
        $profilename = "null";
        $profilevers = "null";
    }
    SPITHEADER(1);

    # I think this will take care of XSS prevention?
    echo "<script type='text/plain' id='form-json'>\n";
    echo htmlentities(json_encode($formfields)) . "\n";
    echo "</script>\n";
    echo "<script type='text/plain' id='error-json'>\n";
    echo htmlentities(json_encode($errors));
    echo "</script>\n";
    echo "<script type='text/plain' id='profiles-json'>\n";
    echo htmlentities(json_encode($profile_array));
    echo "</script>\n";
    
    # Gack.
    if (isset($this_user) && $this_user->IsNonLocal()) {
        if (preg_match("/^[^+]*\+([^+]+)\+([^+]+)\+(.+)$/",
                       $this_user->nonlocal_id(), $matches) &&
            $matches[1] == "ch.geni.net") {
            $portal = "https://portal.geni.net/";
        }
    }

    # Place to hang the toplevel template.
    echo "<div id='main-body'></div>\n";

    #
    # Spit out a project selection list if a real user.
    #
    if ($this_user && !$this_user->webonly()) {
        $plist = array();
        while (list($project) = each($projlist)) {
            $plist[] = $project;
        }
        echo "<script type='text/plain' id='projects-json'>\n";
        echo htmlentities(json_encode($plist));
        echo "</script>\n";
    }
    #
    # And AM list if that is allowed.
    #
    if (isset($this_user) && !$this_user->webonly() && $ISCLOUD) {
	$am_options = "";
	while (list($am, $urn) = each($am_array)) {
	    $amlist[$urn] = $am;
        }
	echo "<script type='text/plain' id='amlist-json'>\n";
	echo htmlentities(json_encode($amlist));
	echo "</script>\n";
    }
    #TEMPORARILY HARD CODED. Used to separate federated sites
    if ($ISCLOUD) {
        echo "<script type='text/javascript'>\n";
        echo "    window.FEDERATEDLIST  = ['IG UtahDDC', 'Emulab', 'APT Utah', 'iMinds Virt Wall 2', 'UKY Emulab'];\n";
        echo "</script>\n";
    }

    SpitOopsModal("oops");
    echo "<script type='text/javascript'>\n";
    echo "    window.PROFILE    = '" . $formfields["profile"] . "';\n";
    echo "    window.PROFILENAME= $profilename;\n";
    echo "    window.PROFILEVERS= $profilevers;\n";
    echo "    window.AJAXURL    = 'server-ajax.php';\n";
    echo "    window.SHOWABOUT  = $showabout;\n";
    echo "    window.NOPPRSPEC  = $nopprspec;\n";
    echo "    window.REGISTERED = $registered;\n";
    echo "    window.WEBONLY    = $webonly;\n";
    echo "    window.PORTAL     = '$portal';\n";
    echo "    window.SHOWPICKER = $showpicker;\n";
    echo "    window.CANCOPY = $cancopy;\n";
    $isadmin = (isset($this_user) && ISADMIN() ? 1 : 0);
    echo "    window.ISADMIN    = $isadmin;\n";
    $multisite = (isset($this_user) ? 1 : 0);
    echo "    window.MULTISITE  = $multisite;\n";
    $doconstraints = (isset($this_user) &&
                      (ISADMINISTRATOR() || STUDLY()) ? 1 : 0);
    echo "    window.DOCONSTRAINTS = 1;\n";
    echo "    window.PORTAL_NAME = '$PORTAL_NAME';\n";
    echo "</script>\n";
    echo "<script src='js/lib/jquery-2.0.3.min.js?nocache=asdfasdf'></script>\n";
    echo "<script src='js/lib/bootstrap.js?nocache=asdfasdf'></script>\n";
    echo "<script src='js/lib/require.js?nocache=asdfasdf' data-main='js/instantiate.js?nocache=asdfasdf'></script>";
}

if (!isset($create)) {
    $defaults = array();
    $defaults["username"] = "";
    $defaults["email"]    = "";
    $defaults["sshkey"]   = "";
    $defaults["profile"]  = (isset($profile) ?
                             $profile->uuid() : $profile_default);
    $defaults["where"]    = $DEFAULT_AGGREGATE;
    if ($this_user && count($projlist)) {
	list($project, $grouplist) = each($projlist);
        $defaults["pid"] = $project;
        reset($projlist);
    }
    else {
        $defaults["pid"] = "";
    }

    # 
    # Look for current user or cookie that tells us who the user is. 
    #
    if ($this_user) {
	$defaults["username"] = $this_user->uid();
	$defaults["email"]    = $this_user->email();
	#
	# Look for an key marked as an APT uploaded key and use that.
	# If no APT key, use any uploaded key; if the user leaves this
	# key in the form, it will become the official APT key.
	#
	$sshkey = $this_user->GetAPTSSHKey();
	if (!$sshkey) {
	    $sshkeys = $this_user->GetSSHKeys();
	    if (count($sshkeys)) {
		$sshkey = $sshkeys[0];
	    }
	}
	if ($sshkey) {
	    $defaults["sshkey"] = $sshkey;
	}
    }
    elseif (isset($_COOKIE['quickvm_user'])) {
	$geniuser = GeniUser::Lookup("sa", $_COOKIE['quickvm_user']);
	if ($geniuser) {
	    #
	    # Look for existing quickvm. User not allowed to create
	    # another one.
	    #
	    $instance = Instance::LookupByCreator($geniuser->uuid());
	    if ($instance && $instance->status() != "terminating") {
		header("Location: status.php?oneonly=1&uuid=" .
		       $instance->uuid());
		return;
	    }
            #
            # Watch for too many instances by guest user and redirect
            # to the signup page.
            #
            if (Instance::GuestInstanceCount($geniuser) > $MAXGUESTINSTANCES) {
		header("Location: signup.php?toomany=1");
		return;
            }
	    $defaults["username"] = $geniuser->name();
	    $defaults["email"]    = $geniuser->email();
	    $defaults["sshkey"]   = $geniuser->SSHKey();
	}
    }
    if (!$this_user) {
        # We use a session. in case we need to do verification
        session_start();
        session_unset();
    }
    SPITFORM($defaults, false, array());
    echo "<div style='display: none'><div id='jacks-dummy'></div></div>\n";
    SPITFOOTER();
    return;
}
?>
