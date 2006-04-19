/*
 * EMULAB-COPYRIGHT
 * Copyright (c) 2006 University of Utah and the Flux Group.
 *
 * libnetmon, a library for monitoring network traffic sent by a process. See
 * README for instructions.
 */
#include "libnetmon.h"

/*
 * Die with a standard Emulab-type error message format. In the future, I might
 * try to modify this to simply unlink our wrapper functions so that the app
 * can continue to run.
 */
void croak(char *format, ...) {
    va_list ap;
    fprintf(stderr,"*** ERROR\n    libnetmon: ");
    vfprintf(stderr,format, ap);
    va_end(ap);
    fflush(stderr);
    exit(1);
}

/*
 * Set up our data structures, and go find the real versions of the functions
 * we're going to wrap.
 */
void lnm_init() {

    static bool intialized = false;
    char *sockpath;

    if (intialized == false) {
        DEBUG(printf("Initializing\n"));

        /*
         * Set up the array we use to track which FDs we're tracking
         */
        monitorFDs = (fdRecord*)NULL;
        fdSize = 0;
        allocFDspace();

        /*
         * Figure out which version of the output format we're supposed to use
         */
        char *outversion_s;
        outversion_s = getenv("LIBNETMON_OUTPUTVERSION");
        if (outversion_s) {
            if (!sscanf(outversion_s,"%i",&output_version) == 1) {
                croak("Bad output version: %s\n",outversion_s);
            }
        } else {
            output_version = 1;
        }
        DEBUG(printf("Using output version %i\n",output_version));

#define FIND_REAL_FUN(FUN) \
          real_##FUN = (FUN##_proto_t*)dlsym(RTLD_NEXT,#FUN); \
          if (!real_##FUN) { \
              croak("Unable to get the address of " #FUN "(): %s\n", \
                    dlerror()); \
          }

        /*
         * Find the real versions of the library functions we're going to wrap
         */
        FIND_REAL_FUN(socket);
        FIND_REAL_FUN(close);
        FIND_REAL_FUN(connect);
        FIND_REAL_FUN(write);
        FIND_REAL_FUN(send);
        FIND_REAL_FUN(setsockopt);
        FIND_REAL_FUN(read);
        FIND_REAL_FUN(recv);
        FIND_REAL_FUN(recvmsg);
        FIND_REAL_FUN(accept);

        /*
         * Connect to netmond if we've been asked to
         */
        sockpath = getenv("LIBNETMON_SOCKPATH");
        if (sockpath) {
            int sockfd;
            struct sockaddr_un servaddr;

            DEBUG(printf("Opening socket at path %s\n",sockpath));
            
            sockfd = real_socket(AF_LOCAL, SOCK_STREAM, 0);
            if (!sockfd) {
                croak("Unable to create socket\n");
            }

            servaddr.sun_family = AF_LOCAL;
            strcpy(servaddr.sun_path,sockpath);

            if (real_connect(sockfd, (struct sockaddr*) &servaddr,
                             sizeof(servaddr))) {
                croak("Unable to connect to netmond socket\n");
            }

            outstream = fdopen(sockfd,"w");
            if (!outstream) {
                croak("fdopen() failed on socket\n");
            }

            DEBUG(printf("Done opening socket\n"));

        } else {
            outstream = stdout;
        }

        /*
         * Connect to the netmond's control socket if we've been asked to
         */
        sockpath = getenv("LIBNETMON_CONTROL_SOCKPATH");
        if (sockpath) {
            struct sockaddr_un servaddr;

            DEBUG(printf("Opening control socket at path %s\n",sockpath));
            
            controlfd = real_socket(AF_LOCAL, SOCK_STREAM, 0);
            if (!controlfd) {
                croak("Unable to create socket\n");
            }

            servaddr.sun_family = AF_LOCAL;
            strcpy(servaddr.sun_path,sockpath);

            if (real_connect(controlfd, (struct sockaddr*) &servaddr,
                             sizeof(servaddr))) {
                croak("Unable to connect to netmond control socket\n");
            }

            /*
             * Set non-blocking, so we can quickly test for presence of
             * packets on the control socket.
             *
             * Note: Another possibility would be to use O_ASYNC on this
             * file descriptor, so that we get a signal when data is
             * available. But, this could interact poorly with apps that 
             * use this signal themselves, so this is probably not a
             * good idea.
             */
            if (fcntl(controlfd, F_SETFL, O_NONBLOCK)) {
                croak("Unable to set control socket nonblocking\n");
            }

            /*
             * Ask the server for the parameters we're supposed to use
             */
            control_query();
            lnm_control_wait();
            lnm_control();

            DEBUG(printf("Done opening control socket\n"));

        } else {
            controlfd = -1;
        }

        /*
         * Check to see if we're supposed to force a specific socket buffer
         * size
         */
        char *bufsize_s;
        if ((bufsize_s = getenv("LIBNETMON_SOCKBUFSIZE"))) {
            if (sscanf(bufsize_s,"%i",&forced_bufsize) == 1) {
                printf("libnetmon: Forcing socket buffer size %i\n",
                        forced_bufsize);
            } else {
                croak("Bad sockbufsize: %s\n",bufsize_s);
            }
        } else {
            forced_bufsize = 0;
        }

        /*
         * Run a function to clean up state when the program exits
         */
        if (atexit(&stopWatchingAll) != 0) {
            croak("Unable to register atexit() function\n");
        }

        intialized = true;
    } else {
        /* DEBUG(printf("Skipping intialization\n")); */
    }
}

