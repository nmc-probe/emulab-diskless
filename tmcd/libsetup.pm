#!/usr/bin/perl -wT

#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2003 University of Utah and the Flux Group.
# All rights reserved.
#
# TODO: Signal handlers for protecting db files.

#
# Common routines and constants for the client bootime setup stuff.
#
package libsetup;
use Exporter;
@ISA = "Exporter";
@EXPORT =
    qw ( libsetup_init libsetup_setvnodeid libsetup_settimeout cleanup_node 
	 doifconfig dohostnames domounts dotunnels check_nickname
	 doaccounts dorpms dotarballs dostartupcmd install_deltas
	 bootsetup nodeupdate startcmdstatus whatsmynickname dosyncserver
	 TBBackGround TBForkCmd vnodejailsetup plabsetup vnodeplabsetup
	 dorouterconfig jailsetup dojailconfig JailedMounts findiface
	 tmcctimeout libsetup_getvnodeid dotrafficconfig
	 ixpsetup dokeyhash donodeid libsetup_refresh

	 MFS REMOTE JAILED PLAB LOCALROOTFS IXP

	 CONFDIR TMCC TMIFC TMDELAY TMRPM TMTARBALLS TMHOSTS TMJAILNAME
	 TMNICKNAME HOSTSFILE TMSTARTUPCMD FINDIF TMTUNNELCONFIG
	 TMTRAFFICCONFIG TMROUTECONFIG TMLINKDELAY TMDELMAP TMMOUNTDB
	 TMPROGAGENTS TMPASSDB TMGROUPDB TMGATEDCONFIG
	 TMSYNCSERVER TMRCSYNCSERVER TMKEYHASH TMNODEID
       );

# Must come after package declaration!
use English;

# The tmcc library.
use libtmcc;

#
# This is the VERSION. We send it through to tmcd so it knows what version
# responses this file is expecting.
#
# BE SURE TO BUMP THIS AS INCOMPATIBILE CHANGES TO TMCD ARE MADE!
#
sub TMCD_VERSION()	{ 12; };
libtmcc::configtmcc("version", TMCD_VERSION());

# Control tmcc timeout.
sub libsetup_settimeout($) { libtmcc::configtmcc("timeout", $_[0]); };

# Redresh tmcc cache.
sub libsetup_refresh()	   { libtmcc::tmccgetconfig(); };

#
# For virtual (multiplexed nodes). If defined, tack onto tmcc command.
# and use in pathnames. Used in conjunction with jailed virtual nodes.
# I am also using this for subnodes; eventually everything will be subnodes.
#
my $vnodeid;
sub libsetup_setvnodeid($)
{
    my ($vid) = @_;

    if ($vid =~ /^([-\w]+)$/) {
	$vid = $1;
    }
    else {
	die("Bad data in vnodeid: $vid");
    }

    $vnodeid = $vid;
    libtmcc::configtmcc("subnode", $vnodeid);
}
sub libsetup_getvnodeid()
{
    return $vnodeid;
}

#
# True if running inside a jail. Set just below. 
# 
my $injail;

#
# True if running in a Plab vserver.
#
my $inplab;

#
# Ditto for IXP, although currently there is no "in" IXP setup; it
# is all done from outside.
#
my $inixp;

#
# Inside, there might be a tmcc proxy socket. 
#
my $tmccproxy;

# Load up the paths. Its conditionalized to be compatabile with older images.
# Note this file has probably already been loaded by the caller.
BEGIN
{
    if (! -e "/etc/emulab/paths.pm") {
	die("Yikes! Could not require /etc/emulab/paths.pm!\n");
    }
    require "/etc/emulab/paths.pm";
    import emulabpaths;

    #
    # Determine if running inside a jail. This affects the paths below.
    #
    if (-e "$BOOTDIR/jailname") {
	open(VN, "$BOOTDIR/jailname");
	my $vid = <VN>;
	close(VN);

	libsetup_setvnodeid($vid);
	$injail = 1;

	#
	# Temporary. Will move to tmcc library. 
	#
	if (-e "$BOOTDIR/proxypath") {
	    open(PP, "$BOOTDIR/proxypath");
	    $tmccproxy = <PP>;
	    close(PP);

	    if ($tmccproxy =~ /^([-\w\.\/]+)$/) {
		$tmccproxy = $1;
	    }
	    else {
		die("Bad data in tmccproxy path: $tmccproxy");
	    }
	}
    }

    # Determine if running inside a Plab vserver.
    if (-e "$BOOTDIR/plabname") {
	open(VN, "$BOOTDIR/plabname");
	my $vid = <VN>;
	close(VN);

	libsetup_setvnodeid($vid);
	$inplab = 1;
    }

    # Make sure these exist!
    if (! -e "$VARDIR/logs") {
	mkdir("$VARDIR", 0775);
	mkdir("$VARDIR/jails", 0775);
	mkdir("$VARDIR/db", 0755);
	mkdir("$VARDIR/logs", 0775);
	mkdir("$VARDIR/boot", 0775);
	mkdir("$VARDIR/lock", 0775);
    }
}

#
# The init routine. This is deprecated, but left behind in case an old
# liblocsetup is run against a new libsetup. Whenever a new libsetup
# is installed, better install the path module (see above) too!
#
sub libsetup_init($)
{
    my($path) = @_;

    $ETCDIR  = $path;
    $BINDIR  = $path;
    $VARDIR  = $path;
    $BOOTDIR = $path
}

#
# This "local" library provides the OS dependent part. 
#
use liblocsetup;

#
# These are the paths of various files and scripts that are part of the
# setup library.
#
sub TMCC()		{ "$BINDIR/tmcc"; }
sub TMHOSTS()		{ "$ETCDIR/hosts"; }
sub FINDIF()		{ "$BINDIR/findif"; }
sub HOSTSFILE()		{ "/etc/hosts"; }
#
# This path is valid only *outside* the jail when its setup.
# 
sub JAILDIR()		{ "$VARDIR/jails/$vnodeid"; }

#
# Also valid outside the jail, this is where we put local project storage.
#
sub LOCALROOTFS()	{ (REMOTE() ? "/users/local" : "$VARDIR/jails/local");}

#
# Okay, here is the path mess. There are three environments.
# 1. A local node where everything goes in one place ($VARDIR/boot).
# 2. A virtual node inside a jail or a Plab vserver ($VARDIR/boot).
# 3. A virtual (or sub) node, from the outside. 
#
# As for #3, whether setting up a old-style virtual node or a new style
# jailed node, the code that sets it up needs a different per-vnode path.
#
sub CONFDIR() {
    if ($injail || $inplab) {
	return $BOOTDIR;
    }
    if ($vnodeid) {
	return JAILDIR();
    }
    return $BOOTDIR;
}

#
# These go in /var/emulab. Good for all environments!
# 
sub TMMOUNTDB()		{ $VARDIR . "/db/mountdb"; }
sub TMSFSMOUNTDB()	{ $VARDIR . "/db/sfsmountdb"; }
sub TMPASSDB()		{ $VARDIR . "/db/passdb"; }
sub TMGROUPDB()		{ $VARDIR . "/db/groupdb"; }
#
# The rest of these depend on the environment running in (inside/outside jail).
# 
sub TMNICKNAME()	{ CONFDIR() . "/nickname";}
sub TMJAILNAME()	{ CONFDIR() . "/jailname";}
sub TMJAILCONFIG()	{ CONFDIR() . "/jailconfig";}
sub TMPLABCONFIG()	{ CONFDIR() . "/rc.plab";}
sub TMSTARTUPCMD()	{ CONFDIR() . "/startupcmd";}
sub TMPROGAGENTS()	{ CONFDIR() . "/progagents";}
sub TMIFC()		{ CONFDIR() . "/rc.ifc"; }
sub TMRPM()		{ CONFDIR() . "/rc.rpm";}
sub TMTARBALLS()	{ CONFDIR() . "/rc.tarballs";}
sub TMROUTECONFIG()     { CONFDIR() . "/rc.route";}
sub TMGATEDCONFIG()     { CONFDIR() . "/gated.conf";}
sub TMTRAFFICCONFIG()	{ CONFDIR() . "/rc.traffic";}
sub TMTUNNELCONFIG()	{ CONFDIR() . "/rc.tunnel";}
sub TMVTUNDCONFIG()	{ CONFDIR() . "/vtund.conf";}
sub TMDELAY()		{ CONFDIR() . "/rc.delay";}
sub TMLINKDELAY()	{ CONFDIR() . "/rc.linkdelay";}
sub TMDELMAP()		{ CONFDIR() . "/delay_mapping";}
sub TMSYNCSERVER()	{ CONFDIR() . "/syncserver";}
sub TMRCSYNCSERVER()	{ CONFDIR() . "/rc.syncserver";}
sub TMKEYHASH()		{ CONFDIR() . "/keyhash";}
sub TMNODEID()		{ CONFDIR() . "/nodeid";}

#
# Whether or not to use SFS (the self-certifying file system).  If this
# is 0, fall back to NFS.  Note that it doesn't hurt to set this to 1
# even if TMCD is not serving out SFS mounts, or if this node is not
# running SFS.  It'll deal and fall back to NFS.
#
my $USESFS		= 1;

#
# Some things never change.
# 
my $TARINSTALL  = "/usr/local/bin/install-tarfile %s %s %s";
my $RPMINSTALL  = "/usr/local/bin/install-rpm %s %s";
my $VTUND       = "/usr/local/sbin/vtund";

#
# This is a debugging thing for my home network.
#
my $NODE = "";
if (defined($ENV{'TMCCARGS'})) {
    if ($ENV{'TMCCARGS'} =~ /^([-\w\s]*)$/) {
	$NODE .= " $1";
    }
    else {
	die("Tainted TMCCARGS from environment: $ENV{'TMCCARGS'}!\n");
    }
}

# Locals
my $pid		= "";
my $eid		= "";
my $vname	= "";

# When on the MFS, we do a much smaller set of stuff.
# Cause of the way the packages are loaded (which I do not understand),
# this is computed on the fly instead of once.
sub MFS()	{ if (-e "$ETCDIR/ismfs") { return 1; } else { return 0; } }

#
# Same for a remote node.
#
sub REMOTE()	{ if (-e "$ETCDIR/isrem") { return 1; } else { return 0; } }

#
# Same for a control node.
#
sub CONTROL()	{ if (-e "$ETCDIR/isctrl") { return 1; } else { return 0; } }

#
# Are we jailed? See above.
#
sub JAILED()	{ if ($injail) { return $vnodeid; } else { return 0; } }

#
# Are we on plab?
#
sub PLAB()	{ if ($inplab) { return $vnodeid; } else { return 0; } }

#
# Are we on an IXP
#
sub IXP()	{ if ($inixp) { return $vnodeid; } else { return 0; } }

#
# Do not try this on the MFS since it has such a wimpy perl installation.
#
if (!MFS()) {
    require Socket;
    import Socket;
}

