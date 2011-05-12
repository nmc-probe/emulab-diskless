/*
 * EMULAB-COPYRIGHT
 * Copyright (c) 2010-2011 University of Utah and the Flux Group.
 * All rights reserved.
 */

/*
 * Configuration "file" handling for a "null" or default configuration.
 * Just uses globally configurated info for all images:
 *
 *  - images get/put to a standard image directory
 *  - servers run as same user as master server
 */ 

#ifdef USE_NULL_CONFIG
#include <sys/param.h>
#include <sys/stat.h>
#include <ctype.h>
#include <errno.h>
#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <unistd.h>
#include <assert.h>
#include "log.h"
#include "configdefs.h"

static int debug = 1;

static char *DEFAULT_IMAGEDIR	= "/usr/local/images";
static char *DEFAULT_MCADDR	= "239.192.1";
static char *DEFAULT_MCPORT	= "1025";

static char *indexfile;
char *imagedir = NULL;
static char *rimagedir;

/* Multicast address/port base info */
static int mc_a, mc_b, mc_c, mc_port;

static char *imageidtopath(char *imageid);
static char *myrealpath(char *path, char rpath[PATH_MAX]);

/* Memory alloc functions that abort when no memory */
static void *mymalloc(size_t size);
static void *myrealloc(void *ptr, size_t size);
static char *mystrdup(const char *str);

static void
null_deinit(void)
{
}

static int
null_read(void)
{
	/* "Reading" the config file is a no-op. */
	return 0;
}

static void *
null_save(void)
{
	static int dummy;

	/* Just return non-zero value */
	return (void *)&dummy;
}

static int
null_restore(void *state)
{
	return 0;
}

static void
null_free(void *state)
{
}

/*
 * Set the allowed GET methods.
 * XXX for now, just unicast and multicast.
 */
static void
set_get_methods(struct config_host_authinfo *ai, int ix)
{
	ai->imageinfo[ix].get_methods = CONFIG_IMAGE_MCAST;
#if 1
	ai->imageinfo[ix].get_methods |= CONFIG_IMAGE_UCAST;
#endif
}

/*
 * Set the GET options for a particular node/image.
 */
static void
set_get_options(struct config_host_authinfo *ai, int ix)
{
	char str[256];

	strcpy(str, "");
	strcat(str, " -W 100000000 -K 15");

	/*
	 * We use a small server inactive timeout since we no longer have
	 * to start up a frisbeed well in advance of the client(s).
	 *
	 * XXX we cranked this from 60 to 180 seconds to account for clients
	 * with lots of write buffer memory but slow disks, giving them time
	 * to flush all their buffers and report their stats before we give
	 * up on them.
	 */
	strcat(str, " -T 180");

	ai->imageinfo[ix].get_options = mystrdup(str);
}

#define FREE(p) { if (p) free(p); }

/*
 * Free the dynamically allocated host_authinfo struct.
 */
static void
null_free_host_authinfo(struct config_host_authinfo *ai)
{
	int i;

	if (ai == NULL)
		return;

	FREE(ai->hostid);
	if (ai->imageinfo != NULL) {
		for (i = 0; i < ai->numimages; i++) {
			FREE(ai->imageinfo[i].imageid);
			FREE(ai->imageinfo[i].path);
			FREE(ai->imageinfo[i].sig);
			FREE(ai->imageinfo[i].get_options);
			FREE(ai->imageinfo[i].put_options);
			FREE(ai->imageinfo[i].extra);
		}
		free(ai->imageinfo);
	}
	assert(ai->extra == NULL);
	free(ai);
}

/*
 * Return the IP address/port to be used by the server/clients for
 * the image listed in ai->imageinfo[0].  Methods lists one or more transfer
 * methods that the client can handle, we return the method chosen.
 * If first is non-zero, then we need to return a "new" address and *addrp
 * and *portp are uninitialized.  If non-zero, then our last choice failed
 * (probably due to a port conflict) and we need to choose a new address
 * to try, possibly based on the existing info in *addrp and *portp.
 *
 * For Emulab, we use the frisbee_index from the DB along with the base
 * multicast address and port to compute a unique address/port.  Uses the
 * same logic that frisbeelauncher used to use.  For retries (first==0),
 * we choose a whole new addr/port for multicast.
 *
 * For unicast, we use the index as well, just to produce a unique port
 * number.
 *
 * Return zero on success, non-zero otherwise.
 */
