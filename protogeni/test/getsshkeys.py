#! /usr/bin/env python
#
# GENIPUBLIC-COPYRIGHT
# Copyright (c) 2008-2011 University of Utah and the Flux Group.
# All rights reserved.
# 
# Permission to use, copy, modify and distribute this software is hereby
# granted provided that (1) source code retains these copyright, permission,
# and disclaimer notices, and (2) redistributions including binaries
# reproduce the notices in supporting documentation.
#
# THE UNIVERSITY OF UTAH ALLOWS FREE USE OF THIS SOFTWARE IN ITS "AS IS"
# CONDITION.  THE UNIVERSITY OF UTAH DISCLAIMS ANY LIABILITY OF ANY KIND
# FOR ANY DAMAGES WHATSOEVER RESULTING FROM THE USE OF THIS SOFTWARE.
#

#
#
import sys
import pwd
import getopt
import os
import re
import xmlrpclib
from M2Crypto import X509

execfile( "test-common.py" )

#
# Get a credential for myself, that allows me to do things at the SA.
#
mycredential = get_self_credential()

#
# Lookup my ssh keys.
#
params = {}
params["credential"] = mycredential
params["version"]    = 1
rval,response = do_method("sa", "GetKeys", params)
if rval:
    Fatal("Could not get my keys")
    pass
mykeys = response["value"]
print str(mykeys)

