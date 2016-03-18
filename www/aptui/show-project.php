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
# Must be after quickvm_sup.php since it changes the auth domain.
$page_title = "Show Project";

#
# Get current user.
#
RedirectSecure();
$this_user = CheckLoginOrRedirect();
$this_idx  = $this_user->uid_idx();
$this_uid  = $this_user->uid();
$isadmin   = (ISADMIN() ? 1 : 0);

#
# Verify page arguments.
#
$optargs = RequiredPageArguments("project", PAGEARG_PROJECT);

SPITHEADER(1);

if (!ISADMIN() && 
    !$project->AccessCheck($this_user, $TB_PROJECT_READINFO)) {
    SPITUSERERROR("You do not have permission to view this information!");
    return;
}
$emulablink = "$TBBASE/showproject.php3?project=" . $project->pid();

echo "<link rel='stylesheet'
            href='css/tablesorter.css'>\n";

echo "<script type='text/javascript'>\n";
echo "  window.ISADMIN        = $isadmin;\n";
echo "  window.EMULAB_LINK    = '$emulablink';\n";
echo "  window.TARGET_PROJECT = '" . $project->pid() . "';\n";
echo "</script>\n";

# Place to hang the toplevel template.
echo "<div id='main-body'></div>\n";

SPITREQUIRE("show-project",
            "<script src='js/lib/jquery.tablesorter.min.js'></script>".
            "<script src='js/lib/jquery.tablesorter.widgets.min.js'></script>".
            "<script src='js/lib/sugar.min.js'></script>".
            "<script src='js/lib/jquery.tablesorter.parser-date.js'></script>");
SPITFOOTER();
?>
