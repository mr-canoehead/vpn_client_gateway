#!/usr/bin/env bash
#
# Control port forwarding when using Private Internet Access VPN
#
# Usage:
#  ./port_forwarding.sh --enable --disable --updateddns --emailnotify --copyport --updatetransmission

programname=$0

function usage {
    cat <<EOF
usage: $programname --enable/disable --updateddns --emailnotify --copyport --updatetransmission
  --enable      set up port forwarding on the VPN tunnel
  --disable     disable port forwarding, removes all port forwarding firewall rules
  --updateddns  restart ddclient process to update ddns provider with current IP address
  --emailnotif used with --enable, sends email when port forwarding is set up
  --copyport    use the port assigned by PIA for the remote server port, useful for transmission-daemon
  --updatetransmission  send commands to transmission-daemon on remote machine
                        with --enable, commands transmission-daemon to use new
                        listening port and start seeding/downloading all torrents
                        with --disable, commands transmission-daemon to stop all
                        torrents
EOF
    exit 1
}

remote_server=192.168.1.55
remote_port=8080
transmission_rpc_port=9091
transmission_auth_user="transmission"
transmission_auth_password="transmission"
sender_email="<myemail>@gmail.com"
recipient_email="<recipientemail>@telus.net"
ddns_hostname="myhost.dynu.net"

function get_port_forward_assignment()
{
  local external_port_assignment=0
  if [ "$(uname)" == "Linux" ]; then
    client_id=`head -n 100 /dev/urandom | sha256sum | tr -d " -"`
  fi
  if [ "$(uname)" == "Darwin" ]; then
    client_id=`head -n 100 /dev/urandom | shasum -a 256 | tr -d " -"`
  fi

  local json=`curl "http://209.222.18.222:2000/?client_id=$client_id" 2>/dev/null`
  # get port returned by API
  local port_value="$(jq -e '.port' <<<$json)"
  if [ "$port_value" == "" ]; then
	external_port_assignment=0
  else
	external_port_assignment=$port_value
  fi
  echo "$external_port_assignment"
}
# handle options
updateddns=false
emailnotify=false
updatetransmission=false
copyport=false
action="none"
if [ $# -eq 0 ]; then
    usage
    exit 1
fi

while test $# -gt 0
do
    case "$1" in
	--enable) action="enable"
	    ;;
	--disable) action="disable"
            ;;
        --updateddns) updateddns=true
            ;;
        --emailnotify) emailnotify=true
            ;;
        --updatetransmission) updatetransmission=true
            ;;
	--copyport) copyport=true
	    ;;
	-h|--help|--usage) usage
            ;;
        --*) echo "Invalid option $1"
	     exit 0
            ;;
        *) echo "Invalid argument $1"
	     exit 0
            ;;
    esac
    shift
done
if [[ "$action" = "none" ]]; then
	exit 0
fi
if [[ "$action" = "enable" ]]; then
	# give openvpn a few seconds to establish the vpn tunnel
	sleep 5

	# request port forwarding and get port assignment
	external_port=$(get_port_forward_assignment)

	# if the external port has changed, update the firewall rules
	if [[ $external_port -gt 0 ]]; then
		logger "Port forwarding: new port=$external_port"
		if [[ "$copyport" = true ]] ; then
			remote_port=$external_port
		fi
		# delete all existing 'port forwarding' PREROUTING rules
		for i in $( sudo iptables -t nat --line-numbers -L | grep 'port forwarding' | grep ^[0-9] | awk '{ print $1 }' | tac ); do sudo iptables -t nat -D PREROUTING $i; done
		# delete all port forwarding rules in the custom chain
		sudo iptables -F port_forwarding_vpn
		# add PREROUTING rule with new port number
		sudo iptables -A PREROUTING -t nat -i tun+ -p tcp --dport $external_port -j DNAT --to $remote_server:$remote_port -m comment --comment "port forwarding http"
		# add port forwarding rule to custom chain
		sudo iptables -t filter -A port_forwarding_vpn -i tun+ -p tcp -d $remote_server --dport $remote_port -j ACCEPT
		if [ "$updateddns" = true ] ; then
			#restart DDNS client to update IP address
			sudo service ddclient restart
		fi
		if [ "$emailnotify" = true ] ; then
			# send email notification with new port number
			/usr/sbin/ssmtp $recipient_email <<EOF
To: $recipient_email
From: $sender_email
Subject: Port forwarding update

http://$ddns_hostname:$external_port
EOF
		fi
		if [ "$updatetransmission" = true ] ; then
			# update transmission-daemon with new port, tell it to start seeding/downloading torrents
			su -c "transmission-remote $remote_server:$transmission_rpc_port -p $external_port --auth $transmission_auth_user:$transmission_auth_password --torrent all --start" pi
		fi
	else
		logger "Port forwarding: no new port assignment."
	fi
elif [[ "$action" = "disable" ]] ; then
	# delete all existing 'port forwarding' PREROUTING rules
	for i in $( sudo iptables -t nat --line-numbers -L | grep 'port forwarding' | grep ^[0-9] | awk '{ print $1 }' | tac ); do sudo iptables -t nat -D PREROUTING $i; done
	# delete all port forwarding rules from the custom chain
	sudo iptables -F port_forwarding_vpn
	# add a RETURN so that processing resumes normally
	sudo iptables -t filter -A port_forwarding_vpn -j RETURN
	if [ "$updatetransmission" = true ] ; then
		# stop all torrents from downloading / seeding
		su -c "transmission-remote $remote_server:$transmission_rpc_port --auth $transmission_auth_user:$transmission_auth_password --torrent all --stop" pi
	fi
	logger "Port forwarding disabled."
fi
exit 0


