#
# Copyright (c) 2007 University of Utah and the Flux Group.
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

#usage: init-by-file.pl proj exp input-file
#
# Input file is generated by init-elabnodes.pl using the -o option.

$proj = $ARGV[0];
$exp = $ARGV[1];
$filename = $ARGV[2];

sub psystem(@) {
  print join(' ', @_);
  print "\n";
  system(@_);
  die unless $? == 0;
}

open(FILE, "<".$filename) or die("Cannot open $filename\n");

# reset the links
# wait only on the reset event
psystem("/usr/testbed/bin/tevc -e $proj/$exp now elabc clear");
psystem("/usr/testbed/bin/tevc -w -e $proj/$exp now elabc reset");
psystem("/usr/testbed/bin/tevc -e $proj/$exp now elabc create");

while ($line = <FILE>)
{
    $line =~ /^10.0.0.([0-9]+) ([0-9.]+) ([0-9.]+) ([0-9.]+) [0-9.]+$/;
    psystem("/usr/testbed/bin/tevc -e $proj/$exp now elabc-elab-$1 modify dest=$2 bandwidth=$3");
    psystem("/usr/testbed/bin/tevc -e $proj/$exp now elabc-elab-$1 modify dest=$2 delay=$4");
}


