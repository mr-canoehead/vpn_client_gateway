<?php
require_once('util.php');
// remove disabled marker file
$result = unlink('vpnmgmt/vpn.disabled');
// set openvpn service to start automatically on boot
$result = enable_service('openvpn');
// remove any existing forwarding rules
$result = shell_exec('sudo iptables -F FORWARD');
// add rules for forwarding via VPN
$result = shell_exec('sudo iptables -A FORWARD -j forward_rules_vpn');
// enable killswitch (no outbound traffic if VPN is not connected)
$result = shell_exec('sudo iptables -F killswitch');
$result = shell_exec('sudo iptables -t filter -A killswitch -j killswitch_on');
// save iptables
$result = shell_exec("sudo su -c 'iptables-save > /etc/iptables/rules.v4'");
?>
