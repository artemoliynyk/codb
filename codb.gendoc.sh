#!/bin/bash

echo
echo "Running at `date`"
phpdoc -p -c codb.phpdoc.conf.xml -t doc
