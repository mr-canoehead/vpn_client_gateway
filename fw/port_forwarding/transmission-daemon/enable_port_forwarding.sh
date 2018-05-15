#!/bin/bash
# A simple helper script run via the openvpn 'up' directive.
# This script is needed because openvpn will not pass any data
# until all 'up' commmands have completed successfully.
/usr/local/bin/port_forwarding.sh --enable --updatetransmission --copyport &
exit 0
