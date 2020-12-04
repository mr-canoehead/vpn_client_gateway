#!/bin/bash

#dnsmasq nameserver file
RESOLVCONF=/etc/dnsmasq-resolv.conf

### DNS servers to use when VPN is connected

SERVERS_VPN_CONNECTED=(198.18.0.1)

### DNS servers to use when VPN is not connected

#Cloudflare
SERVERS_VPN_DISCONNECTED=(1.1.1.1 1.0.0.1)

#Google
#SERVERS_VPN_DISCONNECTED=(8.8.8.8 8.8.4.4)

vpnstate="$1"
if [[ "$vpnstate" == "up" ]] ; then
        serverlist=${SERVERS_VPN_CONNECTED[@]}
else
        serverlist=${SERVERS_VPN_DISCONNECTED[@]}
fi
output=""
for s in ${serverlist[@]}; do
        output="${output}nameserver $s\n"
done
printf "$output" | tee > $RESOLVCONF
