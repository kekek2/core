#!/bin/sh

MASK=$1
DEST=$2

echo -n "" > ${DEST}

[ "${MASK}" = "" ] && echo "No target" && exit 1;

[ ! -f ${MASK} ] && echo "No logfile" && exit 1;

for FILE in $( ls -r ${MASK}* ); do
    GZ=`file ${FILE} | grep -c gzip`
    if [ "${GZ}" = "1" ]; then
	zcat ${FILE} >> ${DEST}
    else
	cat ${FILE} >> ${DEST}
    fi
done;