#
# Reset to a moderately clean state.
#
sub cleanup_node ($) {
    my ($scrub) = @_;
    
    print STDOUT "Cleaning node; removing configuration files ...\n";
    unlink TMIFC, TMRPM, TMSTARTUPCMD, TMTARBALLS;
    unlink TMROUTECONFIG, TMTRAFFICCONFIG, TMTUNNELCONFIG;
    unlink TMDELAY, TMLINKDELAY, TMPROGAGENTS, TMSYNCSERVER, TMRCSYNCSERVER;
    unlink TMMOUNTDB . ".db";
    unlink TMSFSMOUNTDB . ".db";
    unlink "$VARDIR/db/rtabid";

    #
    # If scrubbing, remove the password/group file DBs so that we revert
    # to base set.
    # 
    if ($scrub) {
	unlink TMNICKNAME;
	unlink TMPASSDB . ".db";
	unlink TMGROUPDB . ".db";
    }

    if (! REMOTE()) {
	printf STDOUT "Resetting %s file\n", HOSTSFILE;
	if (system($CP, "-f", TMHOSTS, HOSTSFILE) != 0) {
	    printf "Could not copy default %s into place: $!\n", HOSTSFILE;
	    exit(1);
	}
    }

    return os_cleanup_node($scrub);
}

#
# Check node allocation. If the nickname file has been created, use
# that to avoid load on tmcd.
#
# Returns 0 if node is free. Returns list (pid/eid/vname) if allocated.
#
sub check_status ()
{
    my $status;
    my @tmccresults;

    if (tmcc(TMCCCMD_STATUS, undef, \@tmccresults) < 0) {
	warn("*** WARNING: Could not get status from server!\n");
	return -1;
    }
    $status = $tmccresults[0];

    if ($status =~ /^FREE/) {
	unlink TMNICKNAME;
	return 0;
    }
    
    if ($status =~ /ALLOCATED=([-\@\w]*)\/([-\@\w]*) NICKNAME=([-\@\w]*)/) {
	$pid   = $1;
	$eid   = $2;
	$vname = $3;
    }
    else {
	warn "*** WARNING: Error getting reservation status\n";
	return -1;
    }
    
    #
    # Stick our nickname in a file in case someone wants it.
    # Do not overwrite; we want to save the original info until later.
    # See bootsetup; indicates project change!
    #
    if (! -e TMNICKNAME()) {
	system("echo '$vname.$eid.$pid' > " . TMNICKNAME());
    }
    
    return ($pid, $eid, $vname);
}

#
# Check cached nickname. Its okay if we have been deallocated and the info
# is stale. The node will notice that later.
# 
sub check_nickname()
{
    if (-e TMNICKNAME) {
	my $nickfile = TMNICKNAME;
	my $nickinfo = `cat $nickfile`;

	if ($nickinfo =~ /([-\@\w]*)\.([-\@\w]*)\.([-\@\w]*)/) {
	    $vname = $1;
	    $eid   = $2;
	    $pid   = $3;

	    return ($pid, $eid, $vname);
	}
    }
    return check_status();
}

#
# Process mount directives from TMCD. We keep track of all the mounts we
# have added in here so that we delete just the mounts we added, when
# project membership changes. Same goes for project directories on shared
# nodes. We use a simple perl DB for that.
#
sub domounts()
{
    my %MDB;
    my %mounts;
    my %deletes;
    my %sfsmounts;
    my %sfsdeletes;
    my @tmccresults;

    if (tmcc(TMCCCMD_MOUNTS, undef , \@tmccresults) < 0) {
	warn("*** WARNING: Could not get mount list from server!\n");
	return -1;
    }

    foreach my $str (@tmccresults) {
	if ($str =~ /^REMOTE=([-:\@\w\.\/]+) LOCAL=([-\@\w\.\/]+)/) {
	    $mounts{$1} = $2;
	}
	elsif ($str =~ /^SFS REMOTE=([-:\@\w\.\/]+) LOCAL=([-\@\w\.\/]+)/) {
	    $sfsmounts{$1} = $2;
	}
	else {
	    warn "*** WARNING: Malformed mount information: $str\n";
	}
    }
    
    #
    # The MFS version does not support (or need) this DB stuff. Just mount
    # them up.
    #
    if (MFS()) {
	while (($remote, $local) = each %mounts) {
	    if (! -e $local) {
		if (! os_mkdir($local, "0770")) {
		    warn "*** WARNING: Could not make directory $local: $!\n";
		    next;
		}
	    }
	
	    print STDOUT "  Mounting $remote on $local\n";
	    if (system("$NFSMOUNT $remote $local")) {
		warn "*** WARNING: Could not $NFSMOUNT ".
		    "$remote on $local: $!\n";
		next;
	    }
	}
	return 0;
    }

    dbmopen(%MDB, TMMOUNTDB, 0660);
    
    #
    # First mount all the mounts we are told to. For each one that is not
    # currently mounted, and can be mounted, add it to the DB.
    # 
    while (($remote, $local) = each %mounts) {
	if (defined($MDB{$remote})) {
	    next;
	}

	if (! -d $local) {
	    # Leftover SFS link.
	    if (-l $local) {
		unlink($local) or
		    warn "*** WARNING: Could not unlink $local: $!\n";
	    }
	    if (! os_mkdir($local, "0770")) {
		warn "*** WARNING: Could not make directory $local: $!\n";
		next;
	    }
	}
	
	print STDOUT "  Mounting $remote on $local\n";
	if (system("$NFSMOUNT $remote $local")) {
	    warn "*** WARNING: Could not $NFSMOUNT $remote on $local: $!\n";
	    next;
	}

	$MDB{$remote} = $local;
    }

    #
    # Now unmount the ones that we mounted previously, but are now no longer
    # in the mount set (as told to us by the TMCD). Note, we cannot delete 
    # them directly from MDB since that would mess up the foreach loop, so
    # just stick them in temp and postpass it.
    #
    while (($remote, $local) = each %MDB) {
	if (defined($mounts{$remote})) {
	    next;
	}

	print STDOUT "  Unmounting $local\n";
	if (system("$UMOUNT $local")) {
	    warn "*** WARNING: Could not unmount $local\n";
	    next;
	}
	
	#
	# Only delete from set if we can actually unmount it. This way
	# we can retry it later (or next time).
	# 
	$deletes{$remote} = $local;
    }
    while (($remote, $local) = each %deletes) {
	delete($MDB{$remote});
    }

    # Write the DB back out!
    dbmclose(%MDB);

    #
    # Now, do basically the same thing over again, but this time for
    # SFS mounted stuff
    #

    if (scalar(%sfsmounts)) {
	dbmopen(%MDB, TMSFSMOUNTDB, 0660);
	
	#
	# First symlink all the mounts we are told to. For each one
	# that is not currently symlinked, and can be, add it to the
	# DB.
	#
	while (($remote, $local) = each %sfsmounts) {
	    if (-l $local) {
		if (readlink($local) eq ("/sfs/" . $remote)) {
		    $MDB{$remote} = $local;
		    next;
		}
		if (readlink($local) ne ("/sfs/" . $remote)) {
		    print STDOUT "  Unlinking incorrect symlink $local\n";
		    if (! unlink($local)) {
			warn "*** WARNING: Could not unlink $local: $!\n";
			next;
		    }
		}
	    }
	    elsif (-d $local) {
		if (! rmdir($local)) {
		    warn "*** WARNING: Could not rmdir $local: $!\n";
		    next;
		}
	    }
	    
	    $dir = $local;
	    $dir =~ s/(.*)\/[^\/]*$/$1/;
	    if ($dir ne "" && ! -e $dir) {
		print STDOUT "  Making directory $dir\n";
		if (! os_mkdir($dir, "0755")) {
		    warn "*** WARNING: Could not make directory $local: $!\n";
		    next;
		}
	    }
	    print STDOUT "  Symlinking $remote on $local\n";
	    if (! symlink("/sfs/" . $remote, $local)) {
		warn "*** WARNING: Could not make symlink $local: $!\n";
		next;
	    }
	    
	    $MDB{$remote} = $local;
	}

	#
	# Now delete the ones that we symlinked previously, but are
	# now no longer in the mount set (as told to us by the TMCD).
	# Note, we cannot delete them directly from MDB since that
	# would mess up the foreach loop, so just stick them in temp
	# and postpass it.
	#
	while (($remote, $local) = each %MDB) {
	    if (defined($sfsmounts{$remote})) {
		next;
	    }
	    
	    if (! -e $local) {
		$sfsdeletes{$remote} = $local;
		next;
	    }
	    
	    print STDOUT "  Deleting symlink $local\n";
	    if (! unlink($local)) {
		warn "*** WARNING: Could not delete $local: $!\n";
		next;
	    }
	    
	    #
	    # Only delete from set if we can actually unlink it.  This way
	    # we can retry it later (or next time).
	    #
	    $sfsdeletes{$remote} = $local;
	}
	while (($remote, $local) = each %sfsdeletes) {
	    delete($MDB{$remote});
	}

	# Write the DB back out!
	dbmclose(%MDB);	
    }
    else {
	# There were no SFS mounts reported, so disable SFS
	$USESFS = 0;
    }

    return 0;
}

#
# Aux function called from the mkjail code to do mounts outside
# of a jail, and return the list of mounts that were created. Can use
# either NFS or local loopback. Maybe SFS someday. Local only, of course.
# 
sub JailedMounts($$$)
{
    my ($vid, $rootpath, $usenfs) = @_;
    my @mountlist = ();
    my $mountstr;

    #
    # No NFS mounts on remote nodes.
    # 
    if (REMOTE()) {
	return ();
    }

    if ($usenfs) {
	$mountstr = $NFSMOUNT;
    }
    else {
	$mountstr = $LOOPBACKMOUNT;
    }

    #
    # Mount same set of existing mounts. A hack, but this whole NFS thing
    # is a serious hack inside jails.
    #
    dbmopen(%MDB, TMMOUNTDB, 0444);
    
    while (my ($remote, $path) = each %MDB) {
	$local = "$rootpath$path";
	    
	if (! -e $local) {
	    if (! os_mkdir($local, "0770")) {
		warn "*** WARNING: Could not make directory $local: $!\n";
		next;
	    }
	}
	
	if (! $usenfs) {
	    $remote = $path;
	}

	print STDOUT "  Mounting $remote on $local\n";
	if (system("$mountstr $remote $local")) {
	    warn "*** WARNING: Could not $mountstr $remote on $local: $!\n";
	    next;
	}
	push(@mountlist, $path);
    }
    dbmclose(%MDB);	   
    return @mountlist;
}