static int
null_get_server_address(struct config_imageinfo *ii, int methods, int first,
			in_addr_t *addrp, in_port_t *portp, int *methp)
{
	int	a, b, c, d, idx;
	FILE	*fd;

	if ((methods & (CONFIG_IMAGE_MCAST|CONFIG_IMAGE_UCAST)) == 0) {
		error("get_server_address: only support UCAST/MCAST");
		return 1;
	}

	if ((fd = fopen(indexfile, "r+")) == NULL) {
		error("get_server_address: could not open index file '%s'!",
		      indexfile);
		return 1;
	}
	if (fscanf(fd, "%d", &idx) != 1 || idx < 0) {
		error("get_server_address: bogus index in '%s'!",
		      indexfile);
		fclose(fd);
		return 1;
	}
	if (fseek(fd, 0L, SEEK_SET) != 0 || fprintf(fd, "%d\n", idx+1) < 0) {
		error("get_server_address: cannot update index in '%s'!",
		      indexfile);
		fclose(fd);
		return 1;
	}
	fclose(fd);

	a = mc_a;
	b = mc_b;
	c = mc_c;
	d = 1;

	d += idx;
	if (d > 254) {
		c += (d / 254);
		d = (d % 254) + 1;
	}
	if (c > 254) {
		b += (c / 254);
		c = (c % 254) + 1;
	}
	if (b > 254) {
		error("get_server_address: ran out of MC addresses!");
		return 1;
	}

	if (methods & CONFIG_IMAGE_MCAST) {
		/*
		 * XXX avoid addresses that "flood".
		 * 224.0.0.x and 224.128.0.x are defined to flood,
		 * but because of the way IP multicast addresses map
		 * onto ethernet addresses (only the low 23 bits are used)
		 * ANY MC address (224-239) with those bits will also flood.
		 * So avoid those.
		 */
		if (c == 0 && (b == 0 || b == 128))
			c++;

		*methp = CONFIG_IMAGE_MCAST;
		*addrp = (a << 24) | (b << 16) | (c << 8) | d;
	} else if (methods & CONFIG_IMAGE_UCAST) {
		*methp = CONFIG_IMAGE_UCAST;
		*addrp = 0;
	}
	*portp = mc_port + (((c << 8) | d) & 0x7FFF);

	if (debug)
		fprintf(stderr,
			"get_server_address: idx %d, addr 0x%x, port %d\n",
			idx, *addrp, *portp);

	return 0;
}

/*
 * Just return imagedir for GET and PUT.
 */
static void
allow_stddirs(char *imageid, 
	      struct config_host_authinfo *get,
	      struct config_host_authinfo *put)
{
	struct config_imageinfo *ci;
	struct stat sb;

	if (get == NULL && put == NULL)
		return;

	/*
	 * No image specified, just return info about the directories
	 * that are accessible.
	 */
	if (imageid == NULL) {
		int ni, i;
		size_t ns;
		char *dirs[8];

		/*
		 * Right now, allow PUT to imagedir.
		 */
		if (put != NULL) {
			dirs[0] = imagedir;
			ni = put->numimages + 1;
			ns = ni * sizeof(struct config_imageinfo);
			if (put->imageinfo)
				put->imageinfo = myrealloc(put->imageinfo, ns);
			else
				put->imageinfo = mymalloc(ns);
			for (i = put->numimages; i < ni; i++) {
				ci = &put->imageinfo[i];
				ci->imageid = NULL;
				ci->path = mystrdup(dirs[i - put->numimages]);
				ci->flags = CONFIG_PATH_ISDIR;
				if (stat(ci->path, &sb) == 0) {
					ci->sig = mymalloc(sizeof(time_t));
					*(time_t *)ci->sig = sb.st_mtime;
					ci->flags |= CONFIG_SIG_ISMTIME;
				} else
					ci->sig = NULL;
				ci->get_options = NULL;
				ci->get_methods = 0;
				ci->get_uid = ci->get_gid = -1;
				ci->put_options = NULL;
				ci->extra = NULL;
			}
			put->numimages = ni;
		}
		/*
		 * and GETs as well.
		 */
		if (get != NULL) {
			dirs[0] = imagedir;
			ni = get->numimages + 1;
			ns = ni * sizeof(struct config_imageinfo);
			if (get->imageinfo)
				get->imageinfo = myrealloc(get->imageinfo, ns);
			else
				get->imageinfo = mymalloc(ns);
			for (i = get->numimages; i < ni; i++) {
				ci = &get->imageinfo[i];
				ci->imageid = NULL;
				ci->path = mystrdup(dirs[i - get->numimages]);
				ci->flags = CONFIG_PATH_ISDIR;
				if (stat(ci->path, &sb) == 0) {
					ci->sig = mymalloc(sizeof(time_t));
					*(time_t *)ci->sig = sb.st_mtime;
					ci->flags |= CONFIG_SIG_ISMTIME;
				} else
					ci->sig = NULL;
				set_get_options(get, i);
				set_get_methods(get, i);
				ci->get_uid = ci->get_gid = -1;
				ci->put_options = NULL;
				ci->extra = NULL;
			}
			get->numimages = ni;
		}
		goto done;
	}

