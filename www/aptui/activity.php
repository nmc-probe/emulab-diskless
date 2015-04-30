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
chdir("apt");
include("quickvm_sup.php");
include("profile_defs.php");
$page_title = "My Profiles";

#
# Verify page arguments.
#
$optargs = OptionalPageArguments("target_user",    PAGEARG_USER,
				 "target_project", PAGEARG_PROJECT,
                                 "min",            PAGEARG_INTEGER,
                                 "max",            PAGEARG_INTEGER);
#
# Get current user.
#
RedirectSecure();
$this_user = CheckLoginOrRedirect();
SPITHEADER(1);

if (!ISADMIN()) {
    if (isset($target_user)) {
        if (!$target_user->SameUser($this_user)) {
            SPITUSERERROR("Not enough permission to view this page!");
        }
    }
    elseif (isset($target_project)) {
        $approved = 0;
        
        if (!$target_project->IsMember($this_user, $approved) && $approved) {
            SPITUSERERROR("Not enough permission to view this page!");
        }
    }
    else {
        $target_user = $this_user;
    }
}
$instances = array();

#
# Allow for targeted searches
#
$whereclause = "";

if (isset($target_user)) {
    $target_idx  = $target_user->idx();
    $whereclause = "where h.creator_idx='$target_idx'";
}
elseif (isset($target_project)) {
    $target_idx   = $target_project->pid_idx();
    $whereclause = "where h.pid_idx='$target_idx'";
}
if (isset($min) || isset($max)) {
    if ($whereclause != "") {
        $whereclause = "$whereclause and ";
    }
    else {
        $whereclause = "where ";
    }
    if (isset($min)) {
        $whereclause .= "UNIX_TIMESTAMP(h.created) > $min ";
        if (isset($max)) {
            $whereclause .= "and ";
        }
    }
    if (isset($max)) {
        $whereclause .= "UNIX_TIMESTAMP(h.created) < $max ";
    }
}

$query_result =
    DBQueryFatal("select h.uuid,h.profile_version,h.created,h.destroyed, ".
		 "    h.creator,p.uuid as profile_uuid,p.pid,u.email, ".
                 "    h.physnode_count,h.virtnode_count,".
                 "    h.name as instance_name,p.name as profile_name, ".
                 "    truncate(h.physnode_count * ".
                 "      ((UNIX_TIMESTAMP(h.destroyed) - ".
                 "        UNIX_TIMESTAMP(h.created)) / 3600.0),2) as phours ".
		 "  from apt_instance_history as h ".
		 "left join apt_profile_versions as p on ".
		 "     p.profileid=h.profile_id and ".
		 "     p.version=h.profile_version ".
		 "left join geni.geni_users as u on u.uuid=h.creator_uuid ".
                 $whereclause . " " .
		 "order by h.created desc");

if (mysql_num_rows($query_result) == 0) {
    $message = "<b>Oops, there is no activity to show you.</b><br>";
    SPITUSERERROR($message);
    exit();
}

if (1) {
    while ($row = mysql_fetch_array($query_result)) {
	$pname     = $row["profile_name"];
        $iname     = $row["instance_name"];
	$pproj     = $row["pid"];
	$puuid     = $row["profile_uuid"];
	$created   = DateStringGMT($row["created"]);
	$destroyed = DateStringGMT($row["destroyed"]);
	$creator   = $row["creator"];
	$email     = $row["email"];
        $pcount    = $row["physnode_count"];
        $vcount    = $row["virtnode_count"];
        $phours    = $row["phours"];
        # Backwards compat.
        if (!isset($pproj)) {
            $pproj = "";
        }
        if (!isset($destroyed)) {
            $destroyed = "";
        }
        if (!isset($iname)) {
            $iname = "&nbsp;";
        }
        
	# If a guest user, use email instead.
	if (isset($email)) {
	    $creator = $email;
	}

        # Save space with array instead of hash.
	$instance =
            array($pname, $pproj, $puuid, $pcount, $vcount,
                  $creator, $created, $destroyed, $phours, $iname);
                          
	$instances[] = $instance;
    }
}

# Place to hang the toplevel template.
echo "<div id='activity-body'></div>\n";

echo "<script type='text/javascript'>\n";
echo "    window.AJAXURL  = 'server-ajax.php';\n";
if (isset($min)) {
    echo "    window.MIN  = $min;\n";
}
else {
    echo "    window.MIN  = null;\n";
}
if (isset($max)) {
    echo "    window.MAX  = $max;\n";
}
else {
    echo "    window.MAX  = null;\n";
}
if (isset($target_user)) {
    echo "    window.ARG = 'user=$target_idx';\n";
}
elseif (isset($target_project)) {
    echo "    window.ARG = 'project=$target_idx';\n";
}
else {
    echo "    window.ARG = null;\n";
}
echo "</script>\n";
echo "<script type='text/plain' id='instances-json'>\n";
echo json_encode($instances);
echo "</script>\n";
echo "<link rel='stylesheet' href='css/tablesorter.css'>\n";
echo "<link rel='stylesheet' href='css/jQRangeSlider.css'>\n";
echo "<script src='js/lib/jquery.min.js'></script>\n";
echo "<script src='js/lib/jquery-ui.js'></script>\n";
echo "<script src='js/lib/jQRangeSlider/jQRangeSliderMouseTouch.js'></script>\n";
echo "<script src='js/lib/jQRangeSlider/jQRangeSliderDraggable.js'></script>\n";
echo "<script src='js/lib/jQRangeSlider/jQRangeSliderHandle.js'></script>\n";
echo "<script src='js/lib/jQRangeSlider/jQRangeSliderBar.js'></script>\n";
echo "<script src='js/lib/jQRangeSlider/jQRangeSliderLabel.js'></script>\n";
echo "<script src='js/lib/jQRangeSlider/jQRangeSlider.js'></script>\n";
echo "<script src='js/lib/jQRangeSlider/jQDateRangeSliderHandle.js'></script>\n";
echo "<script src='js/lib/jQRangeSlider/jQDateRangeSlider.js'></script>\n";
echo "<script src='js/lib/jQRangeSlider/jQRuler.js'></script>\n";
echo "<script src='js/lib/jquery.tablesorter.min.js'></script>\n";
echo "<script src='js/lib/jquery.tablesorter.widgets.min.js'></script>\n";
echo "<script src='js/lib/jquery.tablesorter.widget-math.js'></script>\n";
echo "<script src='js/lib/bootstrap.js'></script>\n";
echo "<script src='js/lib/require.js' data-main='js/activity'></script>\n";

SPITFOOTER();
?>
