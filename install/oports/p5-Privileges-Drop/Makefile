PORTNAME=	Privileges-Drop
PORTVERSION=	1.01
CATEGORIES=	misc perl5
MASTER_SITES=	${MASTER_SITE_PERL_CPAN}
MASTER_SITE_SUBDIR=	IO/TLBDK
PKGNAMEPREFIX=	p5-

MAINTAINER=	sklower@berkeley.edu
COMMENT=	"Privileges::Drop - Perl5 module to make it simple to drop all privileges."

# old school
PERL_CONFIGURE=	5.8.0+
PERL_MODBUILD=	yes

# new school
USES=		perl5
USE_PERL5=	configure modbuild

NO_STAGE=	yes

MAN3=		Privileges::Drop.3

.include <bsd.port.mk>
