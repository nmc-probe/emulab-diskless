
/* emc sets up an interface for accepting commands over an internet socket;
 * listens on a well-known port for instances of vmc and rmc;
 * and maintains several key data structures.
 */

/* so emc reads config data, sets up data structures, listens on main port
 * every connecting client is configured or rejected, then is placed in
 * a working set to select() on.  Then commands are processed from either a 
 * file, or from an active user interface; requests/data are processed from
 * RMC and VMC, and appropriate responses are generated if necessary (whether
 * the response be to the requesting client, or to somebody else (i.e., if 
 * VMC pushes a position update, EMC does not need to respond to VMC -- but it
 * might need to push data to RMC!)
 */

// so we need to store a bunch of data for data from RMC, VMC, and initial
// all this data can reuse the same data structure, which includes a list of
//   robot id
//   position
//   orientation
//   status

// for the initial configuration of rmc, we need to send a list of id -> 
// hostname pairs (later we will also need to send a bounding box, but not
// until the vision system is a long ways along).

// for the initial configuration of vmc, we need to send a list of robot ids,
// nothing more (as the vision system starts picking up robots, it will query
// emc with known positions/orientations to get matching ids)

// so the emc config file is a bunch of lines with id number, hostname, initial
// position (x, y, and r floating point vals).  We read this file into the 
// initial position data structure, as well as a list of id to hostname
// mappings.

#include "config.h"

#include <stdio.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <unistd.h>
#include <stdlib.h>
#include <signal.h>
#include <paths.h>
#include <netinet/in.h>
#include <errno.h>
#include <string.h>
#include <sys/socket.h>

#include "log.h"
#include "tbdefs.h"
#include "event.h"
#include <elvin/elvin.h>

#include "robot_list.h"
#include "mtp.h"

#include "emcd.h"

static event_handle_t handle;

static char *pideid;

static char *progname;

static int debug;

static int port = EMC_SERVER_PORT;
static int command_seq_no = 0;

/* initial data pulled from the config file */
/* it's somewhat non-intuitive to use lists here -- since we're doing maps,
 * we could just as well use hashtables -- but speed is not a big deal here,
 * and linked lists might be easier for everybody to understand
 */
/* the robot_id/hostname pairings as given in the config file */
struct robot_list *hostname_list = NULL;
/* the robot_id/initial positions as given in the config file */
struct robot_list *initial_position_list = NULL;
/* a list of positions read in from the movement file */
struct robot_list *position_queue = NULL;

static elvin_error_t elvin_error;

static struct rmc_client rmc_data = { -1 };
static struct vmc_client vmc_data = { -1 };
static int emulab_sock = -1;

static void ev_callback(event_handle_t handle,
			event_notification_t notification,
			void *data);

static int acceptor_callback(elvin_io_handler_t handler,
			     int fd,
			     void *rock,
			     elvin_error_t eerror);

static int unknown_client_callback(elvin_io_handler_t handler,
				   int fd,
				   void *rock,
				   elvin_error_t eerror);

static int rmc_callback(elvin_io_handler_t handler,
			int fd,
			void *rock,
			elvin_error_t eerror);

static int emulab_callback(elvin_io_handler_t handler,
			   int fd,
			   void *rock,
			   elvin_error_t eerror);

static int vmc_callback(elvin_io_handler_t handler,
			int fd,
			void *rock,
			elvin_error_t eerror);

static void parse_config_file(char *filename);
static void parse_movement_file(char *filename);

#if defined(SIGINFO)
/* SIGINFO-related stuff */

/**
 * Variable used to tell the main loop that we received a SIGINFO.
 */
static int got_siginfo = 0;

/**
 * Handler for SIGINFO that sets the got_siginfo variable and breaks the main
 * loop so we can really handle the signal.
 *
 * @param sig The actual signal number received.
 */
static void siginfo(int sig)
{
  got_siginfo = 1;
#if 0
  if (handle->do_loop)
    event_stop_main(handle);
#endif
}

