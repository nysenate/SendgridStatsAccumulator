#!/bin/bash
#
# statserver.sh - Worker script for load testing the server
#
# Project: BluebirdCRM
# Author: Graylin Kim
# Organization: New York State Senate
# Date: 2011-12-02
# Revised: 2011-12-02
#
prog=`basename $0`
bin_dir=`dirname $0`
root_dir=$bin_dir/../
config_file=$root_dir/config.ini
tests_file=$root_dir/resources/tests.ini

usage () {
  echo "Usage: $prog user requests"
}

if [ ! $# -eq 2 ]; then
  usage
  exit 1
fi

worker="Worker $1"; shift;
count=$1; shift;

# Load up the test configuration section
eval `sed -e 's/[[:space:]]*\=[[:space:]]*/=/g' \
    -e 's/;.*$//' \
    -e 's/[[:space:]]*$//' \
    -e 's/^[[:space:]]*//' \
    -e "s/^\(.*\)=\([^\"']*\)$/\1=\"\2\"/" \
   < $config_file \
    | sed -n -e "/^\[test\]/,/^\s*\[/{/^[^;].*\=.*/p;}"`

tests=(`cat $tests_file | grep '\[[A-Za-z0-9_ \-]\+\]' | sed -e 's/^\[\([A-Za-z0-9_ \-]\+\)\].*/\1/' | sed -e ':a;N;$!ba;s/\n/ /g'`)
for ((i=0; i<${#tests[*]}; i++)); do
    args=`sed \
        -e 's/[[:space:]]*\=[[:space:]]*/=/g' \
        -e 's/;.*$//' \
        -e 's/[[:space:]]*$//' \
        -e 's/^[[:space:]]*//' \
        -e "s/^\(.*\)=\([^\"']*\)$/-d \1=\2/" \
       < $tests_file | sed -n -e "/^\[${tests[$i]}\]/,/^\s*\[/{/^[^;].*\=.*/p;}" | sed ':a;N;$!ba;s/\n/ /g'`
    urls[$i]="curl -i -s --user $user:$pass --url $url $args"
done

for ((i=0; i<$count; i++)); do
    index=`expr $RANDOM % 9`
    output=`${urls[$index]} | grep "HTTP/1.1" | sed -e "s/HTTP\/1.1 \([0-9]\+\).*/\1/"`
    printf "[$worker] Running %-15s: %s\n" "${tests[$index]}" "$output"
done
