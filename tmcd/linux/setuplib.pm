#!/usr/bin/perl -wT
use English;

#
# Initialize at boot time.
#
my $TMCC	= "/etc/rc.d/testbed/tmcc";
my $TMIFC       = "/etc/rc.d/testbed/rc.ifc";
my $TMRPM       = "/etc/rc.d/testbed/rc.rpm";
my $TMSTARTUPCMD= "/etc/rc.d/testbed/startupcmd";
my $TMGROUP     = "/etc/rc.d/testbed/group";
my $TMPASSWD    = "/etc/rc.d/testbed/passwd";
my $TMSHADOW    = "/etc/rc.d/testbed/shadow";
my $TMGSHADOW   = "/etc/rc.d/testbed/gshadow";
my $TMHOSTS     = "/etc/rc.d/testbed/hosts";
my $TMNICKNAME  = "/etc/rc.d/testbed/nickname";
my $HOSTSFILE   = "/etc/hosts";
my @CONFIGS	= ($TMIFC, $TMRPM, $TMSTARTUPCMD, $TMNICKNAME);
my @LOCKFILES   = ("/etc/group.lock", "/etc/gshadow.lock");
my $REBOOTCMD   = "reboot";
my $STATCMD     = "status";
my $IFCCMD      = "ifconfig";
my $ACCTCMD     = "accounts";
my $DELAYCMD    = "delay";
my $HOSTSCMD    = "hostnames";
my $RPMCMD      = "rpms";
my $STARTUPCMD  = "startupcmd";
my $DELTACMD    = "deltas";
my $IFCONFIG    = "/sbin/ifconfig eth%d inet %s netmask %s\n";
my $RPMINSTALL  = "/bin/rpm -i %s\n";
my $CP		= "/bin/cp -f";
my $USERADD	= "/usr/sbin/useradd";
my $USERMOD	= "/usr/sbin/usermod";
my $GROUPADD	= "/usr/sbin/groupadd";
my $DELTAINSTALL= "/usr/local/bin/install-delta";
my $IFACE	= "eth";
my $CTLIFACENUM = "4";
my $CTLIFACE    = "${IFACE}${CTLIFACENUM}";
my $project     = "";
my $eid         = "";
my $vname       = "";
my $PROJDIR     = "/proj";
my $USERDIR     = "/users";
my $PROJMOUNTCMD= "/bin/mount fs.emulab.net:/q/$PROJDIR/%s $PROJDIR/%s";
my $USERMOUNTCMD= "/bin/mount fs.emulab.net:$USERDIR/%s $USERDIR/%s";
my $HOSTNAME    = "%s\t%s-%s %s\n";

#
# This is a debugging thing for my home network.
# 
my $NODE	= "MYIP=155.99.214.136";
$NODE		= "";

#
# Inform the master we have rebooted.
#
sub inform_reboot()
{
    open(TM, "$TMCC $NODE $REBOOTCMD |")
	or die "Cannot start $TMCC: $!";
    close(TM)
	or die $? ? "$TMCC exited with status $?" : "Error closing pipe: $!";

    return 0;
}

#
# Check node allocation. Returns 0/1 for free/allocated status.
#
sub check_status ()
{
    print STDOUT "Checking Testbed reservation status ... \n";

    open(TM, "$TMCC $NODE $STATCMD |")
	or die "Cannot start $TMCC: $!";
    $_ = <TM>;
    close(TM)
	or die $? ? "$TMCC exited with status $?" : "Error closing pipe: $!";

    if ($_ =~ /^FREE/) {
	print STDOUT "  Free!\n";
	return 0;
    }
    
    if ($_ =~ /ALLOCATED=([-\@\w.]*)\/([-\@\w.]*) NICKNAME=([-\@\w.]*)/) {
	$project = $1;
	$eid     = $2;
	$vname   = $3;
	$nickname= "$vname.$eid.$project";
	print STDOUT "  Allocated! PID: $1, EID: $2, NickName: $nickname\n";
    }
    else {
	die("Error getting reservation status\n");
    }
    return ($project, $eid, $vname);
}

#
# Stick our nickname in a file in case someone wants it.
#
sub create_nicknames()
{
    open(NICK, ">$TMNICKNAME")
	or die("Could not open $TMNICKNAME: $!");
    print NICK "$nickname\n";
    close(NICK);

    return 0;
}

