#!/usr/bin/perl

# This program takes a list of machines and removes all the VLANs
# whose every member is an argument.

$snmpit = "/usr/testbed/bin/snmpit";

if ($#ARGV < 0) {
    die "Syntax: $0 machines\n";
}
open(SNMPIT,"$snmpit -l |") || die "Error running $snmpit -l\n";

%machines = {};

while ($#ARGV >= 0) {
    $machines{pop(@ARGV)} = "";
}

@toremove = ();

while (<SNMPIT>) {
    chop;
    @line = split;
    $id = $line[0];
    if (! ($id =~ /[0-9]+/)) {next;}
    if ($line[1] eq "Control") {next;}
    if ($line[1] eq "System") {next;}
    $remove = 1;
    foreach $member (@line[2..$#line]) {
	@elements = split(":",$member);
	if (! defined($machines{$elements[0]})) {
	    $remove = 0;
	    last;
	}
    }
    if ($remove == 1) {
	push(@toremove,$id);
    }
}

$toremove = join(" ",@toremove);
print "Removing VLANs: $toremove\n";

if ($#toremove > 0) {
    system("$snmpit -u -r $toremove");
}

print "VLANs removed\n";

0;
