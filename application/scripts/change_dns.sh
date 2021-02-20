#!/bin/bash

#dnsmasq nameserver file
RESOLVCONF=/etc/dnsmasq-resolv.conf

### DNS servers to use when VPN is connected

#PIA DNS+Streaming+MACE
#SERVERS_VPN_CONNECTED=(10.0.0.241)

#PIA DNS
#SERVERS_VPN_CONNECTED=(10.0.0.242)

#PIA DNS+Streaming
SERVERS_VPN_CONNECTED=(10.0.0.243)

#PIA DNS+MACE
#SERVERS_VPN_CONNECTED=(10.0.0.244)

#Surfshark
#SERVERS_VPN_CONNECTED=(162.252.172.57 149.154.159.92)

#NordVPN
#SERVERS_VPN_CONNECTED=(103.86.96.100 103.86.99.100)

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