/*
 * Check for control messages
 */
void lnm_control() {
    ssize_t readrv;
    generic_m msg;

    if (controlfd < 0) {
        return;
    }

    /*
     * Socket is non-blocking
     *
     * NOTE: If read() is too slow, we might want some mechanism to only
     * check this FD every once in a while.
     */
    while ((readrv = real_read(controlfd, &msg, CONTROL_MESSAGE_SIZE))) {
        if (readrv == CONTROL_MESSAGE_SIZE) {
            /*
             * Got a whole message, process it
             */
            process_control_packet(&msg);
        } else if ((readrv < 0) && (errno == EAGAIN)) {
            /*
             * Normal case - no data ready for us
             */
            break;
        } else {
            // For now, croak. We can probably do something better
            croak("Error reading on control socket\n");
        }
    }

    return;
}

/*
 * Wait for a control message, then process it
 */
void lnm_control_wait() {
    fd_set fds;
    int selectrv;
    struct timeval tv;

    if (controlfd < 0) {
        return;
    }

    FD_ZERO(&fds);
    FD_SET(controlfd,&fds);

    tv.tv_sec = 10;
    tv.tv_usec = 0;

    DEBUG(printf("Waiting for a control message\n"));
    selectrv = select(controlfd + 1, &fds, NULL, NULL, &tv);
    if (select == 0) {
        croak("Timed out waiting for a control message\n");
    } else if (select < 0) {
        croak("Bad return value from select() in lnm_control_wait()\n");
    }

    DEBUG(printf("Done waiting for a control message\n"));

    lnm_control();
    
    return;
}

/*
 * Get the unique identifier for a connection
 */
void nameFD(int fd, const struct sockaddr *localname,
        const struct sockaddr *remotename) {
    struct sockaddr_in *remoteaddr, *localaddr;
    union {
        struct sockaddr sa;
        char data[128];
    } sockname;
    socklen_t namelen;

    if (remotename == NULL) {
        int gpn_rv;
	namelen = sizeof(sockname.data);
        gpn_rv = getpeername(fd,(struct sockaddr *)sockname.data,&namelen);
        if (gpn_rv != 0) {
            croak("Unable to get remote socket name: %s\n", strerror(errno));
        }
        /* Assume it's the right address family, since we checked that above */
        remotename = (struct sockaddr*)&(sockname.sa);
        remoteaddr = (struct sockaddr_in *) &(sockname.sa);
    } else {
        remoteaddr = (struct sockaddr_in*)remotename;
    }

    /*
     * Keep some information about the socket, so that we can print it out
     * later
     */
    monitorFDs[fd].remote_port = ntohs(remoteaddr->sin_port);
    /* XXX Error checking */
    monitorFDs[fd].remote_hostname = inet_ntoa(remoteaddr->sin_addr);

    /*
     * Get the local port number
     */
    int gsn_rv;
    if (localname == NULL) {
	namelen = sizeof(sockname.data);
        gsn_rv = getsockname(fd,(struct sockaddr *)sockname.data,&namelen);
        if (gsn_rv != 0) {
            croak("Unable to get local socket name: %s\n", strerror(errno));
        }
        /* Assume it's the right address family, since we checked that above */
        localaddr = (struct sockaddr_in *) &(sockname.sa);
    } else {
        localaddr = (struct sockaddr_in *) localname;
    }

    monitorFDs[fd].local_port = ntohs(localaddr->sin_port);

    monitorFDs[fd].connected = true;
    informConnect(fd);

}