static void dump_info(void)
{
}
#endif

static void usage(char *progname)
{
  fprintf(stderr,
	  "Usage: emcd\n");
}

int main(int argc, char *argv[])
{
  int c, on_off = 1, emc_sock, retval = EXIT_SUCCESS;
  char pid[MAXHOSTNAMELEN], eid[MAXHOSTNAMELEN];
  char *server = "localhost";
  char *movement_file = NULL;
  char *config_file = NULL;
  struct sockaddr_in sin;
  elvin_io_handler_t eih;
  address_tuple_t tuple;
  char *keyfile = NULL;
  char *logfile = NULL;
  char *pidfile = NULL;
  char *eport = NULL;
  char buf[BUFSIZ];
  char *idx;
  FILE *fp;
  
  progname = argv[0];
  
  while ((c = getopt(argc, argv, "hde:k:p:c:f:s:P:l:i:")) != -1) {
    switch (c) {
    case 'h':
      break;
    case 'd':
      debug += 1;
      break;
    case 'e':
      pideid = optarg;
      if ((idx = strchr(pideid, '/')) == NULL) {
	fprintf(stderr,
		"error: malformed pid/eid argument - %s\n",
		pideid);
	usage(progname);
      }
      else if ((idx - pideid) >= sizeof(pid)) {
	fprintf(stderr,
		"error: pid is too long - %s\n",
		pideid);
	usage(progname);
      }
      else if (strlen(idx + 1) >= sizeof(eid)) {
	fprintf(stderr,
		"error: eid is too long - %s\n",
		pideid);
	usage(progname);
      }
      else {
	strncpy(pid, pideid, (idx - pideid));
	pid[idx - pideid] = '\0';
	strcpy(eid, idx + 1);
      }
      break;
    case 'k':
      keyfile = optarg;
      break;
    case 'p':
      if (sscanf(optarg, "%d", &port) != 1) {
	fprintf(stderr, "error: illegible port: %s\n", optarg);
	exit(1);
      }
      break;
    case 'c':
      config_file = optarg;
      break;
    case 'f':
      movement_file = optarg;
      break;
    case 's':
      server = optarg;
      break;
    case 'P':
      eport = optarg;
      break;
    case 'l':
      logfile = optarg;
      break;
    case 'i':
      pidfile = optarg;
      break;
    default:
      break;
    }
  }
  
  argc -= optind;
  argv += optind;
  
  // initialize the global lists
  hostname_list = robot_list_create();
  initial_position_list = robot_list_create();
  position_queue = robot_list_create();
  
  /* read config file into the above lists*/
  parse_config_file(config_file);
  /* read movement file into the position queue */
  parse_movement_file(movement_file);

  if (debug) 
    loginit(0, logfile);
  else {
    /* Become a daemon */
    daemon(0, 0);
    
    if (logfile)
      loginit(0, logfile);
    else
      loginit(1, "emcd");
  }
  
  if (pidfile)
    strcpy(buf, pidfile);
  else
    sprintf(buf, "%s/progagent.pid", _PATH_VARRUN);
  fp = fopen(buf, "w");
  if (fp != NULL) {
    fprintf(fp, "%d\n", getpid());
    (void) fclose(fp);
  }
  
  // first, EMC server sock:
  emc_sock = socket(AF_INET,SOCK_STREAM,0);
  if (emc_sock == -1) {
    fprintf(stdout,"Could not open socket for EMC: %s\n",strerror(errno));
    exit(1);
  }

  memset(&sin, 0, sizeof(sin));
  sin.sin_len = sizeof(sin);
  sin.sin_family = AF_INET;
  sin.sin_port = htons(port);
  sin.sin_addr.s_addr = INADDR_ANY;

  setsockopt(emc_sock, SOL_SOCKET, SO_REUSEADDR, &on_off, sizeof(on_off));
  
  if (bind(emc_sock, (struct sockaddr *)&sin, sizeof(sin)) == -1) {
    perror("bind");
    exit(1);
  }
  
  if (listen(emc_sock, 5) == -1) {
    perror("listen");
    exit(1);
  }
  
#if defined(SIGINFO)
  signal(SIGINFO, siginfo);
#endif
  
  /*
   * Convert server/port to elvin thing.
   *
   * XXX This elvin string stuff should be moved down a layer. 
   */
  if (server) {
    snprintf(buf, sizeof(buf), "elvin://%s%s%s",
	     server,
	     (eport ? ":"  : ""),
	     (eport ? eport : ""));
    server = buf;
  }
  
  /*
   * Register with the event system. 
   */
  handle = event_register_withkeyfile(server, 0, keyfile);
  if (handle == NULL) {
    fatal("could not register with event system");
  }

  if (pideid != NULL) {
    /*
     * Construct an address tuple for subscribing to events for this node.
     */
    tuple = address_tuple_alloc();
    if (tuple == NULL) {
      fatal("could not allocate an address tuple");
    }
    
    /*
     * Ask for just the SETDEST event for NODEs we care about. 
     */
    tuple->expt      = pideid;
    tuple->objtype   = TBDB_OBJECTTYPE_NODE;
    tuple->eventtype = TBDB_EVENTTYPE_SETDEST;
    
    if (!event_subscribe(handle, ev_callback, tuple, NULL)) {
      fatal("could not subscribe to event");
    }
    
    if ((elvin_error = elvin_error_alloc()) == NULL) {
      fatal("could not allocate elvin error");
    }
  }
  
  if ((eih = elvin_sync_add_io_handler(NULL,
				       emc_sock,
				       ELVIN_READ_MASK,
				       acceptor_callback,
				       NULL,
				       elvin_error)) == NULL) {
    fatal("could not register I/O callback");
  }
  
  event_main(handle);
  
  return retval;
}

