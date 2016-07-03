<?php
$vpnserver=$_GET["vpnserver"];
//echo "VPN server: $vpnserver\n";
	$hostname = explode(".", $vpnserver);
	$subdomain = $hostname[0];
	$domain = $hostname[1];
	$tld = $hostname[2];
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
			else if ($line_tokens[0] === "ca" && $domain === "nordvpn"){
				$serverconf .= "ca " . $subdomain . "_" . $domain . "_" . $tld . "_ca.crt " . "\n";
			}
			else if ($line_tokens[0] === "tls-auth" && $domain === "nordvpn"){
				$serverconf .= "tls-auth " . $subdomain . "_" . $domain . "_" . $tld . "_tls.key " . "\n";
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
