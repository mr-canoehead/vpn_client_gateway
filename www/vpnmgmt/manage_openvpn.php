<?php
$vpnserver=$_GET["vpnserver"];
//echo "VPN server: $vpnserver\n";
if (isset($vpnserver)){
	if ($vpnserver == "disable" || $vpnserver == "none"){
		include 'disablevpn.php';
	}
	else if ($vpnserver == "enable"){
		include 'enablevpn.php';
		// start openvpn service
		$result = shell_exec('sudo service openvpn start');
	}
	else {
		if (file_exists('/var/www/vpnmgmt/vpn.disabled')){
			include 'enablevpn.php';
		}
		$result = shell_exec('sudo service openvpn status');
		if (strpos($result,'is running') !== false){
			// echo "Stopping VPN service...\n";
			$result = shell_exec('sudo service openvpn stop');
		}
		// modify /etc/openvpn/server.conf with new server name
		$vpnshellcmd= 'sed "s/remote .*/remote ' . $vpnserver . ' 1194/" < /etc/openvpn/server.conf.template > /etc/openvpn/server.conf';
		$result = shell_exec($vpnshellcmd);
		// start openvpn service
		$result = shell_exec('sudo service openvpn start');
	}
}
?>
