<?php
// echo "Disabling VPN service...";
// create disabled marker file
$result = shell_exec('touch /var/www/vpnmgmt/vpn.disabled');
// stop openvpn service
$result = shell_exec('sudo service openvpn stop');
// prevent openvpn from starting at boot
$result = shell_exec('sudo update-rc.d openvpn disable');
// remove any existing nat postrouting rules
$result = shell_exec('sudo iptables -t nat -F POSTROUTING');
// add nat postrouting rule to allow forwarding via eth0
$result = shell_exec('sudo iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE');
// add forwarding rule for lan traffic
$result = shell_exec('sudo iptables -F FORWARD');
$result = shell_exec('sudo iptables -A FORWARD -i eth0 -o eth0 -m comment --comment "LAN forwarding" -j ACCEPT');
// disable killswitch (allow all outbound traffic over LAN)
$result = shell_exec('sudo iptables -F killswitch');
$result = shell_exec('sudo iptables -t filter -A killswitch -o eth0 -j ACCEPT');
// save iptables
$result = shell_exec("sudo su -c 'iptables-save > /etc/iptables/rules.v4'");
?>