	/*
	 * Image was specified; find the real path for the targetted file.
	 * Don't want users symlinking to files outside their allowed space.
	 */
	assert(imageid == NULL);

 done:
	return;
}

/*
 * Find all images (imageid==NULL) or a specific image (imageid!=NULL)
 * that a particular node can access for GET/PUT.  Pretty simple default:
 * any node can read/write any image within the default image directory.
 *
 * For a single image this will either return a single image or no image.
 * The desired image must be one of the images that would be returned in
 * the all images case.
 *
 * Imageids should be a path to which we prepend the imagedir if it is
 * not already there.
 *
 * Return zero on success, non-zero otherwise.
 */
static int
null_get_host_authinfo(struct in_addr *req, struct in_addr *host,
		       char *imageid,
		       struct config_host_authinfo **getp,
		       struct config_host_authinfo **putp)
{
	struct config_host_authinfo *get = NULL, *put = NULL;
	struct config_imageinfo *ci;
	struct stat sb;
	int exists;
	char *path = NULL;

	/*
	 * If the requester is not the same as the host, then it is a proxy
	 * request.  We don't do proxying.
	 */
	if (req->s_addr != host->s_addr)
		return 1;

	/*
	 * If an imageid is specified and it doesn't resolve to a valid
	 * path, it is an error.
	 */
	if (imageid != NULL && (path = imageidtopath(imageid)) == NULL)
		return 1;

	if (getp == NULL && putp == NULL) {
		if (path)
			free(path);
		return 0;
	}

	if (getp) {
		get = mymalloc(sizeof *get);
		memset(get, 0, sizeof *get);
	}
	if (putp) {
		put = mymalloc(sizeof *put);
		memset(put, 0, sizeof(*put));
	}

	/*
	 * XXX we don't care about the node identity right now.
	 * we should at least restrict it to the local subnet.
	 */
	if (get != NULL)
		get->hostid = mystrdup(inet_ntoa(*host));
	if (put != NULL)
		put->hostid = mystrdup(inet_ntoa(*host));

	/*
	 * If no image specified, just return the standard directories.
	 * We could also return the list of images that already exist...
	 * someday.
	 */
	if (imageid == NULL) {
		allow_stddirs(imageid, get, put);
		goto done;
	}

	/*
	 * Need to make sure path really exists.
	 *
	 * imageidtopath will return success even when the final component
	 * does not exist.  That is alright for a put, but not a get.
	 * What do we return for flags on a PUT?  What about sig?
	 */
	if (stat(path, &sb) < 0)
		exists = 0;
	else
		exists = 1;

	/*
	 * Otherwise, return this image
	 */
	if (put != NULL) {
		put->imageinfo = mymalloc(sizeof(struct config_imageinfo));
		put->numimages = 1;
		ci = &put->imageinfo[0];
		ci->imageid = mystrdup(imageid);
		ci->path = mystrdup(path);
		ci->flags = CONFIG_PATH_ISFILE;
		if (exists) {
			ci->sig = mymalloc(sizeof(time_t));
			*(time_t *)ci->sig = sb.st_mtime;
			ci->flags |= CONFIG_SIG_ISMTIME;
		} else
			ci->sig = NULL;
		ci->get_methods = 0;
		ci->get_options = NULL;
		ci->get_uid = ci->get_gid = -1;
		ci->put_options = NULL;
		ci->extra = NULL;
	}

	if (get != NULL) {
		get->imageinfo = mymalloc(sizeof(struct config_imageinfo));
		get->numimages = 1;
		ci = &get->imageinfo[0];
		ci->imageid = mystrdup(imageid);
		ci->path = mystrdup(path);
		ci->flags = CONFIG_PATH_ISFILE;
		if (exists) {
			ci->sig = mymalloc(sizeof(time_t));
			*(time_t *)ci->sig = sb.st_mtime;
			ci->flags |= CONFIG_SIG_ISMTIME;
		} else
			ci->sig = NULL;
		set_get_methods(get, 0);
		set_get_options(get, 0);
		ci->get_uid = ci->get_gid = -1;
		ci->put_options = NULL;
		ci->extra = NULL;
	}

