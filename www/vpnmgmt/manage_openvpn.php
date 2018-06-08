<?php
require_once('util.php');

function get_current_vpn_server() {
		if (file_exists('vpn.disabled'))
			$enabled = False;
		else
			$enabled = True;
		if (file_exists('vpn.method.openvpn'))
		{
			$configfile = file('/etc/openvpn/server.conf');
                	$serverconf = "";
                	foreach($configfile as $line_num => $line){
                       		$line_tokens = preg_split("/[\s]+/",$line);
                       		if ($line_tokens[0] === "remote"){
					$servername = $line_tokens[1];
                               	 	$portnumber = $line_tokens[2];
					$method = "openvpn";
                        	}
                	}
		}
		else if (file_exists('vpn.method.pptp'))
		{
			$configfile = file('/etc/ppp/peers/server.conf');
                	$serverconf = "";
                	foreach($configfile as $line_num => $line){
                       		$line_tokens = preg_split("/[\s]+/",$line);
                        	if ($line_tokens[0] === "pty" && $line_tokens[1] === "\"pptp"){
					$servername = $line_tokens[2];
					$portnumber = '0';
					$method = "pptp";
                        	}
                	}
		}
		else
		{
			$servername = 'unknown';
			$portnumber = 'unknown';
		}
                $vpnserverinfo = simplexml_load_file('vpnservers.xml');
		$countryinfo = simplexml_load_file('countryflags.xml');

		$xpathquery = '//vpnserver[servername="' . $servername . '" and method="' . $method . '"]';
		$serverinfo = $vpnserverinfo->xpath($xpathquery);
		$countryname = (string) $serverinfo[0]->countryname;
		$method = (string) $serverinfo[0]->method;
		$regionname = (string) $serverinfo[0]->regionname;
                $xpathquery = '//country[name="' . $countryname . '"]';
                $country = $countryinfo->xpath($xpathquery);
                $flagurl = (string) $country[0]->flagfile;
		$currentserver = array("servername" => $servername, "port" => $portnumber,"enabled" => $enabled, "country" => $countryname, "method" => $method, "region" => $regionname, "flagurl" => $flagurl);
		return $currentserver;
}


function disablevpn() {
	// create disabled marker file
	$result = shell_exec('touch vpn.disabled');
	// stop vpn service
	$result = stop_service('openvpn');
	$result = stop_service('pptp');
	// prevent vpn from starting at boot
	$result = disable_service('openvpn');
	$result = disable_service('pptp');
	// remove any existing forwarding rules
	$result = shell_exec('sudo iptables -F FORWARD');
	// add forwarding rule for lan traffic
	$result = shell_exec('sudo iptables -A FORWARD -j forward_rules_lan');
	// disable killswitch (allow all outbound traffic over LAN)
	$result = shell_exec('sudo iptables -F killswitch');
	$result = shell_exec('sudo iptables -t filter -A killswitch -j killswitch_off');
	// save iptables
	$result = shell_exec("sudo su -c 'iptables-save > /etc/iptables/rules.v4'");
}


function enablevpn() {
	// remove disabled marker file
	$result = unlink('vpn.disabled');
	// set vpn services to start automatically on boot
	if (file_exists('vpn.method.openvpn'))	$result = enable_service('openvpn');
	if (file_exists('vpn.method.pptp'))	$result = enable_service('pptp');
	// remove any existing forwarding rules
	$result = shell_exec('sudo iptables -F FORWARD');
	// add rules for forwarding via VPN
	$result = shell_exec('sudo iptables -A FORWARD -j forward_rules_vpn');
	// enable killswitch (no outbound traffic if VPN is not connected)
	$result = shell_exec('sudo iptables -F killswitch');
	$result = shell_exec('sudo iptables -t filter -A killswitch -j killswitch_on');
	// save iptables
	$result = shell_exec("sudo su -c 'iptables-save > /etc/iptables/rules.v4'");
}

function setvpnmethodfile($method) {
	if (file_exists('vpn.method.openvpn'))	$result = unlink('vpn.method.openvpn');
	if (file_exists('vpn.method.pptp'))	$result = unlink('vpn.method.pptp');

	$result = shell_exec('touch vpn.method.' . $method);
}