void parse_config_file(char *config_file) {
  char line[BUFSIZ];
  int line_no = 0;
  FILE *fp;

  if (config_file == NULL) {
    return;
  }

  fp = fopen(config_file,"r");
  if (fp == NULL) {
    fprintf(stderr,"Could not open config file!\n");
    exit(-1);
  }

  // read line by line
  while (fgets(line, sizeof(line), fp) != NULL) {
    int id;
    char *hostname;
    float init_x;
    float init_y;
    float init_theta;

    // parse the line
    // sorry, i ain't using regex.h to do this simple crap
    // lines look like '5 garcia1.flux.utah.edu 6.5 4.234582 0.38'
    // the regex would be '\s*\S+\s+\S+\s+\S+\s+\S+\s+\S+'
    // so basically, 5 non-whitespace groups separated by whitespace (tabs or
    // spaces)

    // we use strtok_r because i'm complete
    char *token = NULL;
    char *delim = " \t";
    char *state = NULL;
    struct robot_config *rc;
    struct position *p;
    
    ++line_no;

    token = strtok_r(line,delim,&state);
    if (token == NULL) {
      fprintf(stdout,"Syntax error in config file, line %d.\n",line_no);
      continue;
    }
    id = atoi(token);

    token = strtok_r(NULL,delim,&state);
    if (token == NULL) {
      fprintf(stdout,"Syntax error in config file, line %d.\n",line_no);
      continue;
    }
    hostname = strdup(token);

    token = strtok_r(NULL,delim,&state);
    if (token == NULL) {
      fprintf(stdout,"Syntax error in config file, line %d.\n",line_no);
      continue;
    }
    init_x = (float)atof(token);
	  
    token = strtok_r(NULL,delim,&state);
    if (token == NULL) {
      fprintf(stdout,"Syntax error in config file, line %d.\n",line_no);
      continue;
    }
    init_y = (float)atof(token);

    token = strtok_r(NULL,delim,&state);
    if (token == NULL) {
      fprintf(stdout,"Syntax error in config file, line %d.\n",line_no);
      continue;
    }
    init_theta = (float)atof(token);

	
    // now we save this data to the lists:
    rc = (struct robot_config *)malloc(sizeof(struct robot_config));
    rc->id = id;
    rc->hostname = hostname;
    robot_list_append(hostname_list,id,(void*)rc);
    p = (struct position *)malloc(sizeof(struct position *));
    p->x = init_x;
    p->y = init_y;
    p->theta = init_theta;
    robot_list_append(initial_position_list,id,(void*)p);

    // next line!
  }

}