 done:
	if (path)
		free(path);
	if (getp) *getp = get;
	if (putp) *putp = put;
	return 0;
}

static void
dump_host_authinfo(FILE *fd, char *node, char *cmd,
		   struct config_host_authinfo *ai)
{
	int i;

	/*
	 * Otherwise, dump the whole list of images for each node
	 */
	for (i = 0; i < ai->numimages; i++)
		if (ai->imageinfo[i].flags == CONFIG_PATH_ISFILE)
			fprintf(fd, "%s ", ai->imageinfo[i].imageid);

	/*
	 * And dump any directories that can be accessed
	 */
	for (i = 0; i < ai->numimages; i++)
		if (ai->imageinfo[i].flags == CONFIG_PATH_ISDIR)
			fprintf(fd, "%s/* ", ai->imageinfo[i].path);

	fprintf(fd, "\n");
}

static void
null_dump(FILE *fd)
{
	fprintf(fd, "Basic master frisbee config:\n");
	/* XXX do something */
}

struct config null_config = {
	null_deinit,
	null_read,
	null_get_host_authinfo,
	null_free_host_authinfo,
	null_get_server_address,
	null_save,
	null_restore,
	null_free,
	null_dump
};

struct config *
null_init(void)
{
	char pathbuf[PATH_MAX], *path;
	static int called;
	struct stat sb;

	if (called)
		return &null_config;
	called++;

	if (imagedir == NULL)
		imagedir = DEFAULT_IMAGEDIR;
	if ((path = myrealpath(imagedir, pathbuf)) == NULL) {
		error("null_init: could not resolve '%s'", imagedir);
		return NULL;
	}
	rimagedir = mystrdup(path);

	indexfile = mymalloc(strlen(imagedir) + 1 + strlen(".index") + 1);
	sprintf(indexfile, "%s/.index", imagedir);
	if (stat(indexfile, &sb) < 0) {
		FILE *fd;

		if ((fd = fopen(indexfile, "w")) == NULL) {
			error("null_init: could not create index file '%s'",
			      indexfile);
			unlink(indexfile);
			free(indexfile);
			return NULL;
		}
		fputs("0\n", fd);
		fclose(fd);
	}

	/* One time parsing of MC address info */
	if (sscanf(DEFAULT_MCADDR, "%d.%d.%d", &mc_a, &mc_b, &mc_c) != 3) {
		error("null_init: MC base addr '%s' not in A.B.C format!",
		      DEFAULT_MCADDR);
		return NULL;
	}
	mc_port = atoi(DEFAULT_MCPORT);

	return &null_config;
}

/*
 * Validate an imageid and convert it into a filesystem path in our
 * imageid directory.  Returns a pointer to a dynamically allocated path
 * path string on success, NULL otherwise.  On success, the path will either
 * point to an existing regular file or to a non-existent file within the
 * image directory.
 */
