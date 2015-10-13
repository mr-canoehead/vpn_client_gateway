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
		if ((strpos($result,'Active: active') !== false) or (strpos($result,'is running') !== false)){
			// echo "Stopping VPN service...\n";
			$result = shell_exec('sudo service openvpn stop');
		}
		// modify /etc/openvpn/server.conf with new server name
                $configfile = file('/etc/openvpn/server.conf');
                $serverconf = "";
                foreach($configfile as $line_num => $line){
                        $line_tokens = preg_split("/[\s]+/",$line);
                        if ($line_tokens[0] === "remote"){
                                $portnumber = $line_tokens[2];
                                $serverconf .= "remote " . $vpnserver . " " . $portnumber . "\n";
                        }
                        else{
                                $serverconf .= $line;
                        }
                }
                file_put_contents("/etc/openvpn/server.conf", $serverconf);
		// start openvpn service
		$result = shell_exec('sudo service openvpn start');
	}
}
?>
