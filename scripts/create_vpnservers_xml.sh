#!/bin/bash
# this is a simple helper script that creates the vpnserver.xml file
#
# input: 
# CSV file with three columns, separated by ;
# 1. Country
# 2. City
# 3. Server 
#
# how to get the input:
# official list in newshosting control panel (member only)
# https://controlpanel.newshosting.com/customer/action/vpnserverlist.php
# Country codes must be changed to full names manually

filename="newshosting_vpn_servers.csv"

echo "<vpnserverinfo>"
echo "<basicvpnservers>"

# create basicvpnservers for all server
# users can always delete the ones they don't want
 while read -r line
 do 
   IFS=';' read -a line <<< "$line"
#   line=( ${line//;/ } )
   echo -e "\t<servername>${line[2]}</servername>"
 done < $filename

echo "</basicvpnservers>"
echo "<vpnservers>"

# create all servername entries
 while read -r line
 do
   IFS=';' read -a line <<< "$line"
   #echo "0: ${line[0]}, 1: ${line[1]}, 2: ${line[2]}"
   echo -e "\t<vpnserver>"
   echo -e "\t\t<servername>${line[2]}</servername>"
   echo -e "\t\t<countryname>${line[0]}</countryname>"
   echo -e "\t\t<regionname>${line[1]}</regionname>"
   echo -e "\t</vpnserver>"
 done < $filename

echo "</vpnservers>"
echo "</vpnserverinfo>"