$return_data = "";
$request_data = json_decode($_GET["data"],TRUE);
$request_action = $request_data["request"];
switch($request_action) {
	case "disablevpn":
		if (!file_exists('vpn.disabled')){
			disablevpn();
		}
		$return_data = get_current_vpn_server();
		break;
	case "enablevpn":
		if (file_exists('vpn.disabled')){
			enablevpn();
			// start openvpn service
			if (file_exists('vpn.method.openvpn'))	$result = start_service('openvpn');
			// start pptp service
			if (file_exists('vpn.method.pptp'))	$result = start_service('pptp');
		}
		$return_data = get_current_vpn_server();
		break;
	case "changeserver":
		$vpnserver=$request_data["servername"];
		if ($vpnserver === "none") {
			if (!file_exists('vpn.disabled')){
				disablevpn();
			}
			$return_data = get_current_vpn_server();
			break;
		}
		$port=$request_data["port"];
		$method=$request_data["method"];
		if (($vpnserver === "") or ($port === "") or ($method === ""))
			break;
		//echo "VPN server: $vpnserver\n";
		$hostname = explode(".", $vpnserver);
		$subdomain = $hostname[0];
		$domain = $hostname[1];
		$tld = $hostname[2];
		$result = shell_exec('sudo service openvpn status');
                if ((strpos($result,'Active: active') !== false) or (strpos($result,'is running') !== false) or (strpos($result,'started') !== false)){
			// echo "Stopping VPN service...\n";
			$result = stop_service('openvpn');
	                $result = disable_service('openvpn');
		}
		$result = shell_exec('sudo service pptp status');
                if ((strpos($result,'Active: active') !== false) or (strpos($result,'is running') !== false) or (strpos($result,'started') !== false)){
			// echo "Stopping VPN service...\n";
			$result = stop_service('pptp');
	                $result = disable_service('pptp');
		}
		if (file_exists('vpn.disabled')){
			enablevpn();
		}
		// modify /etc/openvpn/server.conf with new server name
		if ( $method === "openvpn" )
		{
			setvpnmethodfile($method);

			$configfile = file('/etc/openvpn/server.conf');
                	$serverconf = "";
               		foreach($configfile as $line_num => $line){
                        	$line_tokens = preg_split("/[\s]+/",$line);
                        	if ($line_tokens[0] === "remote"){
                                	$portnumber = $line_tokens[2];
					if(isset($port) and (strcmp($port,"0") != 0)){
						$portnumber=$port;
					}
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
			$result = enable_service('openvpn');
			$result = start_service('openvpn');
		}
		if ( $method === "pptp" )
		{
			setvpnmethodfile($method);

			$configfile = file('/etc/ppp/peers/server.conf');
                	$serverconf = "";
               		foreach($configfile as $line_num => $line){
				$line_tokens = preg_split("/[\s]+/",$line);
				//error_log( print_r( $line_tokens, true ) );
                        	if ($line_tokens[0] === "pty" && $line_tokens[1] === "\"pptp"){
                               		$serverconf .= $line_tokens[0] . " " . $line_tokens[1] . " " . $vpnserver;
					for ( $i = 3; $i < count($line_tokens); $i ++) {
						$serverconf .= " " . $line_tokens[$i];
					}
					$serverconf .= "\n";
				}
				else{
                                	$serverconf .= $line;
				}
                	}
                	file_put_contents("/etc/ppp/peers/server.conf", $serverconf);
			// start pptp service
			$result = enable_service('pptp');
			$result = start_service('pptp');
		}
		$return_data = get_current_vpn_server();
		break;
	case "getcurrentserver":
		$return_data = get_current_vpn_server();
		break;
	case "getvpncgwstatus":
		$json_string = file_get_contents('/tmp/vpncgw_status.json');
		$return_data = "<pre>" . $json_string . "</pre>";
		break;
	case "traceroute":
		$return_data = "<pre id=\"TracerouteText\">" . shell_exec('traceroute 8.8.8.8') . "</pre>";
		break;
	case "getiplocation":
		// create curl resource 
	        $ch = curl_init(); 
		// get our public IP address 
	        curl_setopt($ch, CURLOPT_URL, "bot.whatismyipaddress.com"); 
	        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0"); 
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	        $ipaddress = curl_exec($ch); 
		// ---
		// get geolocation info
	        curl_setopt($ch, CURLOPT_URL, "www.geoplugin.net/xml.gp?ip=".$ipaddress);
	        $response = curl_exec($ch);
		// ---
		// generate html output
	        $ipgeoxml = simplexml_load_string($response);
	        $return_data = "<TABLE class=\"ipinfotable\" id=\"IPLocationTable\">";
	        $return_data .= "<TR><TD>Country Name</TD><TD>" . $ipgeoxml->geoplugin_countryName . "</TD></TR>";
	        $return_data .= "<TR><TD>Country Code</TD><TD>" . $ipgeoxml->geoplugin_countryCode . "</TD></TR>";
	        $return_data .= "<TR><TD>Region Name</TD><TD>" . $ipgeoxml->geoplugin_regionName . "</TD></TR>";
	        $return_data .= "<TR><TD>Region Code</TD><TD>" . $ipgeoxml->geoplugin_regionCode . "</TD></TR>";
	        $return_data .= "<TR><TD>City</TD><TD>" . $ipgeoxml->geoplugin_city . "</TD></TR>";
	        $return_data .= "<TR><TD>Public IP</TD><TD>" . $ipaddress . "</TD></TR>";
	        $return_data .= "</TABLE>";
		break;
	case "reboot":
		$result = reboot();
		break;
	case "shutdown":
		$result = shutdown();
		break;
	case "getserverlist":
		$servergroup=$request_data["servergroup"];
                $serverlist = array();
                $vpnserverinfo = simplexml_load_file('vpnservers.xml');
		$countryinfo = simplexml_load_file('countryflags.xml');
		if ($servergroup == "basic") {
	                $servers = $vpnserverinfo->xpath('//basicvpnservers/servername');
		        foreach($servers as $servername){
        	                $xpathquery = '//vpnserver[servername="' . $servername . '"]';
	                        $serverinfo = $vpnserverinfo->xpath($xpathquery);
	                        $countrynamestr = $serverinfo[0]->countryname;
	                        $methodstr = $serverinfo[0]->method;
	                        $portstr = $serverinfo[0]->port;
	                        $xpathquery = '//country[name="' . $countrynamestr . '"]';
	                        $regionstr = $serverinfo[0]->regionname;
	                        $country = $countryinfo->xpath($xpathquery);
	                        $flagfilestr= (string) $country[0]->flagfile;
	                        $serverlist[] = array("servername" => (string) $servername, "port" => (int) $portstr, "country" => (string) $countrynamestr, "method" => (string) $methodstr, "region" => (string) $regionstr, "flagurl" => "images/flags/" . (string) $flagfilestr);
	                }
		}
		else {
			$servers = $vpnserverinfo->xpath('//vpnservers/vpnserver');
	                foreach($servers as $vpnserver){
				$servername = (string) $vpnserver-> servername;
	                        $countrynamestr = (string) $vpnserver-> countryname;
				$methodstr = (string) $vpnserver->method;
	                        $port = (int) $vpnserver->port;
	                        $xpathquery = '//country[name="' . $countrynamestr . '"]';
	                        $regionstr = (string) $vpnserver->regionname;
	                        $country = $countryinfo->xpath($xpathquery);
		                $flagfilestr = (string) $country[0]->flagfile;
	                        $serverlist[] = array("servername" => $servername, "port" => $port, "country" => $countrynamestr, "method" => $methodstr, "region" => $regionstr, "flagurl" => "images/flags/" . $flagfilestr);
			}
		}
		$return_data = $serverlist;
		break;
	case "getsyslog": 
		$return_data = "<pre id=\"SyslogText\">" . shell_exec('sudo su -c "grep -vE \'(iptables|cron|CRON)\' /var/log/syslog | tail --lines=1000"') . "</pre>";
		break;
	case "ajaxtest": 
		$return_data = ["response","hello from manage_openvpn.php!"];
		break;
}
if (host_os_type() == "alpine")
	$result = save_fs_changes();
$response_array = array( "request_data" => $request_data,"response_data" => $return_data);
print json_encode($response_array);
?>
