<?php
include("defs.php3");

#
# Standard Testbed Header
#
PAGEHEADER("Node Control Form");

#
# Only known and logged in users can do this.
#
$uid = GETLOGIN();
LOGGEDINORDIE($uid);

#
# Verify form arguments.
# 
if (!isset($node_id) ||
    strcmp($node_id, "") == 0) {
    USERERROR("You must provide a node ID.", 1);
}

#
# Check to make sure that this is a valid nodeid
#
$query_result = mysql_db_query($TBDBNAME,
	"SELECT * FROM nodes WHERE node_id=\"$node_id\"");
if (mysql_num_rows($query_result) == 0) {
  USERERROR("The node $node_id is not a valid nodeid", 1);
}
$row = mysql_fetch_array($query_result);

#
# Admin users can control any node, but normal users can only control
# nodes in their own experiments.
#
$isadmin = ISADMIN($uid);
if (! $isadmin) {
    $query_result = mysql_db_query($TBDBNAME,
	"select proj_memb.* from proj_memb left join reserved ".
	"on proj_memb.pid=reserved.pid and proj_memb.uid='$uid' ".
	"where reserved.node_id='$node_id'");
    if (mysql_num_rows($query_result) == 0) {
        USERERROR("The node $node_id is not in an experiment ".
		  "or not in the same project as you", 1);
    }
    $foorow = mysql_fetch_array($query_result);
    $trust = $foorow[trust];
    if ($trust != "local_root" && $trust != "group_root") {
        USERERROR("You do not have permission to modify node $node_id!", 1);
    }
}

echo "<center><h1>
      Node Control Center: $node_id
      </h1></center>";

$node_id            = $row[node_id]; 
$type               = $row[type];
$def_boot_image_id  = $row[def_boot_image_id];
$def_boot_path      = $row[def_boot_path];
$def_boot_cmd_line  = $row[def_boot_cmd_line];
$next_boot_path     = $row[next_boot_path];
$next_boot_cmd_line = $row[next_boot_cmd_line];
$rpms               = $row[rpms];
$startupcmd         = $row[startupcmd];

echo "<table border=2 cellpadding=0 cellspacing=2
       align='center'>\n";

#
# Generate the form. Note that $refer is set by the caller so we know
# how we got to the nodecontrol page. 
# 
echo "<form action=\"nodecontrol.php3?refer=$refer\"
            method=\"post\">\n";

echo "<tr>
          <td>Node ID:</td>
          <td class=\"left\"> 
              <input type=\"readonly\" name=\"node_id\" value=\"$node_id\">
              </td>
      </tr>\n";

echo "<tr>
          <td>Node Type:</td>
          <td class=\"left\"> 
              <input type=\"readonly\" name=\"node_type\" value=\"$type\">
              </td>
      </tr>\n";

#
# This should be a menu.
# 
echo "<tr>
          <td>Def Boot Image:</td>
          <td class=\"left\">
              <input type=\"text\" name=\"def_boot_image_id\" size=\"30\"
                     value=\"$def_boot_image_id\"></td>
      </tr>\n";

echo "<tr>
          <td>Def Boot Path:</td>
          <td class=\"left\">
              <input type=\"text\" name=\"def_boot_path\" size=\"40\"
                     value=\"$def_boot_path\"></td>
      </tr>\n";


echo "<tr>
          <td>Def Boot Command Line:</td>
          <td class=\"left\">
              <input type=\"text\" name=\"def_boot_cmd_line\" size=\"40\"
                     value=\"$def_boot_cmd_line\"></td>
      </tr>\n";


echo "<tr>
          <td>Next Boot Path:</td>
          <td class=\"left\">
              <input type=\"text\" name=\"next_boot_path\" size=\"40\"
                     value=\"$next_boot_path\"></td>
      </tr>\n";


echo "<tr>
          <td>Next Boot Command Line:</td>
          <td class=\"left\">
              <input type=\"text\" name=\"next_boot_cmd_line\" size=\"40\"
                     value=\"$next_boot_cmd_line\"></td>
      </tr>\n";


echo "<tr>
          <td>Startup Command[1]:</td>
          <td class=\"left\">
              <input type=\"text\" name=\"startupcmd\" size=\"60\"
                     maxlength=\"256\" value=\"$startupcmd\"></td>
      </tr>\n";


echo "<tr>
          <td>RPMs[2]:</td>
          <td class=\"left\">
              <input type=\"text\" name=\"rpms\" size=\"60\"
                     maxlength=\"1024\" value=\"$rpms\"></td>
      </tr>\n";

echo "<tr>
          <td colspan=2 align=center>
              <b><input type=\"submit\" value=\"Submit\"></b>
          </td>
     </tr>
     </form>
     </table>\n";

echo "<p>
      <dl COMPACT>
        <dt> [1]
           <dd> Node startup command must be a pathname. You may also include
                optional arguments.
        <dt> [2]
           <dd> RPMs must be a colon separated list of pathnames.
      </dl>\n";

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