/*
 * Start monitoring a new file descriptor
 */
void startFD(int fd) {
    unsigned int socktype, typesize;
    int sndsize, rcvsize;

    if (monitorFD_p(fd)) {
        printf("Warning: Tried to start monitoring an FD already in use!\n");
        stopFD(fd);
    }

    /*
     * Make sure it's an IP connection
     * XXX : Make sure the pointer is valid!
     */
    /*
     * XXX : Fix this!
    if (remotename->sa_family != AF_INET) {
        DEBUG(printf("Ignoring a non-INET socket\n"));
        return;
    }
    */
    /*
     * Check to make sure it's a TCP socket
     */
    typesize = sizeof(unsigned int);
    if (getsockopt(fd,SOL_SOCKET,SO_TYPE,&socktype,&typesize) != 0) {
        croak("Unable to get socket type: %s\n",strerror(errno));
    }
    if (socktype != SOCK_STREAM) {
        DEBUG(printf("Ignoring a non-TCP socket\n"));
        return;
    }

    allocFDspaceFor(fd);

    /*
     * We may have been asked to force the socket buffer size
     */
    if (forced_bufsize) {
        int sso_rv;
        sso_rv = real_setsockopt(fd,SOL_SOCKET,SO_SNDBUF,
                &forced_bufsize, sizeof(forced_bufsize));
        if (sso_rv == -1) {
            croak("Unable to force out buffer size: %s\n",strerror(errno));
        }
        sso_rv = real_setsockopt(fd,SOL_SOCKET,SO_RCVBUF,
                &forced_bufsize, sizeof(forced_bufsize));
        if (sso_rv == -1) {
            croak("Unable to force in buffer size: %s\n",strerror(errno));
        }
    }

    /*
     * Find out the socket buffer sizes
     */
    sndsize = getNewSockbuf(fd,SO_SNDBUF);
    rcvsize = getNewSockbuf(fd,SO_RCVBUF);

    if (forced_bufsize && (sndsize > forced_bufsize)) {
	printf("Warning: Tried to force SO_SNBUF to %i but got %i\n",
		forced_bufsize, sndsize);
    }

    if (forced_bufsize && (rcvsize > forced_bufsize)) {
	printf("Warning: Tried to force SO_RCVBUF to %i but got %i\n",
		forced_bufsize, rcvsize);
    }

    monitorFDs[fd].monitoring = true;
    monitorFDs[fd].connected = false;

    DEBUG(printf("Watching FD %i\n",fd));

}

/*
 * Stop watching an FD
 */
void stopFD(int fd) {
    if (!monitorFD_p(fd)) {
        return;
    }

    DEBUG(printf("No longer watching FD %i\n",fd));

    /*
     * Let the monitor know we're done with it
     */
    if (output_version == 2) {
        fprintf(outstream,"Closed: ");
        fprintID(outstream,fd);
        fprintf(outstream,"\n");
    }

    monitorFDs[fd].monitoring = false;
    if (monitorFDs[fd].remote_hostname != NULL) {
        monitorFDs[fd].remote_hostname = NULL;
    }
}

/*
 * Stop watching all FDs - for use when the program exits
 */
