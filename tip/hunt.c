/*
 * Copyright (c) 1983, 1993
 *	The Regents of the University of California.  All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgement:
 *	This product includes software developed by the University of
 *	California, Berkeley and its contributors.
 * 4. Neither the name of the University nor the names of its contributors
 *    may be used to endorse or promote products derived from this software
 *    without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE REGENTS AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE REGENTS OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 */

#ifndef lint
#if 0
static char sccsid[] = "@(#)hunt.c	8.1 (Berkeley) 6/6/93";
#endif
static const char rcsid[] =
	"$Id: hunt.c,v 1.4 2001-07-31 21:58:35 stoller Exp $";
#endif /* not lint */

#include "tip.h"

#include <sys/types.h>
#include <err.h>
#ifndef LINUX
#include <libutil.h>
#endif

#ifdef USESOCKETS
#include <sys/socket.h>
#include <netinet/in.h>

int	socket_open(char *devname);
#endif

extern char *getremote();
extern char *rindex();

static	jmp_buf deadline;
static	int deadfl;

void
dead()
{
	deadfl = 1;
	longjmp(deadline, 1);
}

int
hunt(name)
	char *name;
{
	register char *cp;
	sig_t f;
	int res;

	f = signal(SIGALRM, dead);
	while ((cp = getremote(name))) {
		deadfl = 0;
#if HAVE_UUCPLOCK
		uucplock = rindex(cp, '/')+1;
		if ((res = uu_lock(uucplock)) != UU_LOCK_OK) {
			if (res != UU_LOCK_INUSE)
				fprintf(stderr, "uu_lock: %s\n", uu_lockerr(res));
			continue;
		}
#endif
		/*
		 * Straight through call units, such as the BIZCOMP,
		 * VADIC and the DF, must indicate they're hardwired in
		 *  order to get an open file descriptor placed in FD.
		 * Otherwise, as for a DN-11, the open will have to
		 *  be done in the "open" routine.
		 */
		if (!HW)
			break;
		if (setjmp(deadline) == 0) {
			alarm(10);
#ifdef USESOCKETS
			if ((FD = socket_open(cp)) >= 0) {
				HW = 0;
				alarm(0);
				signal(SIGALRM, SIG_DFL);
				return ((int)cp);
			}
			else
#endif
			if ((FD = open(cp, O_RDWR)) >= 0)
				ioctl(FD, TIOCEXCL, 0);
		}
		alarm(0);
		if (FD < 0) {
			warn("%s", cp);
			deadfl = 1;
		}
		if (!deadfl) {
#if HAVE_TERMIOS
			struct termios t;

			if (tcgetattr(FD, &t) == 0) {
				t.c_cflag |= HUPCL;
				(void)tcsetattr(FD, TCSANOW, &t);
			}
#else /* HAVE_TERMIOS */
#ifdef TIOCHPCL
			ioctl(FD, TIOCHPCL, 0);
#endif
#endif /* HAVE_TERMIOS */
			signal(SIGALRM, SIG_DFL);
			return ((int)cp);
		}
#if HAVE_UUCPLOCK
		(void)uu_unlock(uucplock);
#endif
	}
	signal(SIGALRM, f);
	return (deadfl ? -1 : (int)cp);
}

#ifdef USESOCKETS
/*
 *
 */
int
socket_open(char *devname)
{
	int			sock;
	struct sockaddr_in	name;
	char			aclname[BUFSIZ], buf[BUFSIZ];
	int			aclbits[3];
	int			port;
	FILE			*fp;

	(void) sprintf(aclname, "%s.acl", devname);

	if ((fp = fopen(aclname, "r")) == NULL) {
		return -1;
	}
	fscanf(fp, "%d %x %x %x", &port,
	       &aclbits[0], &aclbits[1], &aclbits[2]);
	fclose(fp);

	/* Create socket from which to read. */
	sock = socket(AF_INET, SOCK_STREAM, 0);
	if (sock < 0) {
		return sock;
	}

	/* Create name. */
	name.sin_family = AF_INET;
	inet_aton("127.0.0.1", &name.sin_addr);
	name.sin_port   = htons(port);

	/* Caller picks up and displays error */
	if (connect(sock, (struct sockaddr *) &name, sizeof(name)) < 0) {
		close(sock);
		return -1;
	}

	/*
	 * Send the acl bits.
	 */
	if (write(sock, aclbits, sizeof(aclbits)) != sizeof(aclbits)) {
		close(sock);
		return -1;
	}

	return sock;
}
#endif
