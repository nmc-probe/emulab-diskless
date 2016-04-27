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
$page_title = "Extend";

#
# Get current user.
#
RedirectSecure();
$this_user = CheckLoginOrRedirect();
$this_idx  = $this_user->uid_idx();
$this_uid  = $this_user->uid();

#
# Verify page arguments.
#
$reqargs = OptionalPageArguments("uuid", PAGEARG_STRING);

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
$instance = Instance::Lookup($uuid);
if (!$instance) {
    SPITHEADER(1);
    echo "<div class='align-center'>
            <p class='lead text-center'>
              Experiment does not exist.
            </p>
          </div>\n";
    SPITFOOTER();
    return;
}
$extensions = ExtensionInfo::LookupForInstance($instance);

#
# If we have an outstanding extension, look to see how much more is left.
#
$days = "null";

if ($instance->extension_requested()) {
    $extension = $extensions[0];
    if ($extension->action() == "request" &&
        $extension->granted() < $extension->wanted()) {
        $days = $extension->wanted() - $extension->granted();
    }
}
$pid = $instance->pid();
$creator = $instance->creator();

#
# Verify page arguments.
#
SPITHEADER(1);

if (!ISADMIN()) {
    SPITUSERERROR("You do not have permission to view this information!");
    return;
}

echo "<link rel='stylesheet'
            href='css/tablesorter.css'>\n";

echo "<script type='text/javascript'>\n";
echo "  window.UUID = '" . $uuid . "';\n";
echo "  window.PID = '" . $pid . "';\n";
echo "  window.CREATOR = '" . $creator . "';\n";
echo "  window.DAYS = $days;\n";
echo "</script>\n";

# Place to hang the toplevel template.
echo "<div id='main-body'></div>\n";

SPITREQUIRE("adminextend",
            "<script src='js/lib/jquery.tablesorter.min.js'></script>".
            "<script src='js/lib/jquery.tablesorter.widgets.min.js'></script>".
            "<script src='js/lib/sugar.min.js'></script>".
            "<script src='js/lib/jquery.tablesorter.parser-date.js'></script>");

if ($instance->extension_reason() && $instance->extension_reason() != "") {
    echo "<pre class='hidden' id='extension-reason'>";
    echo CleanString($instance->extension_reason());
    echo "</pre>\n";
}

if (count($extensions)) {
    $foo = array();
    foreach ($extensions as $extension) {
        $foo[$extension->idx()] = $extension->info;
    }
    echo "<script type='text/plain' id='extensions-json'>\n";
    echo json_encode($foo);
    echo "</script>\n";
}
SPITFOOTER();
?>
