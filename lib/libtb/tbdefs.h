/*
 * EMULAB-COPYRIGHT
 * Copyright (c) 2000-2004 University of Utah and the Flux Group.
 * All rights reserved.
 */

/*
 * Various constants that are reflected in the DB!
 */
#define	TBDB_FLEN_NODEID	(32 + 1)
#define	TBDB_FLEN_VNAME		(32 + 1)
#define	TBDB_FLEN_EID		(32 + 1)
#define	TBDB_FLEN_UID		(8  + 1)
#define	TBDB_FLEN_PID		(12 + 1)
#define	TBDB_FLEN_GID		(16 + 1)
#define	TBDB_FLEN_NODECLASS	(10 + 1)
#define	TBDB_FLEN_NODETYPE	(30 + 1)
#define	TBDB_FLEN_IP		(16 + 1)
#define TBDB_FLEN_EVOBJTYPE	128
#define TBDB_FLEN_EVOBJNAME	128
#define TBDB_FLEN_EVEVENTTYPE	128
#define TBDB_FLEN_PRIVKEY	64
#define TBDB_FLEN_SFSHOSTID	128

/*
 * Event system stuff.
 *
 * If you add to these two lists, make sure you add to the arrays in tbdefs.c
 */
#define TBDB_OBJECTTYPE_TESTBED	"TBCONTROL"
#define TBDB_OBJECTTYPE_STATE	"TBNODESTATE"
#define TBDB_OBJECTTYPE_OPMODE	"TBNODEOPMODE"
#define TBDB_OBJECTTYPE_LINK	"LINK"
#define TBDB_OBJECTTYPE_TRAFGEN	"TRAFGEN"
#define TBDB_OBJECTTYPE_TIME	"TIME"
#define TBDB_OBJECTTYPE_PROGRAM	"PROGRAM"
#define TBDB_OBJECTTYPE_FRISBEE	"FRISBEE"
#define TBDB_OBJECTTYPE_SIMULATOR "SIMULATOR"
#define TBDB_OBJECTTYPE_LINKTEST "LINKTEST"
#define TBDB_OBJECTTYPE_NSE     "NSE"

#define TBDB_EVENTTYPE_START	"START"
#define TBDB_EVENTTYPE_STOP	"STOP"
#define TBDB_EVENTTYPE_KILL	"KILL"
#define TBDB_EVENTTYPE_ISUP	"ISUP"
#define TBDB_EVENTTYPE_REBOOT	"REBOOT"
#define TBDB_EVENTTYPE_UP	"UP"
#define TBDB_EVENTTYPE_DOWN	"DOWN"
#define TBDB_EVENTTYPE_MODIFY	"MODIFY"
#define TBDB_EVENTTYPE_SET	"SET"
#define TBDB_EVENTTYPE_RESET	"RESET"
#define TBDB_EVENTTYPE_HALT	"HALT"
#define TBDB_EVENTTYPE_SWAPOUT	"SWAPOUT"
#define TBDB_EVENTTYPE_NSESWAP	"NSESWAP"
#define TBDB_EVENTTYPE_NSEEVENT	"NSEEVENT"

#define TBDB_NODESTATE_ISUP       "ISUP"
#define TBDB_NODESTATE_REBOOTED   "REBOOTED"
#define TBDB_NODESTATE_REBOOTING  "REBOOTING"
#define TBDB_NODESTATE_SHUTDOWN   "SHUTDOWN"
#define TBDB_NODESTATE_BOOTING    "BOOTING"
#define TBDB_NODESTATE_TBSETUP    "TBSETUP"
#define TBDB_NODESTATE_RELOADSETUP "RELOADSETUP"
#define TBDB_NODESTATE_RELOADING  "RELOADING"
#define TBDB_NODESTATE_RELOADDONE "RELOADDONE"
#define TBDB_NODESTATE_UNKNOWN    "UNKNOWN"
#define TBDB_NODESTATE_PXEWAIT    "PXEWAIT"
#define TBDB_NODESTATE_PXEWAKEUP  "PXEWAKEUP"
#define TBDB_NODESTATE_PXEBOOTING "PXEBOOTING"

#define TBDB_NODEOPMODE_NORMAL      "NORMAL"
#define TBDB_NODEOPMODE_DELAYING    "DELAYING"
#define TBDB_NODEOPMODE_UNKNOWNOS   "UNKNOWNOS"
#define TBDB_NODEOPMODE_RELOADING   "RELOADING"
#define TBDB_NODEOPMODE_NORMALv1    "NORMALv1" 
#define TBDB_NODEOPMODE_MINIMAL     "MINIMAL" 
#define TBDB_NODEOPMODE_RELOAD      "RELOAD" 
#define TBDB_NODEOPMODE_DELAY       "DELAY" 
#define TBDB_NODEOPMODE_BOOTWHAT    "_BOOTWHAT_"
#define TBDB_NODEOPMODE_UNKNOWN     "UNKNOWN"

#define TBDB_TBCONTROL_RESET        "RESET"
#define TBDB_TBCONTROL_RELOADDONE   "RELOADDONE"
#define TBDB_TBCONTROL_TIMEOUT      "TIMEOUT"

#define TBDB_IFACEROLE_CONTROL		"ctrl"
#define TBDB_IFACEROLE_EXPERIMENT	"expt"
#define TBDB_IFACEROLE_JAIL		"jail"
#define TBDB_IFACEROLE_FAKE		"fake"
#define TBDB_IFACEROLE_GW		"gw"
#define TBDB_IFACEROLE_OTHER		"other"

/*
 * Protos.
 */
int	tbdb_validobjecttype(char *foo);
int	tbdb_valideventtype(char *foo);
