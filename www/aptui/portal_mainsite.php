<?php
#
# Copyright (c) 2006-2015 University of Utah and the Flux Group.
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

#
# So, we could be coming in on the alternate APT address (virtual server)
# which causes cookie problems. I need to look into avoiding this problem
# but for now, just change the global value of the TBAUTHDOMAIN when we do.
# The downside is that users will have to log in twice if they switch back
# and forth.
#
if ($_SERVER["SERVER_NAME"] == "www.aptlab.net") {
    $ISVSERVER    = 1;
    $ISAPT        = 1;
    $TBAUTHDOMAIN = ".aptlab.net";
    $COOKDIEDOMAIN= ".aptlab.net";
    $APTHOST      = "www.aptlab.net";
    $WWWHOST      = "www.aptlab.net";
    $APTBASE      = "https://www.aptlab.net";
    $APTMAIL      = "APT Operations <portal-ops@aptlab.net>";
    $APTTITLE     = "APT";
    $FAVICON      = "aptlab.ico";
    $APTLOGO      = "aptlogo.png";
    $APTSTYLE     = "apt.css";
    $GOOGLEUA     = 'UA-42844769-3';
    $TBMAILTAG    = "aptlab.net";
    $EXTENSIONS   = "portal-extensions@aptlab.net";
    $TBAUTHTIMEOUT= (24 * 3600 * 7);
    # For devel trees
    if (preg_match("/\/([\w\/]+)$/", $WWW, $matches)) {
        $APTBASE .= "/" . $matches[1];
    }
    $PORTAL_MANUAL       = "http://docs.aptlab.net";
    $PORTAL_MOTD_SITEVAR = "aptlab/message";
    $PORTAL_HELPFORUM    = "apt-users";
    $PORTAL_PASSWORD_HELP= "Aptlab.net or Emulab.net Username";
    $PORTAL_NSFNUMBER    = "CNS-1338155";
    $DEFAULT_AGGREGATE   = "Utah APT";
    $PORTAL_GENESIS      = "aptlab";
    $PORTAL_NAME         = "APT";
}
elseif ($_SERVER["SERVER_NAME"] == "www.cloudlab.us") {
    $ISVSERVER    = 1;
    $TBAUTHDOMAIN = ".cloudlab.us";
    $COOKDIEDOMAIN= "www.cloudlab.us";
    $APTHOST      = "www.cloudlab.us";
    $WWWHOST      = "www.cloudlab.us";
    $APTBASE      = "https://www.cloudlab.us";
    $APTMAIL      = "CloudLab Operations <portal-ops@cloudlab.us>";
    $APTTITLE     = "CloudLab";
    $FAVICON      = "cloudlab.ico";
    $APTLOGO      = "cloudlogo.png";
    $APTSTYLE     = "cloudlab.css";
    $ISCLOUD      = 1;
    $GOOGLEUA     = 'UA-42844769-2';
    $TBMAILTAG    = "cloudlab.us";
    $EXTENSIONS   = "portal-extensions@cloudlab.us";
    $TBAUTHTIMEOUT= (24 * 3600 * 14);
    # For devel trees
    if (preg_match("/\/([\w\/]+)$/", $WWW, $matches)) {
	$APTBASE .= "/" . $matches[1];
    }
    $PORTAL_MANUAL       = "http://docs.cloudlab.us";
    $PORTAL_MOTD_SITEVAR = "aptlab/message";
    $PORTAL_HELPFORUM    = "cloudlab-users";
    $PORTAL_PASSWORD_HELP= "CloudLab.us or Emulab.net Username";
    $PORTAL_NSFNUMBER    = "CNS-1302688";
    $DEFAULT_AGGREGATE   = "Utah Cloudlab";
    $PORTAL_GENESIS      = "cloudlab";
    $PORTAL_NAME         = "CloudLab";
}
elseif ($ISALTDOMAIN && $_SERVER["SERVER_NAME"] == "www.phantomnet.org") {
    $ISVSERVER    = 1;
    $TBAUTHDOMAIN = ".phantomnet.org";
    $COOKDIEDOMAIN= "www.phantomnet.org";
    $APTHOST      = "www.phantomnet.org";
    $WWWHOST      = "www.phantomnet.org";
    $APTBASE      = "https://www.phantomnet.org";
    $APTMAIL      = "PhantomNet Operations <portal-ops@phantomnet.org>";
    $APTTITLE     = "PhantomNet";
    $FAVICON      = "phantomnet.ico";
    $APTLOGO      = "phantomlogo.png";
    $APTSTYLE     = "phantomnet.css";
    $ISPNET       = 1;
    #$GOOGLEUA     = 'UA-42844769-2';
    $TBMAILTAG    = "phantomnet.org";
    $EXTENSIONS   = "portal-extensions@phantomnet.org";
    $TBAUTHTIMEOUT= (24 * 3600 * 14);
    # For devel trees
    if (preg_match("/\/([\w\/]+)$/", $WWW, $matches)) {
	$APTBASE .= "/" . $matches[1];
    }
    $PORTAL_MANUAL         = "http://wiki.phantomnet.org";
    $PORTAL_MOTD_SITEVAR   = "phantomnet/message";
    $PORTAL_HELPFORUM      = "phantomnet-users";
    $PORTAL_PASSWORD_HELP  = "PhantomNet.org or Emulab.net Username";
    $PORTAL_NSFNUMBER      = "CNS-1305384";
    $DEFAULT_AGGREGATE     = "Emulab";
    $DEFAULT_AGGREGATE_URN = "urn:publicid:IDN+emulab.net+authority+cm";
    $PORTAL_GENESIS        = "phantomnet";
    $PORTAL_NAME           = "PhantomNet";
}
?>