void stopWatchingAll() {
    int i;
    for (i = 0; i < fdSize; i++) {
        if (monitorFD_p(i)) {
            stopFD(i);
        }
    }
}

/*
 * Print the unique identifier for a connection to the given filestream
 */
void fprintID(FILE *f, int fd) {

    /*
     * Note, we've switched from local_port to FD for the first field - this is
     * so that we can report on a connection before connect() finishes
     */
    fprintf(f,"%i:%s:%i", fd,
            monitorFDs[fd].remote_hostname,
            monitorFDs[fd].remote_port);

}

/*
 * Handle a control message from netmond
 */
void process_control_packet(generic_m *m) {
    max_socket_m *maxmsg;
    out_ver_m *vermsg;

    DEBUG(printf("Processing control packet\n"));
        
    switch (m->type) {
        case CM_MAXSOCKSIZE:
            /*
             * The server told us what the socket buffer sizes should be
             */
            maxmsg = (max_socket_m *)m;

            if (maxmsg->force == 0) {
                forced_bufsize = 0;
            } else {
                forced_bufsize = maxmsg->force_size;
            }

            if (maxmsg->limit == 0) {
                max_bufsize = 0;
            } else {
                max_bufsize = maxmsg->limit_size;
            }

            DEBUG(printf("Set forced_bufsize = %i, max_bufsize = %i\n",
                        forced_bufsize, max_bufsize));
            break;
        case CM_OUTPUTVER:
            /*
             * The server told us which output version to use
             */
            vermsg = (out_ver_m *)m;
            output_version = vermsg->version;

            DEBUG(printf("Set output version to %i\n", output_version));

            break;
        default:
            croak("Got an unexepected control message type: %i\n",m->type);
    }
}

/*
 * Send out a query to the control socket
 */
void control_query() {
    generic_m msg;
    query_m *qmsg;

    if (!controlfd) {
        croak("control_query() called without control socket\n");
    }

    qmsg = (query_m *)&msg;
    qmsg->type = CM_QUERY;

    if ((real_write(controlfd,&msg,CONTROL_MESSAGE_SIZE) !=
                CONTROL_MESSAGE_SIZE)) {
        croak("Error writing control query\n");
    }

    return;

}

void allocFDspace() {
    fdRecord *allocRV;
    unsigned int newFDSize;

    /*
     * Pick a new size, and use realloc() to grown our current allocation
     */
    newFDSize = fdSize + FD_ALLOC_SIZE;
    DEBUG(printf("Allocating space for %i FDs\n",newFDSize));

    allocRV = realloc(monitorFDs, newFDSize * sizeof(fdRecord));
    if (!allocRV) {
        croak("Unable to malloc space for monitorFDs array\n");
    }
    monitorFDs = allocRV;

    /*
     * Set newly-allocated entries to 0
     */
    bzero(monitorFDs + fdSize, (newFDSize - fdSize) * sizeof(fdRecord));

    fdSize = newFDSize;

    return;
}

void allocFDspaceFor(int fd) {
    while (fd >= fdSize) {
	allocFDspace();
    }
}

bool monitorFD_p(int whichFD) {
    /*
     * If this FD is greater than the size of our fd array, then we're
     * definitely not monitoring it.
     */
    if (whichFD >= fdSize) {
        return false;
    } else {
        return monitorFDs[whichFD].monitoring;
    }
}

bool connectedFD_p(int whichFD) {
    if (whichFD >= fdSize) {
        return false;
    } else {
        return monitorFDs[whichFD].connected;
    }
}

/*
 * Let the user know that a packet has been sent.
 */
void log_packet(int fd, size_t len) {
    struct timeval time;
    /*
     * XXX - At some point, we may want to use something more precise than
     * gettimeofday()
     */
    if (gettimeofday(&time,NULL)) {
        croak("Error in gettimeofday()\n");
    }
    switch (output_version) {
        case 0:
            fprintf(outstream,"%lu.%08lu [%i, %i]\n",time.tv_sec, time.tv_usec,
                    fd,len);
            break;
        case 1:
            fprintf(outstream,"%lu.%06lu > %s.%i (%i)\n",time.tv_sec,
                    time.tv_usec, monitorFDs[fd].remote_hostname,
                    monitorFDs[fd].remote_port, len);
            break;
        case 2:
            fprintf(outstream,"%lu.%06lu > ", time.tv_sec, time.tv_usec);
            fprintID(outstream,fd);
            fprintf(outstream," (%i)\n", len);
            break;
        default:
            croak("Bad output version: %i\n",output_version);
    }
    fflush(outstream);
}