void parse_movement_file(char *movement_file) {
  char line[BUFSIZ];
  int line_no = 0;
  FILE *fp;

  if (movement_file == NULL) {
    return;
  }

  fp = fopen(movement_file,"r");
  if (fp == NULL) {
    fprintf(stderr,"Could not open movement file!\n");
    exit(-1);
  }

  // read line by line
  while (fgets(line, sizeof(line), fp) != NULL) {
    int id;
    float init_x;
    float init_y;
    float init_theta;

    // parse the line
    // sorry, i ain't using regex.h to do this simple crap
    // lines look like '5  6.5 4.234582 0.38'
    // the regex would be '\s*\S+\s+\S+\s+\S+\s+\S+'
    // so basically, 4 non-whitespace groups separated by whitespace (tabs or
    // spaces)

    // we use strtok_r because i'm complete
    char *token = NULL;
    char *delim = " \t";
    char *state = NULL;
    struct position *p;
    
    ++line_no;

    token = strtok_r(line,delim,&state);
    if (token == NULL) {
      fprintf(stdout,"Syntax error in movement file, line %d.\n",line_no);
      continue;
    }
    id = atoi(token);

    token = strtok_r(NULL,delim,&state);
    if (token == NULL) {
      fprintf(stdout,"Syntax error in movement file, line %d.\n",line_no);
      continue;
    }
    init_x = (float)atof(token);
	  
    token = strtok_r(NULL,delim,&state);
    if (token == NULL) {
      fprintf(stdout,"Syntax error in movement file, line %d.\n",line_no);
      continue;
    }
    init_y = (float)atof(token);

    token = strtok_r(NULL,delim,&state);
    if (token == NULL) {
      fprintf(stdout,"Syntax error in movement file, line %d.\n",line_no);
      continue;
    }
    init_theta = (float)atof(token);


    // now we save this data to the lists:
    p = (struct position *)malloc(sizeof(struct position *));
    p->x = init_x;
    p->y = init_y;
    p->theta = init_theta;
    robot_list_append(position_queue,id,(void*)p);

    // next line!
  }

}

void ev_callback(event_handle_t handle,
		 event_notification_t notification,
		 void *data)
{
  printf("event callback\n");
}

int acceptor_callback(elvin_io_handler_t handler,
		      int fd,
		      void *rock,
		      elvin_error_t eerror)
{
  // we have a new client -- but who?
  int addrlen = sizeof(struct sockaddr_in);
  struct sockaddr_in client_sin;
  int client_sock;

  if ((client_sock = accept(fd,
			    (struct sockaddr *)(&client_sin),
			    &addrlen)) == -1) {
    perror("accept");
  }
  else if (elvin_sync_add_io_handler(NULL,
				     client_sock,
				     ELVIN_READ_MASK,
				     unknown_client_callback,
				     NULL,
				     eerror) == NULL) {
    error("could not add handler for %d\n", client_sock);
    
    close(client_sock);
    client_sock = -1;
  }
  
  return 1;
}

