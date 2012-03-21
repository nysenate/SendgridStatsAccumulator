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
  echo "Usage: $prog [help] [reset] [setup]"
}

if [ $# -eq 0 ]; then
  usage
  exit 1
fi

teardown=0
setup=0

while [ $# -gt 0 ]; do
  case "$1" in
    -h|--help|help) usage; exit 0 ;;
    setup) setup=1;;
    reset) teardown=1; setup=1;;
    *) echo "$prog: $1: Invalid option"; usage; exit 1;;
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


if [ $teardown -eq 1 ]; then
    echo "Dropping tables from existing database $host:$port/$name"
    mysql -h $host -P $port -u $user -p$pass $name -e "DROP TABLE IF EXISTS log; DROP TABLE IF EXISTS bounce, click, deferred, delivered, dropped, open, processed, spamreport, unsubscribe; DROP TABLE IF EXISTS event;"
fi


if [ $setup -eq 1 ]; then
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

