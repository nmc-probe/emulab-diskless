<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2002 University of Utah and the Flux Group.
# All rights reserved.
#
require("defs.php3");

#
# Standard Testbed Header
#
PAGEHEADER("Frequently Asked Questions");

echo("<b><a href=$TBDOCBASE/docwrapper.php3?docname=faq.html&printable=1>Printable version of this document</a></b><br>");

#
# I don't want to stick the html code in here. 
# 
readfile("faq.html");

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
