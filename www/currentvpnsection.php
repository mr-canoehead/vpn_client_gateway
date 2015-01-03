<H2>Current VPN server:
<?php 
if(file_exists('/var/www/vpnmgmt/vpn.disabled')){
echo " none. All traffic will originate locally.</H2>";
echo "<script>$(\"#CurrentVPNFlag\").hide();</script>";
}
else{
       $file = file_get_contents('/etc/openvpn/server.conf');
        $matches = array();
        $t = preg_match('/remote (.*?) 1194/s', $file, $matches);
        print_r($matches[1]);
        echo "</H2>";
//        echo "<script>$(\"#CurrentVPNFlag\").hide();</script>";
}
?>
<div id="CurrentVPNFlag">
<?php 
$file = file_get_contents('/etc/openvpn/server.conf');
$matches = array();
$t = preg_match('/remote (.*?) 1194/s', $file, $matches);
$servername=$matches[1];
if(!file_exists('/var/www/vpnmgmt/vpn.disabled')){
	$vpnserverinfo = simplexml_load_file('vpnmgmt/vpnservers.xml');
	$countryinfo = simplexml_load_file('vpnmgmt/countryflags.xml');
	$xpathquery = '//vpnserver[servername="' . (string) $servername . '"]';
	$serverinfo = $vpnserverinfo->xpath($xpathquery);
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
	if(!file_exists('/var/www/vpnmgmt/vpn.disabled')){
      	echo "<P>" . $location . "</P>";
}
echo "</TD><TD></TD><TD></TD></TR></TABLE>";
}
?>
</div>

