<?php
require_once('util.php');
// create disabled marker file
$result = shell_exec('touch vpnmgmt/vpn.disabled');
// stop openvpn service
$result = stop_service('openvpn');
// prevent openvpn from starting at boot
$result = disable_service('openvpn');
// remove any existing forwarding rules
$result = shell_exec('sudo iptables -F FORWARD');
// add forwarding rule for lan traffic
$result = shell_exec('sudo iptables -A FORWARD -j forward_rules_lan');
// disable killswitch (allow all outbound traffic over LAN)
$result = shell_exec('sudo iptables -F killswitch');
$result = shell_exec('sudo iptables -t filter -A killswitch -j killswitch_off');
// save iptables
$result = shell_exec("sudo su -c 'iptables-save > /etc/iptables/rules.v4'");
?>
