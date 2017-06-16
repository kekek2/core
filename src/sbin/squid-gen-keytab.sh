#!/bin/sh

KEYTAB=/usr/local/etc/squid/squid.keytab
PASS_TMP=/tmp/__tmp_kerb_pass

while getopts :d:n:k:e:b:u:p: name
do
    case $name in
        d) DOMAIN="$OPTARG" ;;			# aka tingnet.local
        n) PRINCIPAL="$OPTARG" ;;		# aka HTTP/TING
	k) KERB_COMPUTER_NAME="$OPTARG" ;;	# aka TING-K
	e) ENCTYPES="$OPTARG" ;;
	b) BASENAME="$OPTARG" ;;
	u) USERNAME="$OPTARG" ;;		# LDAP admin username
	p) PASSWORD="$OPTARG" ;;		# LDAP admin password
    esac
done

[ "$USERNAME" == "" ] && echo "No administrator account name" && exit 0;
[ "$PASSWORD" == "" ] && echo "No administrator account password" && exit 0;
[ "$BASENAME" == "" ] && BASENAME="CN=Computers";
[ "$PRINCIPAL" == "" ] && echo "No principal name" && exit 0;
[ "$DOMAIN" == "" ] && echo "No domain name" && exit 0;
[ "$KERB_COMPUTER_NAME" == "" ] && echo "No Kerberos name for host" && exit 0;
[ "$ENCTYPES" == "2008" ] && ENCTYPES_PARAM="--enctypes 28";


PASSWORD="${PASSWORD%\'}"
echo "${PASSWORD}" | sed 's/\\//g' > ${PASS_TMP}

/usr/bin/kinit --password-file=${PASS_TMP} ${USERNAME}
TICKET=$?
rm ${PASS_TMP}
[ $TICKET != 0 ] && echo "No ticket" && exit 0;

/usr/local/sbin/msktutil -c --verbose -b "${BASENAME}"  -s ${PRINCIPAL}.${DOMAIN} -k ${KEYTAB} --computer-name ${KERB_COMPUTER_NAME} --upn ${PRINCIPAL}.${DOMAIN} ${ENCTYPES_PARAM}

chmod +r ${KEYTAB}

/usr/bin/kdestroy