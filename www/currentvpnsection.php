<H2>Current VPN server:
<?php 
if(file_exists('/var/www/vpnmgmt/vpn.disabled')){
	echo " none. All traffic originates from your ISP.</H2>";
}
else{
	$file = file_get_contents('/etc/openvpn/server.conf');
        $matches = array();
        $t = preg_match('/remote (.*?) \d+/s', $file, $matches);
        print_r($matches[1]);
        echo "</H2>";
}
?>
<div id="CurrentVPNFlag">
<?php 
if(file_exists('/var/www/vpnmgmt/vpn.disabled')){
	$servername='none';
}
else{
	$file = file_get_contents('/etc/openvpn/server.conf');
	$matches = array();
	$t = preg_match('/remote (.*?) \d+/s', $file, $matches);
	$servername=$matches[1];
}
$vpnserverinfo = simplexml_load_file('vpnmgmt/vpnservers.xml');
$countryinfo = simplexml_load_file('vpnmgmt/countryflags.xml');
$xpathquery = '//vpnserver[servername="' . (string) $servername . '"]';
$serverinfo = $vpnserverinfo->xpath($xpathquery);
if(!empty($serverinfo)){
	$countrynamestr = $serverinfo[0]->countryname;
	$xpathquery = '//country[name="' . $countrynamestr . '"]';
	$regionstr = $serverinfo[0]->regionname;
	$country = $countryinfo->xpath($xpathquery);
	$flagfilestr= (string) $country[0]->flagfile;
	echo "<TABLE id=\"CurrentVPNFlagTable\">";
	echo "<TR><TD>";
	echo "<img id=\"CurrentVPNFlag\" width=40% src=\"images/flags/" . $flagfilestr . "\">";
	$location = $countrynamestr;
	if ($regionstr <> ""){
	       	$location = $location . " (" . $regionstr . ")";
	}
	echo "<P>" . $location . "</P>";
	echo "</TD><TD></TD><TD></TD></TR></TABLE>";
}
else{
//	echo "<P><img id=\"DisabledWarning\" width=50px src=\"images/Warning-icon-hi.png\"> VPN service is disabled.</P>";
}
?>
</div>

