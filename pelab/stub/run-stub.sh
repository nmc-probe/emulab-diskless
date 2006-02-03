#!/bin/sh
#
# Script to run the stub
#

#
# Let common-env know what role we're playing
#
export HOST_ROLE="stub"

#
# Grab common environment variables
#
. `dirname $0`/../common-env.sh

#
# Just run the stub!
# TODO: Allow other command line args
#
echo "Starting stubd on $PLAB_IFACE"
exec $AS_ROOT $STUB_DIR/$STUBD $PLAB_IFACE