#
# Do SFS hostid setup.
# Creates an SFS host key for this node, if it doesn't already exist,
# and sends it to TMCD
#
sub dosfshostid ()
{
    my $myhostid;

    # Do I already have a host key?
    if (! -e "/etc/sfs/sfs_host_key") {
	warn "*** This node does not have a host key, skipping SFS stuff\n";
	$USESFS = 0;
	return 1;
    }

    # Give hostid to TMCD
    if (-d "/usr/local/lib/sfs-0.6") {
	$myhostid = `sfskey hostid - 2>/dev/null`;
    }
    else {
	$myhostid = `sfskey hostid -s authserv - 2>/dev/null`;
    }
    if (! $?) {
	if ( $myhostid =~ /^([-\.\w_]*:[a-z0-9]*)$/ ) {
	    $myhostid = $1;
	    print STDOUT "  Hostid: $myhostid\n";
	    tmcc(TMCCCMD_SFSHOSTID, "$myhostid");
	}
	elsif ( $myhostid =~ /^(@[-\.\w_]*,[a-z0-9]*)$/ ) {
	    $myhostid = $1;
	    print STDOUT "  Hostid: $myhostid\n";
	    tmcc(TMCCCMD_SFSHOSTID, "$myhostid");
	}
	else {
	    warn "*** WARNING: Invalid hostid\n";
	}
    }
    else {
	warn "*** WARNING: Could not retrieve this node's SFShostid!\n";
	$USESFS = 0;
    }

    return 0;
}

#
# Do interface configuration.    
# Write a file of ifconfig lines, which will get executed.
#
sub doifconfig (;$)
{
    my ($rtabid)     = @_;
    my @tmccresults  = ();
    my $upcmds       = "";
    my $downcmds     = "";
    my @ifacelist    = ();	# IXP siilly
    my $xifslist     = ();	# NSE/Gated sillyness?

    #
    # Kinda ugly, but there is too much perl goo included by Socket to put it
    # on the MFS. 
    # 
    if (MFS()) {
	return 0;
    }

    if (tmcc(TMCCCMD_IFC, undef, \@tmccresults) < 0) {
	warn("*** WARNING: Could not get interface config from server!\n");
	return -1;
    }

    #
    # XXX hack: workaround for tmcc cmd failure inside TCL
    #     storing the output of a few tmcc commands in
    #     $BOOTDIR files for use by NSE
    if (!REMOTE() && !JAILED()) {
	if (open(IFCFG, ">$BOOTDIR/tmcc.ifconfig")) {
	    foreach my $str (@tmccresults) {
		print IFCFG "$str";
	    }
	    close(IFCFG);
	}
	else {
	    warn("*** WARNING: ".
		 "Cannot open file $BOOTDIR/tmcc.ifconfig: $!\n");
	}
	# More NSE goop below. 
    }

    my $ethpat  = q(IFACETYPE=(\w*) INET=([0-9.]*) MASK=([0-9.]*) MAC=(\w*) );
    $ethpat    .= q(SPEED=(\w*) DUPLEX=(\w*) IPALIASES="(.*)" IFACE=(\w*));

    my $vethpat = q(IFACETYPE=(\w*) INET=([0-9.]*) MASK=([0-9.]*) ID=(\d*) );
    $vethpat   .= q(VMAC=(\w*) PMAC=(\w*));

    foreach my $iface (@tmccresults) {
	if ($iface =~ /$ethpat/) {
	    my $inet     = $2;
	    my $mask     = $3;
	    my $mac      = $4;
	    my $speed    = $5; 
	    my $duplex   = $6;
	    my $aliases  = $7;
	    my $iface    = $8;

	    if (($iface ne "") ||
		($iface = findiface($mac))) {
		if (JAILED()) {
		    next;
		}
		push(@xifslist, $iface);

		#
		# Rather than try to wedge the IXP in, I am going with
		# a new approach. Parse the results from tmcd into a
		# simple data structure, and return that for the caller
		# to use. Might want to use a perl module at some point.
		#
		my $ifconfig = {};
		    
		$ifconfig->{"IPADDR"}   = $inet;
		$ifconfig->{"IPMASK"}   = $mask;
		$ifconfig->{"MAC"}      = $mac;
		$ifconfig->{"SPEED"}    = $speed;
		$ifconfig->{"DUPLEX"}   = $duplex;
		$ifconfig->{"ALIASES"}  = $aliases;
		$ifconfig->{"IFACE"}    = $iface;
		push(@ifacelist, $ifconfig);

		if (IXP()) {
		    next;
		}

		my ($upline, $downline) =
		    os_ifconfig_line($iface, $inet, $mask,
				     $speed, $duplex, $aliases,$rtabid);
		    
		$upcmds   .= "$upline\n    "
		    if (defined($upline));
		$upcmds   .= TMROUTECONFIG . " $inet up\n";
		
		$downcmds .= TMROUTECONFIG . " $inet down\n    ";
		$downcmds .= "$downline\n    "
		    if (defined($downline));

		# There could be routes for each alias.
		foreach my $alias (split(',', $aliases)) {
		    $upcmds   .= TMROUTECONFIG . " $alias up\n";
		    $downcmds .= TMROUTECONFIG . " $alias down\n";
		}
	    }
	    else {
		warn "*** WARNING: Bad MAC: $mac\n";
	    }
	}
	elsif ($iface =~ /$vethpat/) {
	    my $iface    = undef;
	    my $inet     = $2;
	    my $mask     = $3;
	    my $id       = $4;
	    my $vmac     = $5;
	    my $pmac     = $6; 

	    if (JAILED()) {
		if ($iface = findiface($vmac)) {
		    push(@xifslist, $iface);
		}
		next;
	    }

	    if ($pmac eq "none" ||
		($iface = findiface($pmac))) {
		push(@xifslist, $iface)
		    if (defined($iface));

		my ($upline, $downline) =
		    os_ifconfig_veth($iface, $inet, $mask, $id, $vmac,$rtabid);
		    
		$upcmds   .= "$upline\n    ";
		$upcmds   .= TMROUTECONFIG . " $inet up\n";
		
		$downcmds .= TMROUTECONFIG . " $inet down\n    ";
		$downcmds .= "$downline\n    "
		    if (defined($downline));
	    }
	    else {
		warn "*** WARNING: Bad PMAC: $pmac\n";
	    }
	}
	else {
	    warn "*** WARNING: Bad ifconfig line: $iface\n";
	}
    }
    if (@tmccresults && !(JAILED() || IXP())) {
	#
	# Local file into which we write ifconfig commands (as a shell script).
	#
	if (open(IFC, ">" . TMIFC)) {
	    print IFC "#!/bin/sh\n";
	    print IFC "# auto-generated by libsetup.pm, DO NOT EDIT\n";
	    print IFC "if [ x\$1 = x ]; ".
		      "then action=enable; else action=\$1; fi\n";
	    print IFC "case \"\$action\" in\n";
	    print IFC "  enable)\n";
	    print IFC "    $upcmds\n";
	    print IFC "    ;;\n";
	    print IFC "  disable)\n";
	    print IFC "    $downcmds\n";
	    print IFC "    ;;\n";
	    print IFC "esac\n";
	    close(IFC);
	    chmod(0755, TMIFC);
	}
	else {
	    warn("*** WARNING: Could not open " . TMIFC . ": $!\n");
	}
    }

    #
    # Create the interface list file.
    # Control net is always first.
    #
    if (open(XIFS, ">$BOOTDIR/tmcc.ifs")) {
	print XIFS `control_interface`;
	foreach my $xif (@xifslist) {
	    print XIFS "$xif\n";
	}
	close(XIFS);
    }
    else {
	warn("*** WARNING: Cannot open file $BOOTDIR/tmcc.ifs: $!\n");
    }

    return @ifacelist
	if (IXP());
    return 0;
}

#
# Convert from MAC to iface name (eth0/fxp0/etc) using little helper program.
# 
sub findiface($)
{
    my($mac) = @_;
    my($iface);

    open(FIF, FINDIF . " $mac |")
	or die "Cannot start " . FINDIF . ": $!";

    $iface = <FIF>;
    
    if (! close(FIF)) {
	return 0;
    }
    
    $iface =~ s/\n//g;
    return $iface;
}

#
# Do router configuration stuff. This just writes a file for someone else
# to deal with.
#
sub dorouterconfig (;$)
{
    my ($rtabid) = @_;
    my @stuff    = ();
    my $routing  = 0;
    my %upmap    = ();
    my %downmap  = ();
    my @routes   = ();

    if (tmcc(TMCCCMD_ROUTING, undef, \@stuff) < 0) {
	warn("*** WARNING: Could not get routes from server!\n");
	return -1;
    }
    # IXP sillyness.
    return ()
	if (! @stuff);
    
    #
    # Look for router type. If none, we still write the file since other
    # scripts expect this to exist.
    # 
    foreach my $line (@stuff) {
	if (($line =~ /ROUTERTYPE=(.+)/) && ($1 ne "none")) {
	    $routing = 1;
	    last;
	}
    }

    if (!open(RC, ">" . TMROUTECONFIG)) {
	warn("*** WARNING: Could not open " . TMROUTECONFIG . ": $!\n");
	return -1;
    }

    print RC "#!/bin/sh\n";
    print RC "# auto-generated by libsetup.pm, DO NOT EDIT\n";

    if (! $routing) {
	print RC "true\n";
	close(RC);
	chmod(0755, TMROUTECONFIG);
	# IXP sillyness.
	return @routes;
    }

    #
    # Now convert static route info into OS route commands
    # Also check for use of gated and remember it.
    #
    my $usegated = 0;
    my $pat;

    #
    # ROUTERTYPE=manual
    # ROUTE DEST=192.168.2.3 DESTTYPE=host DESTMASK=255.255.255.0 \
    #	NEXTHOP=192.168.1.3 COST=0 SRC=192.168.4.5
    #
    # The SRC ip is used to determine which interface the routes are
    # associated with, since nexthop alone is not enough cause of the 
    #
    $pat = q(ROUTE DEST=([0-9\.]*) DESTTYPE=(\w*) DESTMASK=([0-9\.]*) );
    $pat .= q(NEXTHOP=([0-9\.]*) COST=([0-9]*) SRC=([0-9\.]*));

    my $usemanual = 0;
    foreach my $line (@stuff) {
	if ($line =~ /ROUTERTYPE=(gated|ospf)/) {
	    $usegated = 1;
	} elsif ($line =~ /ROUTERTYPE=(manual|static)/) {
	    $usemanual = 1;
	} elsif ($usemanual && $line =~ /$pat/) {
	    my $dip   = $1;
	    my $rtype = $2;
	    my $dmask = $3;
	    my $gate  = $4;
	    my $cost  = $5;
	    my $sip   = $6;
	    my $rcline;

	    #
	    # For IXP.
	    #
	    my $rconfig = {};
		    
	    $rconfig->{"IPADDR"}   = $dip;
	    $rconfig->{"TYPE"}     = $rtype;
	    $rconfig->{"IPMASK"}   = $dmask;
	    $rconfig->{"GATEWAY"}  = $gate;
	    $rconfig->{"COST"}     = $cost;
	    $rconfig->{"SRCIPADDR"}= $sip;
	    push(@routes, $rconfig);

	    if (! defined($upmap{$sip})) {
		$upmap{$sip} = [];
		$downmap{$sip} = [];
	    }
	    $rcline = os_routing_add_manual($rtype, $dip,
					    $dmask, $gate, $cost, $rtabid);
	    push(@{$upmap{$sip}}, $rcline);
	    $rcline = os_routing_del_manual($rtype, $dip,
					    $dmask, $gate, $cost, $rtabid);
	    push(@{$downmap{$sip}}, $rcline);
	} else {
	    warn "*** WARNING: Bad routing line: $line\n";
	}
    }

    print RC "case \"\$1\" in\n";
    foreach my $arg (keys(%upmap)) {
	print RC "  $arg)\n";
	print RC "    case \"\$2\" in\n";
	print RC "      up)\n";
	foreach my $rcline (@{$upmap{$arg}}) {
	    print RC "        $rcline\n";
	}
	print RC "      ;;\n";
	print RC "      down)\n";
	foreach my $rcline (@{$downmap{$arg}}) {
	    print RC "        $rcline\n";
	}
	print RC "      ;;\n";
	print RC "    esac\n";
	print RC "  ;;\n";
    }
    print RC "  enable)\n";

    #
    # Turn on IP forwarding
    #
    print RC "    " . os_routing_enable_forward() . "\n";

    #
    # Finally, enable gated if desired.
    #
    # Note that we allow both manually-specified static routes and gated
    # though more work may be needed on the gated config files to make
    # this work (i.e., to import existing kernel routes).
    #
    # XXX if rtabid is set, we are setting up routing from outside a
    # jail on behalf of a jail.  We don't want to enable gated in this
    # case, it will be run inside the jail.
    #
    if ($usegated && !defined($rtabid)) {
	print RC "    " . gatedsetup() . "\n";
    }
    print RC "  ;;\n";

    #
    # For convenience, allup and alldown.
    #
    print RC "  enable-routes)\n";
    foreach my $arg (keys(%upmap)) {
	foreach my $rcline (@{$upmap{$arg}}) {
	    print RC "    $rcline\n";
	}
    }
    print RC "  ;;\n";
    
    print RC "  disable-routes)\n";
    foreach my $arg (keys(%downmap)) {
	foreach my $rcline (@{$downmap{$arg}}) {
	    print RC "    $rcline\n";
	}
    }
    print RC "  ;;\n";
    print RC "esac\n";
    print RC "exit 0\n";

    close(RC);
    chmod(0755, TMROUTECONFIG);

    return @routes;
}

