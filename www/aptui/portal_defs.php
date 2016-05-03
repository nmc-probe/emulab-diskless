<?php
#
# Copyright (c) 2006-2016 University of Utah and the Flux Group.
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
#

$APTHOST	= "$WWWHOST";
# Not sure why tbauth uses WWWHOST for the login cookies, but it
# causes confusion in geni-login.ajax. 
$COOKDIEDOMAIN  = "$WWWHOST";
$APTBASE	= "$TBBASE/portal";
$APTMAIL        = $TBMAIL_OPS;
$EXTENSIONS     = $TBMAIL_OPS;
$APTTITLE       = "Emulab";
$FAVICON        = "../favicon.ico";
$APTLOGO        = "emulab-logo.svg";
$APTSTYLE       = "emulab.css";
$ISEMULAB       = 1;
$ISAPT		= 0;
$ISCLOUD        = 0;
$ISPNET         = 0;
$ISVSERVER      = 0;
$GOOGLEUA       = 'UA-45161989-1';
# See tbauth.php3
$CHANGEPSWD_PAGE= "changepswd.php";
$MAXGUESTINSTANCES = 10;
$WITHPUBLISHING = 0;

#
# Other Portal globals. 
#
$PORTAL_MANUAL          = "https://wiki.emulab.net/wikidocs/wiki";
$PORTAL_MOTD_SITEVAR    = "web/banner";
$PORTAL_HELPFORUM       = "emulab-users";
$PORTAL_PASSWORD_HELP   = "Emulab Username or Email";
$PORTAL_NSFNUMBER       = "CNS-58502134";
$PORTAL_GENESIS         = "emulab";
$DEFAULT_AGGREGATE      = "Emulab";
$DEFAULT_AGGREGATE_URN	= "urn:publicid:IDN+${OURDOMAIN}+authority+cm";
$PORTAL_NAME            = "Emulab";

#
# The Utah MotherShip defines alternate portals. This needs to be split
# out into per-domain files at some point. 
#
if ($TBMAINSITE || $ISALTDOMAIN) {
    include_once("portal_mainsite.php");
}

?>