/*
 * Inform the user that the nodelay flag has been changed
 */
void informNodelay(int fd) {
    if (output_version == 2) {
	fprintf(outstream,"TCP_NODELAY: ");
	fprintID(outstream,fd);
	fprintf(outstream," %i\n",monitorFDs[fd].tcp_nodelay);
    }
}

void informMaxseg(int fd) {
    if (output_version == 2) {
	fprintf(outstream,"TCP_MAXSEG: ");
	fprintID(outstream,fd);
	fprintf(outstream," %i\n",monitorFDs[fd].tcp_maxseg);
    }
}

void informBufsize(int fd, int which) {
    int bufsize;
    if (output_version == 2) {
	/* TODO: Handle bad which parameter */
	if (which == SO_SNDBUF) {
	    bufsize = monitorFDs[fd].sndbuf;
	} else {
	    bufsize = monitorFDs[fd].rcvbuf;
	}

	fprintf(outstream,"%s: ", (which == SO_SNDBUF) ?
		"SO_SNDBUF" : "SO_RCVBUF");
	fprintID(outstream,fd);
	fprintf(outstream," %i\n", bufsize);

    }
}

void informConnect(int fd) {
    if (output_version == 2) {
	/*
	 * Let the monitor know about it
	 */
	fprintf(outstream,"New: ");
	fprintID(outstream,fd);
	fprintf(outstream,"\n");

	/*
	 * Some things we report on for every connection
	 */
	informNodelay(fd);
	informMaxseg(fd);
	informBufsize(fd, SO_RCVBUF);
	informBufsize(fd, SO_SNDBUF);

	fprintf(outstream,"Connected: ");
	fprintID(outstream,fd);
	fprintf(outstream,"\n");
    }
}

int getNewSockbuf(int fd, int which) {
    int newsize;
    int optsize;
    optsize = sizeof(newsize);
    if (getsockopt(fd,SOL_SOCKET,which,&newsize,&optsize)) {
	croak("Unable to get socket buffer size");
	/* Make GCC happy - won't get called */
	return 0;
    } else {
	if (which == SO_SNDBUF) {
	    monitorFDs[fd].sndbuf = newsize;
	} else {
	    monitorFDs[fd].rcvbuf = newsize;
	}

	return newsize;
    }
}

/*
 * Library function wrappers
 */

int socket(int domain, int type, int protocol) {
    int returnedFD;
    lnm_init();
    DEBUG(printf("socket() called\n"));
    returnedFD = real_socket(domain, type, protocol);
    if (returnedFD > 0) {
	startFD(returnedFD);
    }

    return returnedFD;
}

/*
 * We're only going to bother to monitor FDs after they have succeeded in
 * connecting to some host.
 *
 * TODO: Allow for some filters:
 *      Only TCP connections
 *      Only certain hosts? (eg. not loopback)
 */
