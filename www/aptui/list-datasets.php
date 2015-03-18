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
include("lease_defs.php");
include("blockstore_defs.php");
include("imageid_defs.php");
chdir("apt");
include("quickvm_sup.php");
include("dataset_defs.php");
$page_title = "My Datasets";

#
# Verify page arguments.
#
$optargs = OptionalPageArguments("target_user",   PAGEARG_USER,
				 "all",           PAGEARG_BOOLEAN);

#
# Get current user.
#
RedirectSecure();
$this_user = CheckLoginOrRedirect();

if (!isset($target_user)) {
    $target_user = $this_user;
}
if (!$this_user->SameUser($target_user)) {
    if (!ISADMIN()) {
	SPITUSERERROR("You do not have permission to view ".
		      "target user's profiles");
	exit();
    }
}
$target_idx = $target_user->uid_idx();
$target_uid = $target_user->uid();

SPITHEADER(1);

echo "<link rel='stylesheet'
            href='css/tablesorter.css'>\n";

$whereclause1 = "where l.owner_uid='$target_uid'";
$whereclause2 = "where v.creator='$target_uid' and v.isdataset=1";
$whereclause3 = "where d.creator_uid='$target_uid'";
$orderclause1 = "order by l.owner_uid";
$orderclause2 = "order by v.creator";
$orderclause3 = "order by d.creator_uid";
$joinclause1  = "";
$joinclause2  = "left join image_versions as v on ".
    "              v.imageid=i.imageid and v.version=i.version ";
$joinclause3  = "";

if (isset($all)) {
    if (ISADMIN()) {
	$whereclause1 = "";
	$whereclause2 = "where v.isdataset=1";
	$whereclause3 = "";
    }
    else {
	$joinclause1 =
	    "left join group_membership as g on ".
	    "     g.uid='$target_uid' and ".
	    "     g.pid=l.pid and g.pid_idx=g.gid_idx";
	$joinclause2 .=
	    "left join group_membership as g on ".
	    "     g.uid='$target_uid' and ".
	    "     g.pid=i.pid and g.pid_idx=g.gid_idx";
	$joinclause3 =
	    "left join group_membership as g on ".
	    "     g.uid='$target_uid' and ".
	    "     g.pid=d.pid and g.pid_idx=g.gid_idx";
	$whereclause1 =
	    "where l.owner_uid='$target_uid' or ".
	    "      g.uid_idx is not null ";
	$whereclause2 =
	    "where (v.creator='$target_uid' or ".
	    "       g.uid_idx is not null) and v.isdataset=1 ";
	$whereclause3 =
	    "where d.creator_uid='$target_uid' or ".
	    "      g.uid_idx is not null ";
    }
}

if ($embedded) {
    $query_result =
	DBQueryFatal("(select l.uuid,'lease' as type from project_leases as l ".
		     " $joinclause1 ".
		     " $whereclause1 $orderclause1) ".
                     "union ".
                     "(select i.uuid,'image' as type from images as i ".
		     " $joinclause2 ".
		     " $whereclause2 $orderclause2)");
}
else {
    $query_result =
	DBQueryFatal("select d.uuid,'dataset' as type from apt_datasets as d ".
		     "$joinclause3 ".
		     "$whereclause3 $orderclause3");
}

if (mysql_num_rows($query_result) == 0) {
    $message = "<b>No datasets to show you. Maybe you want to ".
	"<a id='embedded-anchors'
             href='create-dataset.php?embedded=$embedded'>
               create one?</a></b>
          <br><br>";
    if ($all == 0) {
        if (ISADMIN()) {
            $message .=
                "<a id='embedded-anchors'
                href='list-datasets.php?all=1&embedded=$embedded'>
                 Show all datasets</a>";
        }
        else {
            $message .=
                "<a id='embedded-anchors'".
                "   href='list-datasets.php?all=1&embedded=$embedded'>".
                "Show all datasets you can use</a>\n";
        }
    }
    echo $message;
    SPITREQUIRE("list-datasets");
    SPITFOOTER();
    exit();
}
echo "<div class='row'>
       <div class='col-lg-12 col-lg-offset-0
                   col-md-12 col-md-offset-0
                   col-sm-12 col-sm-offset-0
                   col-xs-12 col-xs-offset-0'>\n";

echo "<a id='embedded-anchors'
             href='create-dataset.php?embedded=$embedded'>
               Create a new dataset?</a>
          <br>";

echo "<input class='form-control search' type='search'
             id='dataset_search' placeholder='Search'>\n";

echo "  <table class='tablesorter'>
         <thead>
          <tr>
           <th>Name</th>";
if (isset($all) && ISADMIN()) {
    echo " <th>Creator</th>";
}
echo "     <th>Project</th>
           <th>Type</th>
           <th>Expires</th>
           <th>URN</th>
          </tr>
         </thead>
         <tbody>\n";

while ($row = mysql_fetch_array($query_result)) {
    $uuid    = $row["uuid"];
    $type    = $row["type"];

    if ($type == "image") {
        $dataset = ImageDataset::Lookup($uuid);
    }
    elseif ($type == "lease") {
        $dataset = Lease::Lookup($uuid);
    }     
    elseif ($type == "dataset") {
        $dataset = Dataset::Lookup($uuid);
    }
    $idx     = $dataset->idx();
    $name    = $dataset->id();
    $dtype   = $dataset->type();
    $pid     = $dataset->pid();
    $creator = $dataset->owner_uid();
    $expires = $dataset->expires();
    $urn     = $dataset->URN();
    
    echo " <tr>
            <td><a href='show-dataset.php?uuid=$uuid&embedded=$embedded'>
             $name</a></td>\n";

    if (isset($all) && ISADMIN()) {
	echo "<td>$creator</td>";
    }
    echo "  <td style='white-space:nowrap'>$pid</td>
            <td>$dtype</td>
            <td class='format-date'>$expires</td>
            <td>$urn</td>
           </tr>\n";
}
echo "   </tbody>
        </table>\n";

if (!isset($all)) {
    if (ISADMIN()) {
	echo "<a id='embedded-anchors'
             href='list-datasets.php?all=1&embedded=$embedded'>
             Show all user datasets</a>\n";
    }
    else {
	echo "<a id='embedded-anchors'
             href='list-datasets.php?all=1&embedded=$embedded'>
             Show all datasets you can use</a>\n";
    }
}
else {
    echo "<a id='embedded-anchors'
             href='list-datasets.php?all=0&embedded=$embedded'>
             Show only my datasets</a>\n";
}
echo "   </div>
      </div>\n";

echo "<script type='text/javascript'>\n";
echo "    window.AJAXURL  = 'server-ajax.php';\n";
echo "</script>\n";
SPITREQUIRE("list-datasets",
         "<script src='js/lib/jquery.tablesorter.min.js'></script>\n".
         "<script src='js/lib/jquery.tablesorter.widgets.min.js'></script>\n");
SPITFOOTER();
?>