#
# Mount the project directory.
#
sub mount_projdir()
{
    print STDOUT "Mounting the project directory on $PROJDIR/$project ... \n";

    if (! -e "$PROJDIR/$project") {
	if (! mkdir("$PROJDIR/$project", 0770)) {
	    print STDERR "Could not make directory $PROJDIR/$project: $!\n";
	}
    }

    if (system("mount | egrep -q ' $PROJDIR/$project '")) {
	if (system(sprintf($PROJMOUNTCMD, $project, $project)) != 0) {
	    print STDERR
		"Could not mount project directory on $PROJDIR/$project.\n";
	}
    }

    return 0;
}

#
# Do interface configuration.    
# Write a file of ifconfig lines, which will get executed.
#
sub doifconfig ()
{
    print STDOUT "Checking Testbed interface configuration ... \n";

    #
    # Open a connection to the TMCD, and then open a local file into which
    # we write ifconfig commands (as a shell script).
    # 
    open(TM,  "$TMCC $NODE $IFCCMD |")
	or die "Cannot start $TMCC: $!";
    open(IFC, ">$TMIFC")
	or die("Could not open $TMIFC: $!");
    print IFC "#!/bin/sh\n";
    
    while (<TM>) {
	$_ =~ /INTERFACE=(\d*) INET=([0-9.]*) MASK=([0-9.]*)/;
	printf STDOUT "  $IFCONFIG", $1, $2, $3;
	printf IFC $IFCONFIG, $1, $2, $3;
    }
    close(TM);
    close(IFC);
    chmod(0755, "$TMIFC");

    return 0;
}

#
# Host names configuration (/etc/hosts). 
#
sub dohostnames ()
{
    print STDOUT "Checking Testbed /etc/hosts configuration ... \n";

    open(TM,  "$TMCC $NODE $HOSTSCMD |")
	or die "Cannot start $TMCC: $!";
    open(HOSTS, ">>$HOSTSFILE")
	or die("Could not open $HOSTSFILE: $!");

    #
    # Now convert each hostname into hosts file representation and write
    # it to the hosts file.
    # 
    while (<TM>) {
	$_ =~ /NAME=([-\@\w.]+) LINK=([0-9]*) IP=([0-9.]*) ALIAS=([-\@\w.]*)/;
	printf STDOUT "  $1, $2, $3, $4\n";
	printf HOSTS  $HOSTNAME, $3, $1, $2, $4;
    }
    close(TM);
    close(HOSTS);

    return 0;
}

#
# Account stuff. Again, open a connection to the TMCD, and receive
# ADDGROUP and ADDUSER commands. We turn those into "pw" commands.
#
sub doaccounts ()
{
    print STDOUT "Checking Testbed group/user configuration ... \n";

    open(TM, "$TMCC $NODE $ACCTCMD |")
	or die "Cannot start $TMCC: $!";

    while (<TM>) {
	if ($_ =~ /^ADDGROUP NAME=([-\@\w.]+) GID=([0-9]+)/) {
	    print STDOUT "  Group: $1/$2\n";

	    $group = $1;
	    $gid   = $2;

	    ($exists) = getgrgid($gid);
	    if ($exists) {
		next;
	    }
	
	    if (system("$GROUPADD -g $gid $group") != 0) {
		print STDERR "Error adding new group $1/$2: $!\n";
	    }
	    next;
	}
	if ($_ =~
	    /^ADDUSER LOGIN=([0-9a-z]+) PSWD=([^:]+) UID=(\d+) GID=(\d+) ROOT=(\d) NAME="(.*)"/)
	{
	    $login = $1;
	    $pswd  = $2;
	    $uid   = $3;
	    $gid   = $4;
	    $root  = $5;
	    $name  = $6;
	    if ( $name =~ /^(([^:]+$|^))$/ ) {
		$name = $1;
	    }
	    print STDOUT "  User: $login/$uid/$gid/$root/$name\n";
	
	    if (! -e "$USERDIR/$login") {
		if (! mkdir("$USERDIR/$login", 0770)) {
		    print STDERR "Could not mkdir $USERDIR/$login: $!\n";
		    next;
		}
	    }
	
	    if (system("mount | egrep -q ' $USERDIR/$login '")) {
		if (system(sprintf($USERMOUNTCMD, $login, $login)) != 0) {
		    print STDERR
			"Could not mount $USERDIR/$login.\n";
		    next;
		}
	    }

	    ($exists) = getpwuid($uid);
	    if ($exists) {
		if ($root) {
		    $GLIST = "-G root,$gid";
		}
		else {
		    $GLIST = "-G $gid";
		}
		system("$USERMOD $GLIST $login");
		next;
	    }
	
	    $GLIST = " ";
	    if ($root) {
		$GLIST = "-G root ";
	    }
	
	    if (system("$USERADD -M -u $uid -g $gid -p $pswd $GLIST ".
		       "-d $USERDIR/$login -s /bin/tcsh -c \"$name\" $login")
		!= 0) {
		print STDERR "Error adding new user $login\n";
		next;
	    }
	    next;
	}
    }
    close(TM);

    return 0;
}

