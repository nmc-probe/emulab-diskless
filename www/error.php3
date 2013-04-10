<?php
#
# Copyright (c) 2000-2002, 2007 University of Utah and the Flux Group.
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
require("defs.php3");

# No page arguments, but make sure that the environment is clean
RequiredPageArguments();

#
# Standard Testbed Header
#
PAGEHEADER("Non Existent Page!");

USERERROR("The URL you gave: <b>" . htmlentities( $REQUEST_URI ) . "</b>
           is not available or is broken.", 1);

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>

