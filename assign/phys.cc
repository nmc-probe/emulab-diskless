#include <stdlib.h>
#include <string.h>
#include "phys.h"

toponode::toponode() {
	ints = count = used = 0;
}

tbswitch::tbswitch() {
	nodes = NULL;
	nodecount = 0;
}

tbswitch::tbswitch(int ncount, char *newname) {
	nodecount = ncount;
	nodes = new toponode[ncount];
	name = strdup(newname);
}

void tbswitch::setsize(int ncount) {
	if (nodes) { delete(nodes); }
	nodecount = ncount;
	nodes = new toponode[ncount];
}

tbswitch::~tbswitch() {
	if (nodes) { delete(nodes); }
	nodecount = 0;
}

topology::topology(int nswitches) {
	switchcount = nswitches;
	switches = new tbswitch *[switchcount];
}

topology::~topology() {
	if (switches) delete(switches);
}