static char *
imageidtopath(char *imageid)
{
	char rpath[PATH_MAX], *path, *cp;
	int idlen;

	assert(imageid != NULL);

	/*
	 * We seriously limit what chars can be in an imageid since we
	 * use it as a file name.
	 */
	idlen = strlen(imageid);
	if (idlen == 0 || idlen >= PATH_MAX) {
		if (debug)
			fprintf(stderr, "imageid too short/long\n");
		return NULL;
	}
	for (cp = imageid; *cp != '\0'; cp++) {
		if (isalnum(*cp) ||
		    *cp == '-' || *cp == '.' || *cp == '+' || *cp == '_')
			continue;
		if (*cp == '/')
			continue;
		if (debug)
			fprintf(stderr, "bogus char (0x%x) in imageid\n", *cp);
		return NULL;
	}

	/*
	 * If imageid is specified and starts with a '/', it is an
	 * absolute path.  We prepend imagedir if it doesn't already
	 * exist.
	 *
	 * If it doesn't start with a '/' we prepend imagedir.
	 */
	assert(imagedir && imagedir[0]);
	if (imageid[0] != '/' || strstr(imageid, imagedir) != imageid) {
		path = mymalloc(strlen(imagedir) + idlen + 2);
		strcpy(path, imagedir);
		if (imageid[0] != '/')
			strcat(path, "/");
		strcat(path, imageid);
	} else
		path = mystrdup(imageid);
	if (debug)
		fprintf(stderr, "imageid %s: mapped to path '%s'\n",
			imageid, path);

	/*
	 * Run it through realpath.
	 * Sounds easy enough, right?  Well, except that the whole path
	 * doesn't need to exist.  The imageid can effectively create
	 * subdirectories if it contains a slash, so realpath can fail
	 * because of missing intermediate subdirs and not because of
	 * an invalid path.
	 */
	if (myrealpath(path, rpath) == NULL) {
		char pathbuf[PATH_MAX];
		char *next, *ep;

		/*
		 * Make sure all intermediate directories exist.
		 * So we start with the imagedir path, and iterate through
		 * the path, adding one component from the imageid each time.
		 * As long as realpath succeeds each time and the resulting
		 * path is within the imagedir, everything is okay and we
		 * create the subdir and continue.
		 */
		strncpy(pathbuf, path, PATH_MAX);
		assert(strncmp(pathbuf, imagedir, strlen(imagedir)) == 0);
		next = &pathbuf[strlen(imagedir)+1];
		assert(next[-1] == '/');
		while ((ep = index(next, '/')) != NULL) {
			*ep = '\0';
			if (debug)
				fprintf(stderr, "Testing: %s\n", pathbuf);

			/*
			 * If realpath fails on the component, see if
			 * it failed due to a missing component in our path.
			 * If so, create the component and continue.
			 *
			 * N.B. realpath returning the canonicalized version
			 * of the path resolved so far in the case of an
			 * error, is documented behavior for the FreeBSD
			 * version but not for Linux. However, it is the
			 * observed behavior for Linux and we rely on it
			 * for now.
			 */
			if (myrealpath(pathbuf, rpath) == NULL) {
				if (errno != ENOENT ||
				    strstr(rpath, rimagedir) != rpath) {
					fprintf(stderr,
						"  resolves bad (%s)\n",
						rpath);
					free(path);
					return NULL;
				}
				/*
				 * Try creating the missing component.
				 */
				if (mkdir(rpath, 0755) < 0) {
					fprintf(stderr,
						"  could not create (%s)\n",
						rpath);
					free(path);
					return NULL;
				}
				if (debug)
					fprintf(stderr, "  created (%s)\n",
						rpath);
			} else {
				if (debug)
					fprintf(stderr, "  exists (%s)\n",
						rpath);
			}
			*ep = '/';
			next = ep+1;
		}

		/*
		 * We are down to the final component of the original path.
		 * It should either exist and be a regular file or not
		 * exist at all.
		 */
		if (myrealpath(pathbuf, rpath) == NULL &&
		    (errno != ENOENT || strstr(rpath, rimagedir) != rpath)) {
			if (debug)
				fprintf(stderr,
					"imageid %s: realpath (%s) bad\n",
					imageid, rpath);
			free(path);
			return NULL;
		}
	}
	free(path);

	/*
	 * Realpath worked if we got here.
	 * But we still need to make sure it resolved within the imagedir.
	 */
	if (strstr(rpath, rimagedir) != rpath) {
		fprintf(stderr, "imageid %s: realpath (%s) bad\n",
			imageid, rpath);
		return NULL;
	}

	if (debug)
		fprintf(stderr, "imageid %s: path '%s' resolved to '%s'\n",
			imageid, path, rpath);

	return mystrdup(rpath);
}

/*
 * Account for differences between BSD and Linux realpath.
 * In particular, BSD realpath() apparently doesn't even test the
 * final component.  It will return success even if the final component
 * doesn't exist. Settle on the Linux behavior.
 */
static char *
myrealpath(char *path, char rpath[PATH_MAX])
{
	char *rv;

	rv = realpath(path, rpath);
#ifndef linux
	if (rv != NULL) {
		struct stat sb;
		/* also sets errno correctly */
		if (stat(path, &sb) < 0)
			rv = NULL;
	}
#endif
	return rv;
}
 
/*
 * XXX memory allocation functions that either return memory or abort.
 * We shouldn't run out of memory and don't want to check every return values.
 */
static void *
mymalloc(size_t size)
{
	void *ptr = malloc(size);
	if (ptr == NULL) {
		error("config_null: out of memory!");
		abort();
	}
	return ptr;
}

static void *
myrealloc(void *ptr, size_t size)
{
	void *nptr = realloc(ptr, size);
	if (nptr == NULL) {
		error("config_null: out of memory!");
		abort();
	}
	return nptr;
}

static char *
mystrdup(const char *str)
{
	char *nstr = strdup(str);
	if (nstr == NULL) {
		error("config_null: out of memory!");
		abort();
	}
	return nstr;
}

#else
struct config *
null_init(void)
{
	return NULL;
}
#endif
