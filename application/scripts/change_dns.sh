#!/bin/bash

#--------------------------------------------------------------------------------------
# This script is run each time the VPN state is changed (enable/disable/change server).
# It is called via 'up' and 'down' directives in the OpenVPN configuration file.
# The script updates the resolver configuration file with the appropriate DNS server
# settings, and can also run optional commands to flush the DNS cache and to restart
# the WiFi access point service (hostapd).
#
# When configuring your system you should uncomment the appropriate settings for
# SERVERS_VPN_CONNECTED and SERVERS_VPN_DISCONNECTED.
#--------------------------------------------------------------------------------------

#dnsmasq nameserver file
RESOLVCONF=/etc/dnsmasq-resolv.conf

### DNS servers to use when VPN is connected

##PIA DNS+Streaming+MACE
SERVERS_VPN_CONNECTED=(10.0.0.241)

##PIA DNS
#SERVERS_VPN_CONNECTED=(10.0.0.242)

##PIA DNS+Streaming
#SERVERS_VPN_CONNECTED=(10.0.0.243)

##PIA DNS+MACE
#SERVERS_VPN_CONNECTED=(10.0.0.244)

##Surfshark
#SERVERS_VPN_CONNECTED=(162.252.172.57 149.154.159.92)

##NordVPN
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


#-------------------------------------------------------------------------------
# The following are commands that may be useful to run when changing VPN servers
# or enabling/disabling the VPN.
# Uncomment the appropriate commands for your installation type.
#-------------------------------------------------------------------------------

#-----------------------------------------------------------------
# Clear the DNS resolver cache by restarting the resolver service.
#-----------------------------------------------------------------

## Standard installations without Pi-hole:
#systemctl restart dnsmasq

## Installations with Pi-hole:
#/usr/local/bin/pihole restartdns

#--------------------------------------------------------------------
# Restart the hostapd service; this forces WiFi clients to reconnect.
# This may help with clearing the DNS cache on client devices.
#--------------------------------------------------------------------

#systemctl restart hostapd