int connect(int sockfd, const struct sockaddr *serv_addr, socklen_t addrlen) {

    int rv;
    lnm_init();
    lnm_control();

    DEBUG(printf("connect() called on %i\n",sockfd));

    /*
     * So, this is a bit messy, but we gotta do it this way. We report on the
     * socket _before_ calling the real connect. This help keep the stub
     * from getting too far behind - otherwise, it can't start until the real
     * app has finished the three way handshake and is well on its way to
     * sending data. If connect() fails, we'll report a socket close below.
     */

    /*
     * Find out some things about the address we're trying to connect to. We
     * decided in socket() if we're going to monitor this connection or not
     * TODO: We really should verify that serv_addr is a legal pointer
     */
    if (monitorFD_p(sockfd)) {
        nameFD(sockfd,NULL,serv_addr);
    }

    rv = real_connect(sockfd, serv_addr, addrlen);

    if (!monitorFD_p(sockfd)) {
        /*
         * Just pass the result back
         */
        return rv;
    }

    /*
     * There are actually some cases when connect() can 'fail', but we
     * still want to watch the FD
     */
    if ((rv == 0) ||
           ((errno == EISCONN) ||     /* Socket is already connected */
            (errno == EINPROGRESS) || /* Non blocking socket */
            (errno == EINTR) ||       /* Connect will happen in background */
            (errno == EALREADY))) {    /* In progress in background */
        /*
         * TODO: In the case of the 'errors' that mean the socket is
         * connecting in the background, we really should make sure that
         * it actually connects - but this could be tricky. The caller is
         * supposed to select() on the FD to find out when it's ready, but
         * if they don't, and just write to it, we won't find out. So, for
         * now, just assume that the connect() will succeed.
         */

        /*
         * Get the local port number so that we can monitor about it
         */
        struct sockaddr_in localaddr;
        int namelen = sizeof(localaddr);
        if (getsockname(sockfd, (struct sockaddr*)&localaddr,&namelen) != 0) {
            croak("Unable to get local socket name: %s\n", strerror(errno));
        }
        int local_port = ntohs(localaddr.sin_port);

        fprintf(outstream,"LocalPort: ");
        fprintID(outstream,sockfd);
        fprintf(outstream," %i\n",local_port);
    } else {
        /*
         * Do this in case the connection really did fail
         */
        stopFD(sockfd);
    }

    return rv;
}

/*
 * We will also watch for accept()ed connections
 */
int accept(int s, struct sockaddr * addr,
        socklen_t * addrlen) {

    int rv;
    lnm_init();
    lnm_control();

    DEBUG(printf("accept() called on %i\n",s));

    rv = real_accept(s, addr, addrlen);

    if (!monitorFD_p(s)) {
	return rv;
    } 

    if (rv > 0) {
        /*
         * Got a new client! Start it up, name it, and report on its local port
         */
        startFD(rv);
        nameFD(rv,addr,NULL);
        fprintf(outstream,"LocalPort: ");
        fprintID(outstream,rv);
        fprintf(outstream," %i\n",ntohs(((struct sockaddr_in*)addr)->sin_port));
    }

    return rv;

}

/*
 * When the user closes a socket, we can stop monitoring it
 */
int close(int d) {
    int rv;

    lnm_init();
    lnm_control();

    rv = real_close(d);

    if (!rv && monitorFD_p(d)) {
        DEBUG(printf("Detected a closed socket with close()\n"));
        stopFD(d);
    }

    return rv;
}

/*
 * Wrap the send() function so that we can log messages sent to any of the
 * socket's we're monitoring.
 *
 * TODO: Need to write wrappers for other functions that can be used to send
 * data on a socket:
 * sendto
 * sendmsg
 * writev
 * others?
 */
ssize_t send(int s, const void *msg, size_t len, int flags) {
    ssize_t rv;

    lnm_init();
    lnm_control();

    /*
     * Wait until _after_ the packet is sent to log it, since the call might
     * block, and we basically want to report when the kernel acked receipt of
     * the packet
     */
    /*
     * TODO: There are a LOT of error cases, flags, etc, that we should handle.
     * For 
     */
    rv = real_send(s,msg,len,flags);

    if ((rv > 0) && monitorFD_p(s)) {
        log_packet(s,rv);
    }

    return rv;

}

ssize_t write(int fd, const void *buf, size_t count) {
    ssize_t rv;

    lnm_init();
    lnm_control();

    /*
     * Wait until _after_ the packet is sent to log it, since the call might
     * block, and we basically want to report when the kernel acked receipt of
     * the packet
     */
    rv = real_write(fd,buf,count);

    if ((rv > 0) && monitorFD_p(fd)) {
        log_packet(fd,rv);
    }

    return rv;

}