#
# RPM configuration. Done after account stuff!
#
sub dorpms ()
{
    print STDOUT "Checking Testbed RPM configuration ... \n";

    open(TM,  "$TMCC $NODE $RPMCMD |")
	or die "Cannot start $TMCC: $!";
    open(RPM, ">$TMRPM")
	or die("Could not open $TMRPM: $!");
    print RPM "#!/bin/sh\n";
    
    while (<TM>) {
	if ($_ =~ /RPM=([-\@\w.\/]+)/) {
	    printf STDOUT "  $RPMINSTALL", $1;
	    printf RPM    "echo \"Installing RPM $1\"\n", $1;
	    printf RPM    "$RPMINSTALL", $1;
	}
    }
    close(TM);
    close(RPM);
    chmod(0755, "$TMRPM");

    return 0;
}

#
# Experiment startup Command.
#
sub dostartupcmd ()
{
    print STDOUT "Checking Testbed Experiment Startup Command ... \n";

    open(TM,  "$TMCC $NODE $STARTUPCMD |")
	or die "Cannot start $TMCC: $!";
    open(RUN, ">$TMSTARTUPCMD")
	or die("Could not open $TMSTARTUPCMD: $!");
    
    while (<TM>) {
	if ($_ =~ /CMD=(\'[-\@\w.\/ ]+\') UID=([0-9a-z]+)/) {
	    print  STDOUT "  Will run $1 as $2\n";
	    print  RUN    "$_\n";
	}
    }
    close(TM);
    close(RUN);
    chmod(0755, "$TMSTARTUPCMD");

    return 0;
}

#
# Install deltas. Return 0 if nothing happened. Return -1 if there was
# an error. Return 1 if deltas installed, which tells the caller to reboot.
#
sub install_deltas ()
{
    my @deltas = ();
    my $reboot = 0;
    
    print STDOUT "Checking Testbed Delta configuration ... \n";

    open(TM,  "$TMCC $DELTACMD |")
	or die "Cannot start $TMCC: $!";
    while (<TM>) {
	push(@deltas, $_);
    }
    close(TM);

    #
    # No deltas. Just exit and let the boot continue.
    #
    if (! @deltas) {
	return 0;
    }

    #
    # Install all the deltas, and hope they all install okay. We reboot
    # if any one does an actual install (they may already be installed).
    # If any fails, then give up.
    # 
    foreach $delta (@deltas) {
	if ($delta =~ /DELTA=([-\@\w.\/]+)/) {
	    print STDOUT  "Installing DELTA $1 ...\n";

	    system("$DELTAINSTALL $1");
	    my $status = $? >> 8;
	    if ($status == 0) {
		$reboot = 1;
	    }
	    else {
		if ($status < 0) {
		    print STDOUT "Failed to install DELTA $1. Help!\n";
		    return -1;
		}
	    }
	}
    }
    return $reboot;
}

#
# If node is free, reset to a moderately clean state.
#
sub cleanup_node () {
    print STDOUT "Cleaning node; removing configuration files ...\n";
    unlink @CONFIGS;
    unlink @LOCKFILES;

    printf STDOUT "Resetting /etc/hosts file\n";
    if (system("$CP -f $TMHOSTS $HOSTSFILE") != 0) {
	print STDERR "Could not copy default /etc/hosts file into place: $!\n";
	exit(1);
    }    

    printf STDOUT "Resetting passwd and group files\n";
    if (system("$CP -f $TMGROUP $TMPASSWD /etc") != 0) {
	print STDERR "Could not copy default group file into place: $!\n";
	exit(1);
    }
    
    if (system("$CP -f $TMSHADOW $TMGSHADOW /etc") != 0) {
	print STDERR "Could not copy default passwd file into place: $!\n";
	exit(1);
    }
}

1;
