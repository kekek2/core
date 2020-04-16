#!/bin/sh

if [ "$1" == "all" ]; then
    echo "flush all local netflow data"
    /usr/local/etc/rc.syshook.d/stop/75-flowd
    rm  /var/netflow/*.sqlite
    rm /var/log/flowd.log*
    /usr/local/etc/rc.syshook.d/start/25-flowd
else
    echo "not flushing local netflow data, provide all as parameter to do so"
fi
