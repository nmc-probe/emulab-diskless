#ifndef _STUB_H
#define _STUB_H

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <errno.h>
#include <string.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <sys/wait.h>
#include <signal.h>
#include <time.h>
#include <sys/time.h>
#include <fcntl.h>
#include <netdb.h>
#include <math.h>

#define STDIN 0 // file descriptor for standard input
#define QUANTA 5000000    //feed-loop interval in usec
#define MONITOR_PORT 3490 //the port the monitor connects to
#define SENDER_PORT  3491 //the port the stub senders connect to 
#define PENDING_CONNECTIONS  10	 //the pending connections the queue will hold
#define CONCURRENT_SENDERS   50	 //concurrent senders the stub maintains
#define CONCURRENT_RECEIVERS 50	 //concurrent receivers the stub maintains
#define MAX_PAYLOAD_SIZE     100 //size of the traffic payload 
#define MAX_TCPDUMP_LINE     256 //the max line size of the tcpdump output
#define SIZEOF_LONG sizeof(long) //message bulding block
#define BANDWIDTH_OVER_THROUGHPUT 0 //the safty margin for estimating the available bandwidth
#define SNIFFWIN_SIZE 131071 //from min(net.core.rmem_max, max(net.ipv4.tcp_rmem)) on Plab linux

//magic numbers
#define CODE_BANDWIDTH 0x00000001 
#define CODE_DELAY     0x00000002 
#define CODE_LOSS      0x00000003 


struct connection {
  short  valid;
  int    sockfd;
  unsigned long ip;
  time_t last_usetime; //last monitor access time
};
typedef struct connection connection;

extern short  flag_debug;
extern connection rcvdb[CONCURRENT_RECEIVERS];
extern unsigned long delays[CONCURRENT_SENDERS];
extern int search_rcvdb(unsigned long indexip);
extern void sniff(int to_ms);

#endif








