/*
 * EMULAB-COPYRIGHT
 * Copyright (c) 2004, 2005 University of Utah and the Flux Group.
 * All rights reserved.
 */

#ifndef _event_sched_rpc_h
#define _event_sched_rpc_h

#include "event-sched.h"

#ifdef __cplusplus

#include <ulxmlrpcpp.h>  // always first header
#include <iostream>
#include <ulxr_tcpip_connection.h>  // first, don't move: msvc #include bug
#include <ulxr_ssl_connection.h> 
#include <ulxr_http_protocol.h> 
#include <ulxr_requester.h>
#include <ulxr_value.h>
#include <ulxr_except.h>
#include <emulab_proxy.h>

int RPC_invoke(char *method,
	       emulab::EmulabResponse *er_out,
	       emulab::spa_attr_t tag,
	       ...);

struct rpc_conn_proto {
	ulxr::Connection *conn;
	ulxr::Protocol *proto;
};

struct r_rpc_data {
	const char *certpath;
	const char *host;
	unsigned short port;
	int refcount;
	struct rpc_conn_proto conn_proto;
	pthread_mutex_t mutex;
};

extern struct r_rpc_data rpc_data;

#endif

#define DEFAULT_RPC_PORT 3069

#define ROBOT_TIMEOUT 10 * 60 /* seconds */

#ifdef __cplusplus
extern "C" {
#endif

int RPC_init(const char *certpath, const char *host, unsigned short port);
int RPC_grab(void);
void RPC_drop(void);

int RPC_exppath(char *pid, char *eid, char *path_out, size_t path_size);
int RPC_waitforrobots(char *pid, char *eid);
int RPC_waitforactive(char *pid, char *eid);
int RPC_agentlist(event_handle_t handle, char *pid, char *eid);
int RPC_grouplist(event_handle_t handle, char *pid, char *eid);
int RPC_eventlist(char *pid, char *eid,
		  event_handle_t handle, address_tuple_t tuple,
		  long basetime);

extern int AddAgent(event_handle_t handle,
		    char *vname, char *vnode, char *nodeid,
		    char *ipaddr, char *type);

extern int AddGroup(event_handle_t handle, char *groupname, char *agentname);

extern int AddEvent(event_handle_t handle, address_tuple_t tuple,
		    long basetime,
		    char *exidx, char *ftime, char *objname, char *exargs,
		    char *objtype, char *evttype, char *parent);

#ifdef __cplusplus
}
#endif

#endif
