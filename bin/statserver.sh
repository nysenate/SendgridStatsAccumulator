#!/bin/bash
#
# statserver.sh - Central script for managing the stat server
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

usage () {
  echo "Usage: $prog [help] [reset] [setup] [test] [siege #workers #tasks]"
}

if [ $# -eq 0 ]; then
  usage
  exit 1
fi

drop=0
load=0
test=0
siege=0
workers=0
tasks=0

while [ $# -gt 0 ]; do
  case "$1" in
    -h|--help|help) usage; exit 0 ;;
    setup) load=1 ;;
    reset) drop=1; load=1 ;;
    test) test=1 ;;
    siege) siege=1; shift; workers=$1; shift; tasks=$1; ;;
    *) echo "$prog: $1: Invalid option"; usage; exit 1 ;;
  esac
  shift
done

# Magic Sed code to read ini variables into the shell
# http://mark.aufflick.com/blog/2007/11/08/parsing-ini-files-with-sed
eval `sed -e 's/[[:space:]]*\=[[:space:]]*/=/g' \
    -e 's/;.*$//' \
    -e 's/[[:space:]]*$//' \
    -e 's/^[[:space:]]*//' \
    -e "s/^\(.*\)=\([^\"']*\)$/\1=\"\2\"/" \
   < $config_file \
    | sed -n -e "/^\[database\]/,/^\s*\[/{/^[^;].*\=.*/p;}"`


if [ $drop -eq 1 ]; then
    echo "Dropping existing database $host:$port/$name"
    mysql -h $host -P $port -u $user -p$pass -e "DROP DATABASE IF EXISTS $name;"
fi

if [ $load -eq 1 ]; then
    echo "Creating database $host:$port/$name under user $user";
    mysql -h $host -P $port -u $user -p$pass -e "CREATE DATABASE $name; USE $name"
    echo "Loading schema from $bin_dir/../resources/schema.sql"
    mysql -h $host -P $port -u $user -p$pass $name < $bin_dir/../resources/schema.sql

    # Adding unique arguments to the events column now
    echo "Adding columns for unique arguments..."
    columns=`sed -e 's/[[:space:]]*\=[[:space:]]*/=/g' \
            -e 's/;.*$//' \
            -e 's/[[:space:]]*$//' \
            -e 's/^[[:space:]]*//' \
            -e "s/^\(.*\)=[\"']\?\([^\"']*\)[\"']\?$/ADD COLUMN \1 \2/" \
           < $config_file | sed -n -e "/^\[uniqueargs\]/,/^\s*\[/{/^ADD COLUMN.*/p;}"`
    mysql -h $host -P $port -u $user -p$pass $name -e "ALTER TABLE event $columns;"

    # Adding extra columns to the events table now
    echo "Adding extra columns for external manipulation..."
    columns=`sed -e 's/[[:space:]]*\=[[:space:]]*/=/g' \
            -e 's/;.*$//' \
            -e 's/[[:space:]]*$//' \
            -e 's/^[[:space:]]*//' \
            -e "s/^\(.*\)=[\"']\?\([^\"']*\)[\"']\?$/ADD COLUMN \1 \2/" \
           < $config_file | sed -n -e "/^\[extracolumns\]/,/^\s*\[/{/^ADD COLUMN.*/p;}"`
    mysql -h $host -P $port -u $user -p$pass $name -e "ALTER TABLE event $columns;"
fi

if [ $test -eq 1 ]; then
    # Load up the test configuration section
    eval `sed -e 's/[[:space:]]*\=[[:space:]]*/=/g' \
        -e 's/;.*$//' \
        -e 's/[[:space:]]*$//' \
        -e 's/^[[:space:]]*//' \
        -e "s/^\(.*\)=\([^\"']*\)$/\1=\"\2\"/" \
       < $config_file \
        | sed -n -e "/^\[test\]/,/^\s*\[/{/^[^;].*\=.*/p;}"`

    # Load up the testing data file
    tests_file=$root_dir/resources/tests.ini
    for test in `cat $tests_file | grep '\[[A-Za-z0-9_ \-]\+\]' | sed -e 's/^\[\([A-Za-z0-9_ \-]\+\)\].*/\1/' | sed ':a;N;$!ba;s/\n/ /g'`; do
        args=`sed \
            -e 's/[[:space:]]*\=[[:space:]]*/=/g' \
            -e 's/;.*$//' \
            -e 's/[[:space:]]*$//' \
            -e 's/^[[:space:]]*//' \
            -e "s/^\(.*\)=\([^\"']*\)$/-d \1=\2/" \
           < $tests_file | sed -n -e "/^\[$test\]/,/^\s*\[/{/^[^;].*\=.*/p;}"`
        output=`curl -i -s --user $user:$pass --url $url $args | grep "HTTP/1.1"`
       printf "%-15s %s\n" "$test" "$output"
    done
fi

if [ $siege -eq 1 ]; then
    for ((user=0; user<$workers; user++)); do
        $root_dir/bin/load_test.sh $user $tasks &
    done
    wait
    echo "Finished Sieging."
fi