int setsockopt (int s, int level, int optname, const void *optval,
                 socklen_t optlen) {
    int rv;

    lnm_init();
    lnm_control();

    DEBUG(printf("setsockopt called (%i,%i)\n",level,optname));

    /*
     * If we're supposed to monitor this FD, there are some things that can
     * make us ignore or cap this call.
     */
    if (monitorFD_p(s)) {
	/*
	 * Note, we do this on all sockets, not just those we are currently
	 * monitoring, since it's likely they'll call setsockopt() before
	 * connect()
	 */
	if ((level == SOL_SOCKET) && ((optname == SO_SNDBUF) ||
				      (optname == SO_RCVBUF))) {
	    if (forced_bufsize) {
		/*
		 * I believe this is the right thing to do - return success but
		 * don't do anything - I think that this is what you normally get
		 * when you, say, pick a socket buffer size that is too big.
		 */
		printf("Warning: Ignored attempt to change SO_SNDBUF or "
		       "SO_RCVBUF\n");
		return 0;
	    } else if (max_bufsize && (*((int *)optval) > max_bufsize)) {
		printf("Warning: Capped attempt to change SO_SNDBUF or "
		       "SO_RCVBUF\n");
		*((int *)optval) = max_bufsize;
	    }

	}
    }

    /*
     * Actually call the real thing
     */
    rv = real_setsockopt(s,level,optname,optval,optlen);

    /*
     * If the call succeeded, we have to record some more information about it
     */
    if (rv == 0 && monitorFD_p(s)) {
	if ((level == SOL_SOCKET) && ((optname == SO_SNDBUF) ||
				      (optname == SO_RCVBUF))) {
	    /*
	     * We have to get the socket buffer size the kernel chose: it might
	     * not be exactly what the user asked for
	     */
	    getNewSockbuf(s,optname);
	    if (connectedFD_p(s)) {
		informBufsize(s,optname);
	    }
	}

	/*
	 * There are some TCP options we have to watch for
	 */
	if (level == IPPROTO_TCP) {
	    if (optname == TCP_NODELAY) {
		monitorFDs[s].tcp_nodelay = *((int *)optval);

		if (connectedFD_p(s)) {
		     /* If connected, inform user of this call */
		    informNodelay(s);
		}
	    }
	    if (optname == TCP_MAXSEG) {
		monitorFDs[s].tcp_maxseg = *((int *)optval);

		if (connectedFD_p(s)) {
		    /* If connected, inform user of this call */
		    informMaxseg(s);
		}
	    }
	}
    }

    /*
     * Finally, give back the returned value
     */
    return rv;
}

/*
 * The usual way to find 'eof' on a socket is to look for a small read
 * read. Since some programs might not be well-behaved in the sense that they
 * may not close() the socket, we wrap read() too
 */
ssize_t read(int d, void *buf, size_t nbytes) {
    ssize_t rv;

    lnm_init();
    lnm_control();

    DEBUG(printf("read() called\n"));

    rv = real_read(d,buf,nbytes);
    
    if ((rv == 0) && monitorFD_p(d)) {
        DEBUG(printf("Detected a closed socket with a zero-length read()\n"));
        stopFD(d);
    }
    
    return rv;
}

/*
 * See comment for read()
 */
ssize_t recv(int s, void *buf, size_t len, int flags) {
    ssize_t rv;

    lnm_init();
    lnm_control();

    rv = real_recv(s,buf,len,flags);
    
    DEBUG(printf("recv() returned %i\n",rv));

    if ((rv == 0) && monitorFD_p(s)) {
        DEBUG(printf("Detected a closed socket with a zero-length recv()\n"));
        stopFD(s);
    }

    
    return rv;
}

/*
 * See comment for read()
 */
ssize_t recvmsg(int s, struct msghdr *msg, int flags) {
    ssize_t rv;

    lnm_init();
    lnm_control();

    rv = real_recvmsg(s,msg,flags);
    
    if ((rv == 0) && monitorFD_p(s)) {
        DEBUG(printf("Detected a closed socket with zero-length recvmsg()\n"));
        stopFD(s);
    }
    
    return rv;
}
