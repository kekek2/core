#!/bin/sh

REPO=$1
ARCHIVE=$2

rm -Rf /var/tmp/sets
mkdir -p $REPO
cd $REPO
tar -xzf $ARCHIVE