sub gatedsetup ()
{
    my ($cnet, @xifs) = split('\n', `cat $BOOTDIR/tmcc.ifs`);

    open(IFS, ">" . TMGATEDCONFIG)
	or die("Could not open " . TMGATEDCONFIG . ": $!");

    print IFS "# auto-generated by libsetup.pm, DO NOT EDIT\n\n";
    #
    # XXX hack: in a jail, the control net is an IP alias with a host mask.
    # This blows gated out of the water, so we have to make the control
    # interface appear to have a subnet mask.
    #
    if (JAILED() && -e "$BOOTDIR/myip") {
	my $hostip = `cat $BOOTDIR/myip`;
	chomp($hostip);
	print IFS "interfaces {\n".
	    "\tdefine subnet local $hostip netmask 255.240.0.0;\n};\n";
    }
    print IFS "smux off;\nrip off;\nospf on {\n";
    print IFS "\tbackbone {\n\t\tinterface $cnet { passive; };\n\t};\n";
    print IFS "\tarea 0.0.0.2 {\n\t\tauthtype none;\n";

    foreach my $xif (@xifs) {
	print IFS "\t\tinterface $xif { priority 1; };\n";
    }

    print IFS "\t};\n};\n";
    close(IFS);

    return os_routing_enable_gated(TMGATEDCONFIG);
}

