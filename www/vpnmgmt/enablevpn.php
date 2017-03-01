<?php
// echo "Enabling VPN service...";
// remove disabled marker file
$result = unlink('vpnmgmt/vpn.disabled');
// set openvpn service to start automatically on boot
$result = shell_exec('sudo update-rc.d openvpn enable');
// remove any existing nat postrouting rules
$result = shell_exec('sudo iptables -t nat -F POSTROUTING');
// add nat postrouting rule for tun0
$result = shell_exec('sudo iptables -t nat -A POSTROUTING -o tun0 -j MASQUERADE');
// add forwarding rules for VPN
$result = shell_exec('sudo iptables -F FORWARD');
$result = shell_exec('sudo iptables -A FORWARD -i tun+ -o eth0 -m state --state RELATED,ESTABLISHED -j ACCEPT');
$result = shell_exec('sudo iptables -A FORWARD -i eth0 -o tun+ -m comment --comment "LAN out to VPN" -j ACCEPT');
// enable killswitch (no outbound traffic if VPN is not connected)
$result = shell_exec('sudo iptables -F killswitch');
$result = shell_exec('sudo iptables -t filter -A killswitch -j RETURN');
// save iptables
$result = shell_exec("sudo su -c 'iptables-save > /etc/iptables/rules.v4'");
?>