int unknown_client_callback(elvin_io_handler_t handler,
			    int fd,
			    void *rock,
			    elvin_error_t eerror)
{
  mtp_packet_t *mp = NULL;
  int rc, retval = 0;

  elvin_sync_remove_io_handler(handler, eerror);
  
  if (((rc = mtp_receive_packet(fd, &mp)) != MTP_PP_SUCCESS) ||
      (mp->version != MTP_VERSION) ||
      (mp->opcode != MTP_CONTROL_INIT)) {
    error("invalid client %p\n", mp);
  }
  else {
    switch (mp->role) {
    case MTP_ROLE_RMC:
      if (rmc_data.sock_fd != -1) {
	error("rmc client is already connected\n");
      }
      else {
	// write back an RMC_CONFIG packet
	struct mtp_config_rmc r;
	
	r.box.horizontal = 12*12*2.54/100.0;
	r.box.vertical = 8*12*2.54/100.0;
	
	r.num_robots = hostname_list->item_count;
	r.robots = (struct robot_config *)
	  malloc(sizeof(struct robot_config)*(r.num_robots));
	if (r.robots == NULL) {
	  struct mtp_packet *wb;
	  struct mtp_control c;
	  
	  c.id = -1;
	  c.code = -1;
	  c.msg = "internal server error";
	  wb = mtp_make_packet(MTP_CONTROL_ERROR, MTP_ROLE_EMC, &c);
	  mtp_send_packet(fd, wb);
	  
	  free(wb);
	  wb = NULL;
	}
	else {
	  struct robot_config *rc = NULL;
	  struct robot_list_enum *e;
	  struct mtp_packet *wb;
	  int i = 0;

	  e = robot_list_enum(hostname_list);
	  while ((rc = (struct robot_config *)
		  robot_list_enum_next_element(e)) != NULL) {
	    r.robots[i] = *rc;
	    i += 1;
	  }
	  
	  // write back an rmc_config packet
	  if ((wb = mtp_make_packet(MTP_CONFIG_RMC,
				    MTP_ROLE_EMC,
				    &r)) == NULL) {
	    error("unable to allocate rmc_config packet");
	  }
	  else if ((retval= mtp_send_packet(fd, wb)) != MTP_PP_SUCCESS) {
	    error("unable to send rmc_config packet");
	  }
	  else if (elvin_sync_add_io_handler(NULL,
					     fd,
					     ELVIN_READ_MASK,
					     rmc_callback,
					     &rmc_data,
					     eerror) == NULL) {
	    error("unable to add rmc_callback handler");
	  }
	  else {
	    // add descriptor to list, etc:
	    rmc_data.sock_fd = fd;
	    rmc_data.position_list = robot_list_create();

	    retval = 1;
	  }

	  free(wb);
	  wb = NULL;
	}
      }
      break;
    case MTP_ROLE_EMULAB:
      if (emulab_sock != -1) {
	error("emulab client is already connected\n");
      }
      else if (elvin_sync_add_io_handler(NULL,
					 fd,
					 ELVIN_READ_MASK,
					 emulab_callback,
					 NULL,
					 eerror) == NULL) {
	emulab_sock = fd;
	retval = 1;
      }
      break;
    case MTP_ROLE_VMC:
      if (vmc_data.sock_fd != -1) {
	error("vmc client is already connected\n");
      }
      else if (elvin_sync_add_io_handler(NULL,
					 fd,
					 ELVIN_READ_MASK,
					 vmc_callback,
					 &vmc_data,
					 eerror) == NULL) {
	vmc_data.sock_fd = fd;
	vmc_data.position_list = robot_list_create();
	retval = 1;
      }
      break;
    default:
      error("unknown role %d\n", mp->role);
      break;
    }
  }

  mtp_free_packet(mp);
  mp = NULL;
  
  if (!retval) {
    info("dropping bad connection %d\n", fd);
    
    close(fd);
    fd = -1;
  }
  
  return retval;
}

