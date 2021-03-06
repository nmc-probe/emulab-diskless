/*
 * Hacked from BSD fsck_msdosfs.
 */
#ifndef __RCSID
#define __RCSID(s)
#endif

/*
 * Copyright (C) 1995, 1996, 1997 Wolfgang Solfrank
 * Copyright (c) 1995 Martin Husemann
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHORS ``AS IS'' AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE AUTHORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */


#include <sys/cdefs.h>
#ifndef lint
__RCSID("$NetBSD: fat.c,v 1.18 2006/06/05 16:51:18 christos Exp $");
static const char rcsid[] =
  "$FreeBSD: releng/10.2/sbin/fsck_msdosfs/fat.c 268968 2014-07-21 23:23:20Z pfg $";
#endif /* not lint */

#include <stdlib.h>
#include <string.h>
#include <ctype.h>
#include <stdio.h>
#include <unistd.h>

#include "fat_glue.h"

static int checkclnum(struct bootblock *, u_int, cl_t, cl_t *);

/*
 * Check a cluster number for valid value
 */
static int
checkclnum(struct bootblock *boot, u_int fat, cl_t cl, cl_t *next)
{
	static cl_t first = 0;

	if (*next >= (CLUST_RSRVD&boot->ClustMask))
		*next |= ~boot->ClustMask;
	if (*next == CLUST_FREE) {
		if (first == 0)
			first = cl;
		boot->NumFree++;
	} else {
		if (first != 0) {
			fat_addskip(boot, first, cl - first);
			first = 0;
		}
	}
	return FSOK;
}

/*
 * Read a FAT from disk. Returns 1 if successful, 0 otherwise.
 */
static int
_readfat(int fs, struct bootblock *boot, u_int no, u_char **buffer)
{
	off_t off;

	*buffer = malloc(boot->FATsecs * boot->bpbBytesPerSec);
	if (*buffer == NULL) {
		perror("No space for FAT sectors");
		return 0;
	}

	off = boot->bpbResSectors + no * boot->FATsecs;
	off *= boot->bpbBytesPerSec;

	if (lseek(fs, off, SEEK_SET) != off) {
		perror("Unable to read FAT");
		goto err;
	}

	if ((size_t)read(fs, *buffer, boot->FATsecs * boot->bpbBytesPerSec)
	    != boot->FATsecs * boot->bpbBytesPerSec) {
		perror("Unable to read FAT");
		goto err;
	}

	return 1;

    err:
	free(*buffer);
	return 0;
}

/*
 * Read a FAT and decode it into internal format
 */
int
readfat(int fs, struct bootblock *boot, u_int no, struct fatEntry **fp)
{
	struct fatEntry *fat;
	u_char *buffer, *p;
	cl_t cl;
	int ret = FSOK;
	size_t len;

	boot->NumFree = boot->NumBad = 0;

	if (!_readfat(fs, boot, no, &buffer))
		return FSFATAL;

	fat = malloc(len = boot->NumClusters * sizeof(struct fatEntry));
	if (fat == NULL) {
		perror("No space for FAT clusters");
		free(buffer);
		return FSFATAL;
	}
	(void)memset(fat, 0, len);

	if (buffer[0] != boot->bpbMedia
	    || buffer[1] != 0xff || buffer[2] != 0xff
	    || (boot->ClustMask == CLUST16_MASK && buffer[3] != 0xff)
	    || (boot->ClustMask == CLUST32_MASK
		&& ((buffer[3]&0x0f) != 0x0f
		    || buffer[4] != 0xff || buffer[5] != 0xff
		    || buffer[6] != 0xff || (buffer[7]&0x0f) != 0x0f))) {

		/* Windows 95 OSR2 (and possibly any later) changes
		 * the FAT signature to 0xXXffff7f for FAT16 and to
		 * 0xXXffff0fffffff07 for FAT32 upon boot, to know that the
		 * file system is dirty if it doesn't reboot cleanly.
		 * Check this special condition before errorring out.
		 */
		if (buffer[0] == boot->bpbMedia && buffer[1] == 0xff
		    && buffer[2] == 0xff
		    && ((boot->ClustMask == CLUST16_MASK && buffer[3] == 0x7f)
			|| (boot->ClustMask == CLUST32_MASK
			    && buffer[3] == 0x0f && buffer[4] == 0xff
			    && buffer[5] == 0xff && buffer[6] == 0xff
			    && buffer[7] == 0x07))) {
			pwarn("FAT is not clean");
			ret = FSFATAL;
			goto done;
		}
		else {
			/* just some odd byte sequence in FAT */

			switch (boot->ClustMask) {
			case CLUST32_MASK:
				pwarn("%s (%02x%02x%02x%02x%02x%02x%02x%02x)\n",
				      "FAT starts with odd byte sequence",
				      buffer[0], buffer[1], buffer[2], buffer[3],
				      buffer[4], buffer[5], buffer[6], buffer[7]);
				break;
			case CLUST16_MASK:
				pwarn("%s (%02x%02x%02x%02x)\n",
				    "FAT starts with odd byte sequence",
				    buffer[0], buffer[1], buffer[2], buffer[3]);
				break;
			default:
				pwarn("%s (%02x%02x%02x)\n",
				    "FAT starts with odd byte sequence",
				    buffer[0], buffer[1], buffer[2]);
				break;
			}

			ret = FSFATAL;
			goto done;
		}
	}
	switch (boot->ClustMask) {
	case CLUST32_MASK:
		p = buffer + 8;
		break;
	case CLUST16_MASK:
		p = buffer + 4;
		break;
	default:
		p = buffer + 3;
		break;
	}
	for (cl = CLUST_FIRST; cl < boot->NumClusters;) {
		switch (boot->ClustMask) {
		case CLUST32_MASK:
			fat[cl].next = p[0] + (p[1] << 8)
				       + (p[2] << 16) + (p[3] << 24);
			fat[cl].next &= boot->ClustMask;
			ret |= checkclnum(boot, no, cl, &fat[cl].next);
			cl++;
			p += 4;
			break;
		case CLUST16_MASK:
			fat[cl].next = p[0] + (p[1] << 8);
			ret |= checkclnum(boot, no, cl, &fat[cl].next);
			cl++;
			p += 2;
			break;
		default:
			fat[cl].next = (p[0] + (p[1] << 8)) & 0x0fff;
			ret |= checkclnum(boot, no, cl, &fat[cl].next);
			cl++;
			if (cl >= boot->NumClusters)
				break;
			fat[cl].next = ((p[1] >> 4) + (p[2] << 4)) & 0x0fff;
			ret |= checkclnum(boot, no, cl, &fat[cl].next);
			cl++;
			p += 3;
			break;
		}
	}

	/* Flush the skip list */
	{
		cl_t tmp = CLUST_BAD;
		checkclnum(boot, 0, cl, &tmp);
	}

 done:
	free(buffer);
	if (ret) {
		free(fat);
		*fp = NULL;
	} else
		*fp = fat;
	return ret;
}