#
# Host names configuration (/etc/hosts). 
#
sub dohostnames (;$)
{
    my ($pathname) = @_;
    my $HTEMP;
    my @tmccresults;

    $pathname = HOSTSFILE()
	if (!defined($pathname));
    $HTEMP = "${pathname}.new";

    if (tmcc(TMCCCMD_HOSTS, undef, \@tmccresults) < 0) {
	warn("*** WARNING: Could not get hosts file from server!\n");
	return -1;
    }
    # Important; if no results then do nothing. Do not want to kill
    # the existing hosts file.
    return 0
	if (! @tmccresults);

    #
    # Note, we no longer start with the 'prototype' file here, because we have
    # to make up a localhost line that's properly qualified.
    #
    if (!open(HOSTS, ">$HTEMP")) {
	warn("*** WARNING: Could not open $HTEMP: $!\n");
	return -1;
    }

    my $localaliases = "loghost";

    #
    # Find out our domain name, so that we can qualify the localhost entry
    #
    my $hostname = `hostname`;
    if ($hostname =~ /[^.]+\.(.+)/) {
	$localaliases .= " localhost.$1";
    }
    
    #
    # First, write a localhost line into the hosts file - we have to know the
    # domain to use here
    #
    print HOSTS os_etchosts_line("localhost", "127.0.0.1",
				 $localaliases), "\n";

    #
    # Now convert each hostname into hosts file representation and write
    # it to the hosts file. Note that ALIASES is for backwards compat.
    # Should go away at some point.
    #
    my $pat  = q(NAME=([-\w\.]+) IP=([0-9\.]*) ALIASES=\'([-\w\. ]*)\');

    foreach my $str (@tmccresults) {
	if ($str =~ /$pat/) {
	    my $name    = $1;
	    my $ip      = $2;
	    my $aliases = $3;
	    
	    my $hostline = os_etchosts_line($name, $ip, $aliases);
	    
	    print HOSTS  "$hostline\n";
	}
	else {
	    warn("*** WARNING: Bad hosts line: $str\n");
	}
    }
    close(HOSTS);
    system("mv -f $HTEMP $pathname") == 0 or
	warn("*** WARNING: Could not mv $HTEMP to $pathname!\n");

    return 0;
}

sub doaccounts()
{
    my @tmccresults;
    my %newaccounts = ();
    my %newgroups   = ();
    my %pubkeys1    = ();
    my %pubkeys2    = ();
    my @sfskeys     = ();
    my %deletes     = ();
    my %lastmod     = ();
    my %PWDDB;
    my %GRPDB;

    if (tmcc(TMCCCMD_ACCT, undef, \@tmccresults) < 0) {
	warn("*** WARNING: Could not get account info from server!\n");
	return -1;
    }
    # Important; if no results then do nothing. We do not want to remove
    # accounts cause the server failed to give us anything!
    return 0
	if (! @tmccresults);

    #
    # The strategy is to keep a record of all the groups and accounts
    # added by the testbed system so that we know what to remove. We
    # use a vanilla perl dbm for that, one for the groups and one for
    # accounts. 
    #
    # First just get the current set of groups/accounts from tmcd.
    #
    foreach my $str (@tmccresults) {
	if ($str =~ /^ADDGROUP NAME=([-\@\w.]+) GID=([0-9]+)/) {
	    #
	    # Group info goes in the hash table.
	    #
	    my $gname = "$1";
	    
	    if (REMOTE() && !JAILED() && !PLAB()) {
		$gname = "emu-${gname}";
	    }
	    $newgroups{"$gname"} = $2
	}
	elsif ($str =~ /^ADDUSER LOGIN=([0-9A-Za-z]+)/) {
	    #
	    # Account info goes in the hash table.
	    # 
	    $newaccounts{$1} = $str;
	    next;
	}
	elsif ($str =~ /^PUBKEY LOGIN=([0-9A-Za-z]+) KEY="(.*)"/) {
	    #
	    # Keys go into hash as a list of keys.
	    #
	    my $login = $1;
	    my $key   = $2;

	    #
	    # P1 or P2 key. Must be treated differently below.
	    #
	    if ($key =~ /^\d+\s+.*$/) {
		if (! defined($pubkeys1{$login})) {
		    $pubkeys1{$login} = [];
		}
		push(@{$pubkeys1{$login}}, $key);
	    }
	    else {
		if (! defined($pubkeys2{$login})) {
		    $pubkeys2{$login} = [];
		}
		push(@{$pubkeys2{$login}}, $key);
	    }
	    next;
	}
	elsif ($str =~ /^SFSKEY KEY="(.*)"/) {
	    #
	    # SFS key goes into the array.
	    #
	    push(@sfskeys, $1);
	    next;
	}
	else {
	    warn("*** WARNING: Bad accounts line: $str\n");
	}
    }

    if (! MFS()) {
	#
	# On the MFS, these will just start out as empty hashes.
	# 
	dbmopen(%PWDDB, TMPASSDB, 0660) or
	    die("Cannot open " . TMPASSDB . ": $!\n");
	
	dbmopen(%GRPDB, TMGROUPDB, 0660) or
	    die("Cannot open " . TMGROUPDB . ": $!\n");
    }

    #
    # Create any groups that do not currently exist. Add each to the
    # DB as we create it.
    #
    while (($group, $gid) = each %newgroups) {
	my ($exists,undef,$curgid) = getgrnam($group);
	
	if ($exists) {
	    if ($gid != $curgid) {
		warn "*** WARNING: $group/$gid mismatch with existing group\n";
	    }
	    next;
	}

	print "Adding group: $group/$gid\n";
	    
	if (os_groupadd($group, $gid)) {
	    warn "*** WARNING: Error adding new group $group/$gid\n";
	    next;
	}
	# Add to DB only if successful. 
	$GRPDB{$group} = $gid;
    }

    #
    # Now remove the ones that we created previously, but are now no longer
    # in the group set (as told to us by the TMCD). Note, we cannot delete 
    # them directly from the hash since that would mess up the foreach loop,
    # so just stick them in temp and postpass it.
    #
    while (($group, $gid) = each %GRPDB) {
	if (defined($newgroups{$group})) {
	    next;
	}

	print "Removing group: $group/$gid\n";
	
	if (os_groupdel($group)) {
	    warn "*** WARNING: Error removing group $group/$gid\n";
	    next;
	}
	# Delete from DB only if successful. 
	$deletes{$group} = $gid;
    }
    while (($group, $gid) = each %deletes) {
	delete($GRPDB{$group});
    }
    %deletes = ();

    # Write the DB back out!
    if (! MFS()) {
	dbmclose(%GRPDB);
    }

    #
    # Repeat the same sequence for accounts, except we remove old accounts
    # first. 
    # 

    # XXX: hack, hack, hack - Jay requested that user "games" be removed from
    # plab nodes since conflicts with his UID.
    os_userdel("games");

    while (($login, $info) = each %PWDDB) {
	my $uid = $info;
	
	#
	# Split out the uid from the serial. Note that this was added later
	# so existing DBs might not have a serial yet. We save the serial
	# for later. 
	#
	if ($info =~ /(\d*):(\d*)/) {
	    $uid = $1;
	    $lastmod{$login} = $2;
	}
	
	if (defined($newaccounts{$login})) {
	    next;
	}

	my ($exists,undef,$curuid,undef,
	    undef,undef,undef,$homedir) = getpwnam($login);

	#
	# If the account is gone, someone removed it by hand. Remove it
	# from the DB so we do not keep trying.
	#
	if (! defined($exists)) {
	    warn "*** WARNING: Account for $login was already removed!\n";
	    $deletes{$login} = $login;
	    next;
	}

	#
	# Check for mismatch, just in case. If there is a mismatch remove it
	# from the DB so we do not keep trying.
	#
	if ($uid != $curuid) {
	    warn "*** WARNING: ".
		 "Account uid for $login has changed ($uid/$curuid)!\n";
	    $deletes{$login} = $login;
	    next;
	}
	
	print "Removing user: $login\n";
	
	if (os_userdel($login) != 0) {
	    warn "*** WARNING: Error removing user $login\n";
	    next;
	}

	#
	# Remove the home dir. 
	#
	# Must ask for the current home dir in case it came from pw.conf.
	#
	if (defined($homedir) &&
	    index($homedir, "/${login}")) {
	    if (os_homedirdel($login, $homedir) != 0) {
	        warn "*** WARNING: Could not remove homedir $homedir.\n";
	    }
	}
	
	# Delete from DB only if successful. 
	$deletes{$login} = $login;
    }
    
    while (($login, $foo) = each %deletes) {
	delete($PWDDB{$login});
    }

    my $pat = q(ADDUSER LOGIN=([0-9A-Za-z]+) PSWD=([^:]+) UID=(\d+) GID=(.*) );
    $pat   .= q(ROOT=(\d) NAME="(.*)" HOMEDIR=(.*) GLIST="(.*)" );
    $pat   .= q(SERIAL=(\d+) EMAIL="([-\w\@\.\+]+)" SHELL=([-\w]*));

    while (($login, $info) = each %newaccounts) {
	if ($info =~ /$pat/) {
	    $pswd  = $2;
	    $uid   = $3;
	    $gid   = $4;
	    $root  = $5;
	    $name  = $6;
	    $hdir  = $7;
	    $glist = $8;
	    $serial= $9;
	    $email = $10;
	    $shell = $11;
	    if ( $name =~ /^(([^:]+$|^))$/ ) {
		$name = $1;
	    }

	    #
	    # See if update needed, based on the serial number we get.
	    # If its different, the account info has changed.
	    # 
	    my $doupdate = 0;
	    if (!defined($lastmod{$login}) || $lastmod{$login} != $serial) {
		$doupdate = 1;
	    }
	    
	    my ($exists,undef,$curuid) = getpwnam($login);

	    if ($exists) {
		if (!defined($PWDDB{$login})) {
		    warn "*** WARNING: ".
			 "Skipping since $login existed before EmulabMan!\n";
		    next;
		}
		if ($curuid != $uid) {
		    warn "*** WARNING: ".
			 "$login/$uid uid mismatch with existing login.\n";
		    next;
		}
		if ($doupdate) {
		    print "Updating: ".
			"$login/$uid/$gid/$root/$name/$hdir/$glist\n";
		    
		    os_usermod($login, $gid, "$glist", $pswd, $root, $shell);

		    #
		    # Note that we changed the info for next time.
		    # 
		    $PWDDB{$login} = "$uid:$serial";
		}
	    }
	    else {
		print "Adding: $login/$uid/$gid/$root/$name/$hdir/$glist\n";

		if (os_useradd($login, $uid, $gid, $pswd, 
			       "$glist", $hdir, $name, $root, $shell)) {
		    warn "*** WARNING: Error adding new user $login\n";
		    next;
		}

		if (PLAB() && ! -e $hdir) {
		    if (! os_mkdir($hdir, "0755")) {
			warn "*** WARNING: Error creating user homedir\n";
			next;
		    }
		    chown($uid, $gid, $hdir);
		}
		
		# Add to DB only if successful. 
		$PWDDB{$login} = "$uid:$serial";
	    }

	    #
	    # Remote nodes and local control nodes get this. 
	    # 
	    if ((REMOTE() || CONTROL()) && $doupdate) {
		#
		# Must ask for the current home dir since we rely on pw.conf.
		#
		my (undef,undef,undef,undef,
		    undef,undef,undef,$homedir) = getpwuid($uid);
		my $sshdir  = "$homedir/.ssh";
		my $forward = "$homedir/.forward";

		#
		# Create .ssh dir and populate it with an authkeys file.
		#
		TBNewsshKeyfile($sshdir, $uid, $gid, 1, @{$pubkeys1{$login}});
		TBNewsshKeyfile($sshdir, $uid, $gid, 2, @{$pubkeys2{$login}});

		#
		# Give user a .forward back to emulab.
		#
		if (! -e $forward) {
		    system("echo '$email' > $forward");
		
		    chown($uid, $gid, $forward) 
			or warn("*** Could not chown $forward: $!\n");
		
		    chmod(0644, $forward) 
			or warn("*** Could not chmod $forward: $!\n");
		}
	    }
	}
	else {
	    warn("*** Bad accounts line: $info\n");
	}
    }
    # Write the DB back out!
    if (! MFS()) {
	dbmclose(%PWDDB);
    }

    #
    # Create sfs_users file and populate it with public SFS keys
    #
    if ($USESFS) {
	my $sfsusers = "/etc/sfs/sfs_users";
	
	if (!open(SFSKEYS, "> ${sfsusers}.new")) {
	    warn("*** WARNING: Could not open ${sfsusers}.new: $!\n");
	    goto bad;
	}
	    
	print SFSKEYS "#\n";
	print SFSKEYS "# DO NOT EDIT! This file auto generated by ".
	    "Emulab.Net account software.\n";
	print SFSKEYS "#\n";
	print SFSKEYS "# Please use the web interface to edit your ".
	    "SFS public key list.\n";
	print SFSKEYS "#\n";
	foreach my $key (@sfskeys) {
	    print SFSKEYS "$key\n";
	}
	close(SFSKEYS);

	if (!chown(0, 0, "${sfsusers}.new")) {
	    warn("*** WARNING: Could not chown ${sfsusers}.new: $!\n");
	    goto bad;
	}
	if (!chmod(0600, "${sfsusers}.new")) {
	    warn("*** WARNING: Could not chmod ${sfsusers}.new: $!\n");
	    goto bad;
	}
	    
	#
	# If there is an update script, its the new version of SFS.
	# Run that script to convert the keys over. At some point ops
	# and the DB will be converted too, and this can go away.
	#
	if (-x "/usr/local/lib/sfs/upgradedb.pl") {
	    system("/usr/local/lib/sfs/upgradedb.pl ${sfsusers}.new");
	    system("rm -f ${sfsusers}.new.v1-saved-1");
	}

	# Because sfs_users only contains public keys, sfs_users.pub is
	# exactly the same
	if (system("cp -p -f ${sfsusers}.new ${sfsusers}.pub.new")) {
	    warn("*** WARNING Could not copy ${sfsusers}.new to ".
		 "${sfsusers}.pub.new: $!\n");
	    goto bad;
	}
	    
	if (!chmod(0644, "${sfsusers}.pub.new")) {
	    warn("*** WARNING: Could not chmod ${sfsusers}.pub.new: $!\n");
	    goto bad;
	}

	# Save off old key files and move in new ones
	foreach my $keyfile ("${sfsusers}", "${sfsusers}.pub") {
	    if (-e $keyfile) {
		if (system("cp -p -f $keyfile $keyfile.old")) {
		    warn("*** Could not save off $keyfile: $!\n");
		    next;
		}
		if (!chown(0, 0, "$keyfile.old")) {
		    warn("*** Could not chown $keyfile.old: $!\n");
		}
		if (!chmod(0600, "$keyfile.old")) {
		    warn("*** Could not chmod $keyfile.old: $!\n");
		}
	    }
	    if (system("mv -f $keyfile.new $keyfile")) {
		warn("*** Could not mv $keyfile.new $keyfile.new: ~!\n");
	    }
	}
      bad:
    }
    
    return 0;
}

#
# RPM configuration. 
#
sub dorpms ()
{
    my @rpms = ();
    
    if (tmcc(TMCCCMD_RPM, undef, \@rpms) < 0) {
	warn("*** WARNING: Could not get rpms from server!\n");
	return -1;
    }
    return 0
	if (! @rpms);

    if (!open(RPM, ">" . TMRPM)) {
	warn("*** WARNING: Could not open " . TMRPM . ": $!\n");
	return -1;
    }
    print RPM "#!/bin/sh\n";

    #
    # Use tmcc to copy rpms for remote/jailed nodes,
    # otherwise access via NFS.
    #
    # XXX for now we always copy the rpm when using NFS
    # to avoid the stupid changing-exports-file server race
    # (install-tarfile knows how to deal with said race when copying).
    #
    my $installoption = (REMOTE() ? "-t" : "-c");

    foreach my $rpm (@rpms) {
	if ($rpm =~ /RPM=(.+)/) {
	    my $rpmline = sprintf($RPMINSTALL, $installoption, $1);

	    print STDOUT "  $rpmline\n";
	    print RPM    "echo \"Installing RPM $1\"\n";
	    print RPM    "$rpmline\n";
	}
	else {
	    warn "*** WARNING: Bad RPMs line: $rpm";
	}
    }
    close(RPM);
    chmod(0755, TMRPM);
    return 0;
}

#
# TARBALL configuration. 
#
sub dotarballs ()
{
    my @tarballs;

    if (tmcc(TMCCCMD_TARBALL, undef, \@tarballs) < 0) {
	warn("*** WARNING: Could not get tarballs from server!\n");
	return -1;
    }
    return 0
	if (! @tarballs);

    if (!open(TARBALL, ">" . TMTARBALLS)) {
	warn("*** WARNING: Could not open " . TMTARBALLS . ": $!\n");
	return -1;
    }
    print TARBALL "#!/bin/sh\n";

    #
    # Use tmcc to copy tarfiles for remote/jailed nodes,
    # otherwise access via NFS.
    #
    # XXX for now we always copy the tarfile when using NFS
    # to avoid the stupid changing-exports-file server race
    # (install-tarfile knows how to deal with said race when copying).
    #
    my $installoption = (REMOTE() ? "-t" : "-c");

    foreach my $tarball (@tarballs) {
	if ($tarball =~ /DIR=(.+)\s+TARBALL=(.+)/) {
	    my $tbline = sprintf($TARINSTALL, $installoption, $1, $2);
		    
	    print STDOUT  "  $tbline\n";
	    print TARBALL "echo \"Installing Tarball $2 in dir $1 \"\n";
	    print TARBALL "$tbline\n";
	}
	else {
	    warn("*** WARNING: Bad Tarballs line: $tarball\n");
	}
    }
    close(TARBALL);
    chmod(0755, TMTARBALLS);
    return 0;
}

#
# Experiment startup Command.
#
sub dostartupcmd ()
{
    my @tmccresults;

    if (tmcc(TMCCCMD_STARTUP, undef, \@tmccresults) < 0) {
	warn("*** WARNING: Could not get startupcmd from server!\n");
	return -1;
    }
    return 0
	if (! @tmccresults);

    if (!open(RUN, ">" . TMSTARTUPCMD)) {
	warn("*** WARNING: Could not open " . TMSTARTUPCMD . ": $!\n");
	return -1;
    }
    print RUN "$tmccresults[0]";
    close(RUN);
    chmod(0755, TMSTARTUPCMD);
    return 0;
}

#
# Program agents. I would like to implement startup command using
# a program agent at some point ...
#
sub doprogagent ()
{
    my @agents = ();

    if (tmcc(TMCCCMD_PROGRAMS, undef, \@agents) < 0) {
	warn("*** WARNING: Could not get progagent config from server!\n");
	return -1;
    }
    return 0
	if (! @agents);

    #
    # Write the data to the file. The rc script will interpret it.
    # Note that one of the lines (the first) indicates what user to
    # run the agent as. 
    # 
    if (!open(RUN, ">" . TMPROGAGENTS)) {
	warn("*** WARNING: Could not open " . TMPROGAGENTS . ": $!\n");
	return -1;
    }
    foreach my $line (@agents) {
	print RUN "$line";
    }
    close(RUN);
    return 0;
}

sub dotrafficconfig()
{
    my $didopen = 0;
    my $pat;
    my @tmccresults;
    my $boss;
    my $startnse = 0;
    my $nseconfig = "";
    
    #
    # Kinda ugly, but there is too much perl goo included by Socket to put it
    # on the MFS. 
    # 
    if (MFS()) {
	return 1;
    }

    if (tmcc(TMCCCMD_BOSSINFO, undef, \@tmccresults) < 0 || !@tmccresults) {
	warn("*** WARNING: Could not get bossinfo from server!\n");
	return -1;
    }
    ($boss) = split(" ", $tmccresults[0]);

    #
    # XXX hack: workaround for tmcc cmd failure inside TCL
    #     storing the output of a few tmcc commands in
    #     $BOOTDIR files for use by NSE
    #
    if (!REMOTE() && !JAILED()) {
	if (!open(BOSSINFCFG, ">$BOOTDIR/tmcc.bossinfo")) {
	    warn("*** WARNING: Cannot open file $BOOTDIR/tmcc.bossinfo: $!\n");
	    return -1;
	}
	print BOSSINFCFG "$tmccresults[0]";
	close(BOSSINFCFG);
    }
    my ($pid, $eid, $vname) = check_nickname();

    my $cmdline = "$BINDIR/trafgen -s ";
    # Inside a jail, we connect to the local elvind and talk to the
    # master via the proxy.
    if (JAILED()) {
	$cmdline .= "localhost"
    }
    else {
	$cmdline .= "$boss"
    }
    if ($pid) {
	$cmdline .= " -E $pid/$eid";
    }

    #
    # XXX hack: workaround for tmcc cmd failure inside TCL
    #     storing the output of a few tmcc commands in
    #     $BOOTDIR files for use by NSE
    #
    # Also nse stuff is mixed up with traffic config right
    # now because of having FullTcp based traffic generation.
    # Needs to move to a different place
    if (!REMOTE() && !JAILED()) {
	open(TRAFCFG, ">$BOOTDIR/tmcc.trafgens") or
	    die "Cannot open file $BOOTDIR/tmcc.trafgens: $!";    
    }

    if (tmcc(TMCCCMD_TRAFFIC, undef, \@tmccresults) < 0) {
	warn("*** WARNING: Could not get traffic config from server!\n");
	return -1;
    }

    $pat  = q(TRAFGEN=([-\w.]+) MYNAME=([-\w.]+) MYPORT=(\d+) );
    $pat .= q(PEERNAME=([-\w.]+) PEERPORT=(\d+) );
    $pat .= q(PROTO=(\w+) ROLE=(\w+) GENERATOR=(\w+));

    foreach my $str (@tmccresults) {
	if (!REMOTE() && !JAILED()) {
	    print TRAFCFG "$str";
	}
	
	if ($str =~ /$pat/) {
	    #
	    # The following is specific to the modified TG traffic generator:
	    #
	    #  trafgen [-s serverip] [-p serverport] [-l logfile] \
	    #	     [ -N name ] [-P proto] [-R role] [ -E pid/eid ] \
	    #	     [ -S srcip.srcport ] [ -T targetip.targetport ]
	    #
	    # N.B. serverport is not needed right now
	    #
	    my $name = $1;
	    my $ownaddr = inet_ntoa(my $ipaddr = gethostbyname($2));
	    my $ownport = $3;
	    my $peeraddr = inet_ntoa($ipaddr = gethostbyname($4));
	    my $peerport = $5;
	    my $proto = $6;
	    my $role = $7;
	    my $generator = $8;
	    my $target;
	    my $source;

	    # Skip if not specified as a TG generator. At some point
	    # work in Shashi's NSE work.
	    if ($generator ne "TG") {
		$startnse = 1;
		if (! $didopen) {
		    open(RC, ">" . TMTRAFFICCONFIG)
			or die("Could not open " . TMTRAFFICCONFIG . ": $!");
		    print RC "#!/bin/sh\n";
		    $didopen = 1;
		}
		next;
	    }

	    if ($role eq "sink") {
		$target = "$ownaddr.$ownport";
		$source = "$peeraddr.$peerport";
	    }
	    else {
		$target = "$peeraddr.$peerport";
		$source = "$ownaddr.$ownport";
	    }

	    if (! $didopen) {
		open(RC, ">" . TMTRAFFICCONFIG)
		    or die("Could not open " . TMTRAFFICCONFIG . ": $!");
		print RC "#!/bin/sh\n";
		$didopen = 1;
	    }
	    print RC "$cmdline -N $name -S $source -T $target -P $proto ".
		"-R $role >$LOGDIR/${name}-${pid}-${eid}.debug 2>&1 &\n";
	}
	else {
	    warn "*** WARNING: Bad traffic line: $str";
	}
    }
    if (!REMOTE() && !JAILED()) {
	close(TRAFCFG);
    }

    if( $startnse ) {
	print RC "$BINDIR/startnse &\n";
    }

    #
    # XXX hack: workaround for tmcc cmd failure inside TCL
    #     storing the output of a few tmcc commands in
    #     $BOOTDIR files for use by NSE
    #
    if (!REMOTE() && !JAILED()) {
	my @nseconfigs = ();

	if (tmcc(TMCCCMD_NSECONFIGS, undef, \@nseconfigs) < 0) {
	    warn("*** WARNING: Could not get nseconfigs from server!\n");
	}
	if (open(NSECFG, ">$BOOTDIR/tmcc.nseconfigs")) {
	    foreach my $nseconfig (@nseconfigs) {
		print NSECFG $nseconfig;
	    }
	    close(NSECFG);
	}
	else {
	    warn("*** WARNING: Cannot open file $BOOTDIR/tmcc.nseconfigs: $!");
	}
    }
	    
    # XXX hack: need a separate section for starting up NSE when we
    #           support simulated nodes
    if( ! $startnse ) {
	
	if( $nseconfig ) {

	    # start NSE if 'tmcc nseconfigs' is not empty
	    if ( ! $didopen ) {
		open(RC, ">" . TMTRAFFICCONFIG)
		    or die("Could not open " . TMTRAFFICCONFIG . ": $!");
		print RC "#!/bin/sh\n";
		$didopen = 1;	
	    }
	    print RC "$BINDIR/startnse &\n";
	}
    }
    
    if ($didopen) {
	printf RC "%s %s\n", TMCC(), TMCCCMD_READY();
	close(RC);
	chmod(0755, TMTRAFFICCONFIG);
    }
    return 0;
}

sub dotunnels(;$)
{
    my ($rtabid) = @_;
    my @tunnels;
    my $pat;
    my $didserver = 0;

    #
    # Kinda ugly, but there is too much perl goo included by Socket to put it
    # on the MFS. 
    # 
    if (MFS()) {
	return 1;
    }

    if (tmcc(TMCCCMD_TUNNEL, undef, \@tunnels) < 0) {
	warn("*** WARNING: Could not get tunnel config from server!\n");
	return -1;
    }
    return 0
	if (! @tunnels);

    my ($pid, $eid, $vname) = check_nickname();

    if (!open(RC, ">" . TMTUNNELCONFIG)) {
	warn("*** WARNING: Could not open " . TMTUNNELCONFIG . ": $!");
	return -1;
    }
    print RC "#!/bin/sh\n";
    print RC "kldload if_tap\n";

    if (!open(CONF, ">" . TMVTUNDCONFIG)) {
	warn("*** WARNING: Could not open " . TMVTUNDCONFIG . ": $!");
	close(RC);
	unlink(TMTUNNELCONFIG);
	return -1;
    }

    print(CONF
	  "options {\n".
	  "  ifconfig    /sbin/ifconfig;\n".
	  "  route       /sbin/route;\n".
	  "}\n".
	  "\n".
	  "default {\n".
	  "  persist     yes;\n".
	  "  stat        yes;\n".
	  "  keepalive   yes;\n".
	  "  type        ether;\n".
	  "}\n".
	  "\n");
    
    $pat  = q(TUNNEL=([-\w.]+) ISSERVER=(\d) PEERIP=([-\w.]+) );
    $pat .= q(PEERPORT=(\d+) PASSWORD=([-\w.]+) );
    $pat .= q(ENCRYPT=(\d) COMPRESS=(\d) INET=([-\w.]+) );
    $pat .= q(MASK=([-\w.]+) PROTO=([-\w.]+));

    foreach my $tunnel (@tunnels) {
	if ($tunnel =~ /$pat/) {
	    #
	    # The following is specific to vtund!
	    #
	    my $name     = $1;
	    my $isserver = $2;
	    my $peeraddr = $3;
	    my $peerport = $4;
	    my $password = $5;
	    my $encrypt  = ($6 ? "yes" : "no");
	    my $compress = ($7 ? "yes" : "no");
	    my $inetip   = $8;
	    my $mask     = $9;
	    my $proto    = $10;

	    my $cmd = "$VTUND -n -P $peerport -f ". TMVTUNDCONFIG;

	    if ($isserver) {
		if (!$didserver) {
		    print RC
			"$cmd -s >$LOGDIR/vtund-${pid}-${eid}.debug 2>&1 &\n";
		    $didserver = 1;
		}
	    }
	    else {
		print RC "$cmd $name $peeraddr ".
		    " >$LOGDIR/vtun-${pid}-${eid}-${name}.debug 2>&1 &\n";
	    }
	    #
	    # Sheesh, vtund fails if it sees "//" in a path. 
	    #
	    my $config = TMROUTECONFIG;
	    $config =~ s/\/\//\//g;
	    my $rtabopt= "";
	    if (defined($rtabid)) {
		$rtabopt = "    ifconfig \"%% rtabid $rtabid\";\n";
	    }
	    
	    print(CONF
		  "$name {\n".
		  "  password      $password;\n".
		  "  compress      $compress;\n".
		  "  encrypt       $encrypt;\n".
		  "  proto         $proto;\n".
		  "\n".
		  "  up {\n".
		  "    # Connection is Up\n".
		  $rtabopt .
		  "    ifconfig \"%% $inetip netmask $mask\";\n".
		  "    program " . $config . " \"$inetip up\" wait;\n".
		  "  };\n".
		  "  down {\n".
		  "    # Connection is Down\n".
		  "    ifconfig \"%% down\";\n".
		  "    program " . $config . " \"$inetip down\" wait;\n".
		  "  };\n".
		  "}\n\n");
	}
	else {
	    warn "*** WARNING: Bad tunnel line: $tunnel";
	}
    }

    close(CONF);
    close(RC);
    chmod(0755, TMTUNNELCONFIG);
    return 0;
}

#
# All we do is store it away in the file. This makes it avail later.
# 
sub dojailconfig()
{
    my @tmccresults;

    if (tmcc(TMCCCMD_JAILCONFIG, undef, \@tmccresults) < 0) {
	warn("*** WARNING: Could not get jailconfig from server!\n");
	return -1;
    }
    return 0
	if (! @tmccresults);

    if (!open(RC, ">" . TMJAILCONFIG)) {
	warn "*** WARNING: Could not write " . TMJAILCONFIG . "\n";
	return -1;
    }
    foreach my $str (@tmccresults) {
	print RC $str;
    }
    close(RC);
    chmod(0755, TMJAILCONFIG);
    return 0;
}

#
# Get the sync server config. 
# 
sub dosyncserver()
{
    my $syncserver;
    my $startserver;
    my @tmccresults;

    if (tmcc(TMCCCMD_SYNCSERVER, undef, \@tmccresults) < 0) {
	warn("*** WARNING: Could not get syncserver from server!\n");
	return -1;
    }
    return 0
	if (! @tmccresults);

    #
    # There should be just one string. Ignore anything else.
    #
    if ($tmccresults[0] =~ /SYNCSERVER SERVER=\'([-\w\.]*)\' ISSERVER=(\d)/) {
	$syncserver = $1;
	$startserver = $2
    }
    else {
	warn "*** WARNING: Bad syncserver line: $tmccresults[0]";
	return -1;
    }

    #
    # Write a file so the client program knows where the server is.
    #
    if (system("echo '$syncserver' > ". TMSYNCSERVER)) {
	warn "*** WARNING: Could not write " . TMSYNCSERVER . "\n";
	return -1;
    }

    #
    # If we are the sync server, arrange to start it up.
    #
    return 0
	if (! $startserver);

    if (!open(RC, ">" . TMRCSYNCSERVER)) {
	warn "*** WARNING: Could not write " . TMRCSYNCSERVER . "\n";
	return -1;
    }
    print RC "#!/bin/sh\n";
    print RC "$BINDIR/emulab-syncd -d >$LOGDIR/syncserver.debug 2>&1 &\n";

    close(RC);
    chmod(0755, TMRCSYNCSERVER);
    return 0;
}

#
# Get the hashkey
# 
sub dokeyhash()
{
    my $keyhash;
    my @tmccresults;

    if (tmcc(TMCCCMD_KEYHASH, undef, \@tmccresults) < 0) {
	warn("*** WARNING: Could not get keyhash from server!\n");
	return -1;
    }
    return 0
	if (! @tmccresults);

    #
    # There should be just one string. Ignore anything else.
    #
    if ($tmccresults[0] =~ /KEYHASH HASH=\'([\w]*)\'/) {
	$keyhash = $1;
    }
    else {
	warn "*** WARNING: Bad keyhash line: $tmccresults[0]";
	return -1;
    }

    #
    # Write a file so the node knows the key.
    #
    my $oldumask = umask(0227);
    
    if (system("echo '$keyhash' > ". TMKEYHASH)) {
	warn "*** WARNING: Could not write " . TMKEYHASH . "\n";
	umask($oldumask);
	return -1;
    }
    umask($oldumask);
    return 0;
}

#
# Get the nodeid
# 
sub donodeid()
{
    my $nodeid;
    my @tmccresults;

    if (tmcc(TMCCCMD_NODEID, undef, \@tmccresults) < 0) {
	warn("*** WARNING: Could not get nodeid from server!\n");
	return -1;
    }
    return 0
	if (! @tmccresults);
    
    #
    # There should be just one string. Ignore anything else.
    #
    if ($tmccresults[0] =~ /([-\w]*)/) {
	$nodeid = $1;
    }
    else {
	warn "*** WARNING: Bad nodeid line: $tmccresults[0]";
	return -1;
    }
    
    system("echo '$nodeid' > ". TMNODEID);
    return 0;
}

#
# Plab configuration.  Currently sets up sshd and the DNS resolver
# 
sub doplabconfig()
{
    my $plabconfig;
    my @tmccresults;

    if (tmcc(TMCCCMD_PLABCONFIG, undef, \@tmccresults) < 0) {
	warn("*** WARNING: Could not get plabconfig from server!\n");
	return -1;
    }
    return 0
	if (! @tmccresults);
    $plabconfig = $tmccresults[0];

    open(RC, ">" . TMPLABCONFIG)
	or die("Could not open " . TMPLABCONFIG . ": $!");

    if ($plabconfig =~ /SSHDPORT=(\d+)/) {
	my $sshdport = $1;

	print RC "#!/bin/sh\n";

	# Note that it's important to never directly modify the config
	# file unless it's already been recreated due to vserver's
	# immutable-except-delete flag
	print(RC
	      "function setconfigopt()\n".
	      "{\n".
	      "    file=\$1\n".
	      "    opt=\$2\n".
	      "    value=\$3\n".
	      "    if ( ! grep -q \"^\$opt[ \t]*\$value\\\$\" \$file ); then\n".
	      "        sed -e \"s/^\\(\$opt[ \t]*.*\\)/#\\1/\" < \$file".
	      " > \$file.tmp\n".
	      "        mv -f \$file.tmp \$file\n".
	      "        echo \$opt \$value >> \$file;\n".
	      "    fi\n".
	      "}\n\n");

	# Make it look like it's in Emulab domain
	# XXX This shouldn't be hardcoded
	print RC "setconfigopt /etc/resolv.conf domain emulab.net\n";
	print RC "setconfigopt /etc/resolv.conf search emulab.net\n\n";

	# No SSH X11 Forwarding
	print RC "setconfigopt /etc/ssh/sshd_config X11Forwarding no\n";

	# Set SSH port
	print RC "setconfigopt /etc/ssh/sshd_config Port $sshdport\n";

	# Start sshd
	print RC "/etc/init.d/sshd restart\n";
    }
    else {
	warn "*** WARNING: Bad plab line: $plabconfig";
    }

    close(RC);
    chmod(0755, TMPLABCONFIG);

    return 0;
}

#
# Boot Startup code. This is invoked from the setup OS dependent script,
# and this fires up all the stuff above.
#
sub bootsetup()
{
    my $oldpid;

    # Tell libtmcc to forget anything it knows.
    tmccclrconfig();
    
    print STDOUT "Checking Testbed reservation status ... \n";

    #
    # Watch for a change in project membership. This is not supposed to
    # happen, but it turns out that it does when reloading. Its good to
    # check for this anyway just in case. A little tricky though.
    #
    if (-e TMNICKNAME) {
	($oldpid) = check_nickname();
	unlink TMNICKNAME;
    }
    
    #
    # Check allocation. Exit now if not allocated.
    #
    if (! check_status()) {
	print STDOUT "  Free!\n";
	cleanup_node(1);
	return 0;
    }
    #
    # Project Change? 
    #
    if (defined($oldpid) && ($oldpid ne $pid)) {
	print STDOUT "  Old Project: $oldpid\n";
	# This removes the nickname file, so do it again.
	cleanup_node(1);
	check_status();
    }
    else {
	#
	# Cleanup node. Flag indicates to gently clean ...
	# 
	cleanup_node(0);
    }
    print STDOUT "  Allocated! $pid/$eid/$vname\n";

    #
    # Setup SFS hostid.
    #
    if ($USESFS && !MFS()) {
	print STDOUT "Setting up for SFS ... \n";
	dosfshostid();
    }

    #
    # Tell libtmcc to get the full config. Note that this must happen
    # AFTER dosfshostid() right above, since that changes what tmcd
    # is going to tell us.
    #
    tmccgetconfig();
    
    #
    # Mount the project and user directories and symlink SFS "mounted"
    # directories
    #
    print STDOUT "Mounting project and home directories ... \n";
    domounts();

    #
    # Do account stuff.
    # 
    print STDOUT "Checking Testbed group/user configuration ... \n";
    doaccounts();

    if (! MFS()) {
	donodeid();
	
	#
	# Okay, lets find out about interfaces.
	#
	print STDOUT "Checking Testbed interface configuration ... \n";
	doifconfig();

        #
        # Do tunnels
        # 
        print STDOUT "Checking Testbed tunnel configuration ... \n";
        dotunnels();

	#
	# Host names configuration (/etc/hosts). 
	#
	print STDOUT "Checking Testbed hostnames configuration ... \n";
	dohostnames();

	#
	# Init the sync server.
	# 
	print STDOUT "Checking Testbed sync server setup ...\n";
	dosyncserver();
	
	#
	# Get the key
	# 
	print STDOUT "Checking Testbed key ...\n";
	dokeyhash();
	
	#
	# Router Configuration.
	#
	print STDOUT "Checking Testbed routing configuration ... \n";
	dorouterconfig();

	#
	# Traffic generator Configuration.
	#
	print STDOUT "Checking Testbed traffic generation configuration ...\n";
	dotrafficconfig();

	#
	# RPMS
	# 
	print STDOUT "Checking Testbed RPM configuration ... \n";
	dorpms();

	#
	# Tar Balls
	# 
	print STDOUT "Checking Testbed Tarball configuration ... \n";
	dotarballs();

	#
	# Program agents
	# 
	print STDOUT "Checking Testbed program agent configuration ... \n";
	doprogagent();
    }

    #
    # Experiment startup Command.
    #
    print STDOUT "Checking Testbed Experiment Startup Command ... \n";
    dostartupcmd();

    #
    # OS specific stuff
    #
    os_setup();

    return 0;
}

#
# This happens inside a jail. 
#
sub jailsetup()
{
    #
    # Currently, we rely on the outer environment to set our vnodeid
    # into the environment so we can get it! See mkjail.pl.
    #
    my $vid = $ENV{'TMCCVNODEID'};
    
    #
    # Set global vnodeid for tmcc commands. Must be before all the rest!
    #
    libsetup_setvnodeid($vid);
    $injail   = 1;

    #
    # Create a file inside so that libsetup inside the jail knows its
    # inside a jail and what its ID is. 
    #
    system("echo '$vnodeid' > " . TMJAILNAME());
    # Need to unify this with jailname.
    system("echo '$vnodeid' > " . TMNODEID());

    print STDOUT "Checking Testbed reservation status ... \n";
    if (! check_status()) {
	print STDOUT "  Free!\n";
	return 0;
    }
    print STDOUT "  Allocated! $pid/$eid/$vname\n";

    {
	#
	# XXX just generates interface list for routing config
	#
	print STDOUT "Checking Testbed interface configuration ... \n";
	doifconfig();

#	print STDOUT "Mounting project and home directories ... \n";
#	domounts();

	print STDOUT "Checking Testbed jail configuration ...\n";
	dojailconfig();
	
	print STDOUT "Checking Testbed hostnames configuration ... \n";
	dohostnames();

	if (REMOTE()) {
	    # Locally, the password/group files initially comes from
	    # outside the jail. 
	    print STDOUT "Checking Testbed group/user configuration ... \n";
	    doaccounts();
	}

	print STDOUT "Checking Testbed sync server setup ...\n";
	dosyncserver();
	
	print STDOUT "Checking Testbed key ...\n";
	dokeyhash();
	
	print STDOUT "Checking Testbed RPM configuration ... \n";
	dorpms();

	print STDOUT "Checking Testbed Tarball configuration ... \n";
	dotarballs();

	print STDOUT "Checking Testbed routing configuration ... \n";
	dorouterconfig();

	print STDOUT "Checking Testbed traffic generation configuration ...\n";
	dotrafficconfig();

	print STDOUT "Checking Testbed program agent configuration ... \n";
	doprogagent();

	print STDOUT "Checking Testbed Experiment Startup Command ... \n";
	dostartupcmd();
    }

    return $vnodeid;
}

#
# Remote Node virtual node jail setup. This happens outside the jailed
# env.
#
sub vnodejailsetup($)
{
    my ($vid) = @_;

    #
    # Set global vnodeid for tmcc commands.
    #
    libsetup_setvnodeid($vid);

    #
    # This is the directory where the rc files go.
    #
    if (! -e JAILDIR()) {
	die("*** $0:\n".
	    "    No such directory: " . JAILDIR() . "\n");
    }

    # Do not bother if somehow got released.
    if (! check_status()) {
	print "Node is free!\n";
	return undef;
    }

    #
    # Create /local directories for users. 
    #
    if (! -e LOCALROOTFS()) {
	os_mkdir(LOCALROOTFS(), "0755");
    }
    if (-e LOCALROOTFS()) {
	my $piddir = LOCALROOTFS() . "/$pid";
	my $eiddir = LOCALROOTFS() . "/$pid/$eid";
	my $viddir = LOCALROOTFS() . "/$pid/$vid";

	if (! -e $piddir) {
	    mkdir($piddir, 0777) or
		die("*** $0:\n".
		    "    mkdir filed - $piddir: $!\n");
	}
	if (! -e $eiddir) {
	    mkdir($eiddir, 0777) or
		die("*** $0:\n".
		    "    mkdir filed - $eiddir: $!\n");
	}
	if (! -e $viddir) {
	    mkdir($viddir, 0775) or
		die("*** $0:\n".
		    "    mkdir filed - $viddir: $!\n");
	}
	chmod(0777, $piddir);
	chmod(0777, $eiddir);
	chmod(0775, $viddir);
    }

    #
    # Tell libtmcc to get the full config for the jail. At the moment
    # we do not use SFS inside jails, so okay to do this now (usually
    # have to call dosfshostid() first). The full config will be copied
    # to the proper location inside the jail by mkjail.
    #
    tmccgetconfig();
    
    #
    # Get jail config.
    #
    print STDOUT "Checking Testbed jail configuration ...\n";
    dojailconfig();

    return ($pid, $eid, $vname);
}    

#
# This happens inside a Plab vserver.
#
sub plabsetup()
{
    # Tell libtmcc to forget anything it knows.
    tmccclrconfig();
    
    #
    # vnodeid will either be found in BEGIN block or will be passed to
    # vnodeplabsetup, so it doesn't need to be found here
    #

    #
    # Do account stuff.
    #
    {
	print STDOUT "Checking Testbed reservation status ... \n";
	if (! check_status()) {
	    print STDOUT "  Free!\n";
	    return 0;
	}
	print STDOUT "  Allocated! $pid/$eid/$vname\n";

	#
	# Setup SFS hostid.
	#
	if ($USESFS) {
	    print STDOUT "Setting up for SFS ... \n";
	    dosfshostid();
	}

	#
	# Tell libtmcc to get the full config. Note that this must happen
	# AFTER dosfshostid() right above, since that changes what tmcd
	# is going to tell us.
	#
	tmccgetconfig();

#	print STDOUT "Mounting project and home directories ... \n";
#	domounts();

	print STDOUT "Checking Testbed plab configuration ...\n";
	doplabconfig();

	print STDOUT "Checking Testbed hostnames configuration ... \n";
	dohostnames();

	print STDOUT "Checking Testbed key ...\n";
	dokeyhash();
	
	print STDOUT "Checking Testbed group/user configuration ... \n";
	doaccounts();

	print STDOUT "Checking Testbed RPM configuration ... \n";
	dorpms();

	print STDOUT "Checking Testbed Tarball configuration ... \n";
	dotarballs();

# 	print STDOUT "Checking Testbed routing configuration ... \n";
# 	dorouterconfig();

# 	print STDOUT "Checking Testbed traffic generation configuration ...\n";
# 	dotrafficconfig();

# 	print STDOUT "Checking Testbed program agent configuration ... \n";
# 	doprogagent();

	print STDOUT "Checking Testbed Experiment Startup Command ... \n";
	dostartupcmd();
    }

    return $vnodeid;
}

#
# Remote node virtual node Plab setup.  This happens inside the vserver
# environment (because on Plab you can't escape)
#
sub vnodeplabsetup($)
{
    my ($vid) = @_;

    #
    # Set global vnodeid for tmcc commands.
    #
    libsetup_setvnodeid($vid);
    $inplab   = 1;

    # Do not bother if somehow got released.
    if (! check_status()) {
	print "Node is free!\n";
	return undef;
    }
    
    #
    # Create a file so that libsetup knows it's inside Plab and what
    # its ID is. 
    #
    system("echo '$vnodeid' > $BOOTDIR/plabname");
    # Need to unify this with plabname.
    system("echo '$vnodeid' > $BOOTDIR/nodeid");
    
    # XXX Anything else to do?
    
    return ($pid, $eid, $vname);
}

#
# IXP config. This happens on the outside since there is currently no
# inside setup (until there is a reasonable complete environment).
#
sub ixpsetup($)
{
    my ($vid) = @_;

    #
    # Set global vnodeid for tmcc commands.
    #
    libsetup_setvnodeid($vid);

    #
    # Config files go here. 
    #
    if (! -e CONFDIR()) {
	die("*** $0:\n".
	    "    No such directory: " . CONFDIR() . "\n");
    }

    # Do not bother if somehow got released.
    if (! check_status()) {
	print "Node is free!\n";
	return undef;
    }
    $inixp    = 1;

    #
    # Different approach for IXPs. The ixp setup code will call the routines
    # directly. 
    # 

    return ($pid, $eid, $vname);
}

#
# Report startupcmd status back to TMCD. Called by the runstartup
# script. 
#
sub startcmdstatus($)
{
    my($status) = @_;

    return(tmcc(TMCCCMD_STARTSTAT, "$status"));
}

#
# Install deltas is deprecated.
#
sub install_deltas ()
{
    #
    # No longer supported, but be sure to return 0.
    #
    print "*** WARNING: No longer supporting testbed deltas!\n";
    return 0;
}

#
# Early on in the boot, we want to reset the hostname. This gets the
# nickname and returns it. 
#
# This is going to get invoked very early in the boot process, before the
# normal client initialization. So we have to do a few things to make
# things are consistent. 
#
sub whatsmynickname()
{
    #
    # Check allocation. Exit now if not allocated.
    #
    if (! check_status()) {
	return 0;
    }

    return "$vname.$eid.$pid";
}

#
# Put ourselves into the background, directing output to the log file.
# The caller provides the logfile name, which should have been created
# with mktemp, just to be safe. Returns the usual return of fork. 
#
# usage int TBBackGround(char *filename).
# 
sub TBBackGround($)
{
    my($logname) = @_;
    
    my $mypid = fork();
    if ($mypid) {
	return $mypid;
    }
    select(undef, undef, undef, 0.2);
    
    #
    # We have to disconnect from the caller by redirecting both STDIN and
    # STDOUT away from the pipe. Otherwise the caller (the web server) will
    # continue to wait even though the parent has exited. 
    #
    open(STDIN, "< /dev/null") or
	die("opening /dev/null for STDIN: $!");

    # Note different taint check (allow /).
    if ($logname =~ /^([-\@\w.\/]+)$/) {
	$logname = $1;
    } else {
	die("Bad data in logfile name: $logname\n");
    }

    open(STDERR, ">> $logname") or die("opening $logname for STDERR: $!");
    open(STDOUT, ">> $logname") or die("opening $logname for STDOUT: $!");

    return 0;
}

#
# Fork a process to exec a command. Return the pid to wait on.
# 
sub TBForkCmd($) {
    my ($cmd) = @_;
    my($mypid);

    $mypid = fork();
    if ($mypid) {
	return $mypid;
    }

    system($cmd);
    exit($? >> 8);
}

#
# Generate ssh authorized_keys files. Either protocol 1 or 2.
# Returns 0 on success, -1 on failure.
#
sub TBNewsshKeyfile($$$$$)
{
    my ($sshdir, $uid, $gid, $protocol, @pkeys) = @_;
    my $keyfile = "$sshdir/authorized_keys";
	
    if (! -e $sshdir) {
	if (! mkdir($sshdir, 0700)) {
	    warn("*** WARNING: Could not mkdir $sshdir: $!\n");
	    return -1;
	}
	if (!chown($uid, $gid, $sshdir)) {
	    warn("*** WARNING: Could not chown $sshdir: $!\n");
	    return -1;
	}
    }
    if ($protocol == 2) {
	$keyfile .= "2";
    }

    if (!open(AUTHKEYS, "> ${keyfile}.new")) {
	warn("*** WARNING: Could not open ${keyfile}.new: $!\n");
	return -1;
    }
    print AUTHKEYS "#\n";
    print AUTHKEYS "# DO NOT EDIT! This file auto generated by ".
	"Emulab.Net account software.\n";
    print AUTHKEYS "#\n";
    print AUTHKEYS "# Please use the web interface to edit your ".
	"public key list.\n";
    print AUTHKEYS "#\n";
    
    foreach my $key (@pkeys) {
	print AUTHKEYS "$key\n";
    }
    close(AUTHKEYS);

    if (!chown($uid, $gid, "${keyfile}.new")) {
	warn("*** WARNING: Could not chown ${keyfile}.new: $!\n");
	return -1;
    }
    if (!chmod(0600, "${keyfile}.new")) {
	warn("*** WARNING: Could not chmod ${keyfile}.new: $!\n");
	return -1;
    }
    if (-e "${keyfile}") {
	if (system("cp -p -f ${keyfile} ${keyfile}.old")) {
	    warn("*** Could not save off ${keyfile}: $!\n");
	    return -1;
	}
	if (!chown($uid, $gid, "${keyfile}.old")) {
	    warn("*** Could not chown ${keyfile}.old: $!\n");
	}
	if (!chmod(0600, "${keyfile}.old")) {
	    warn("*** Could not chmod ${keyfile}.old: $!\n");
	}
    }
    if (system("mv -f ${keyfile}.new ${keyfile}")) {
	warn("*** Could not mv ${keyfile} to ${keyfile}.new: $!\n");
    }
    return 0;
}

1;