int rmc_callback(elvin_io_handler_t handler,
		 int fd,
		 void *rock,
		 elvin_error_t eerror)
{
  struct rmc_client *rmc = rock;
  mtp_packet_t *mp = NULL;
  int rc, retval = 0;

  if (((rc = mtp_receive_packet(fd, &mp)) != MTP_PP_SUCCESS) ||
      (mp->version != MTP_VERSION)) {
    error("invalid client %p\n", mp);
  }
  else {
    switch (mp->opcode) {
    case MTP_REQUEST_POSITION:
      {
	// find the latest position data for the robot:
	//   supply init pos if no positions in rmc_data or vmc_data;
	//   otherwise, take the position with the most recent timestamp
	//     from rmc_data or vmc_data
	int my_id = mp->data.request_position->robot_id;
	struct mtp_update_position *vmc_up;
	struct mtp_update_position *rmc_up;
	struct mtp_update_position up_copy;
	struct mtp_packet *wb;
	
	vmc_up = (struct mtp_update_position *)
	  robot_list_search(vmc_data.position_list, my_id);
	rmc_up = (struct mtp_update_position *)
	  robot_list_search(rmc_data.position_list, my_id);
	
	if (rmc_up != NULL) {
	  // since VMC isn't hooked in, we simply write back the rmc posit
	  up_copy = *rmc_up;
	  // the status field has no meaning when this packet is being sent
	  up_copy.status = -1;
	  // construct the packet
	  wb = mtp_make_packet(MTP_UPDATE_POSITION, MTP_ROLE_EMC, &up_copy);
	  mtp_send_packet(fd, wb);
	}
	else {
	  struct mtp_control mc;

	  mc.id = -1;
	  mc.code = -1;
	  mc.msg = "position not updated yet";
	  wb = mtp_make_packet(MTP_CONTROL_ERROR, MTP_ROLE_EMC, &mc);
	  mtp_send_packet(fd, wb);
	}
	
	free(wb);
	wb = NULL;
	
	retval = 1;
      }
      break;

    case MTP_UPDATE_POSITION:
      {
	// store the latest data in teh robot position/status list
	// in rmc_data
	int my_id = mp->data.update_position->robot_id;
	struct mtp_update_position *up = (struct mtp_update_position *)
	  robot_list_remove_by_id(rmc->position_list, my_id);
	struct mtp_update_position *up_copy;

	free(up);
	up = NULL;

	info("theta %f\n", mp->data.update_position->position.theta);
	
	up_copy = (struct mtp_update_position *)
	  malloc(sizeof(struct mtp_update_position));
	*up_copy = *(mp->data.update_position);
	robot_list_append(rmc->position_list, my_id, up_copy);
	
	// also, if status is MTP_POSITION_STATUS_COMPLETE || 
	// MTP_POSITION_STATUS_ERROR, notify emulab, or issue the next
	// command to rmc from the movement list.

	// later ....

	retval = 1;
      }
      break;
      
    default:
      {
	struct mtp_packet *wb;
	struct mtp_control c;
	
	error("received bogus opcode from RMC: %d\n", mp->opcode);
	
	c.id = -1;
	c.code = -1;
	c.msg = "protocol error: bad opcode";
	wb = mtp_make_packet(MTP_CONTROL_ERROR, MTP_ROLE_EMC, &c);
	mtp_send_packet(rmc->sock_fd,wb);

	free(wb);
	wb = NULL;
      }
      break;
    }
  }

  mtp_free_packet(mp);
  mp = NULL;

  if (!retval) {
    info("dropping connection %d\n", fd);

    elvin_sync_remove_io_handler(handler, eerror);

    rmc->sock_fd = -1;
    
    close(fd);
    fd = -1;
  }
  
  return retval;
}

int emulab_callback(elvin_io_handler_t handler,
		    int fd,
		    void *rock,
		    elvin_error_t eerror)
{
  char buf[1024];
  int retval = 0;
  
  printf("emulab callback\n");
  read(fd, buf, sizeof(buf));

  return retval;
}

int vmc_callback(elvin_io_handler_t handler,
		 int fd,
		 void *rock,
		 elvin_error_t eerror)
{
  char buf[1024];
  int retval = 0;
  
  printf("vmc callback\n");
  read(fd, buf, sizeof(buf));
  
  return retval;
}
