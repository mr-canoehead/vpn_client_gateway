#!/bin/sh
# script used in vpncgw.service unit file to kick-start the status updater thread
/bin/sleep 3
/usr/bin/curl -s 'http://localhost/vpncgw.ajax?request=getvpncgwstatus' > /dev/null
