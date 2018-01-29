<?php
$vpnserverinfo = simplexml_load_file('vpnmgmt/vpnservers.xml');
$countryinfo = simplexml_load_file('vpnmgmt/countryflags.xml');
$lastcountry= NULL;
foreach($vpnserverinfo->vpnservers->vpnserver as $vpnserver){
	$country = (string) $vpnserver->countryname;
	$servername = (string) $vpnserver->servername;
        $portstr = (string) $vpnserver->port;
	if ($country <> $lastcountry){
		echo "<br>";
		echo "<H3>" . $country . "</H3>";
		$lastcountry = $country;
       	}
        if ($portstr<>"") {
                $portparam="&port=" . $portstr;
        }
        else $portparam="";
       	if ($servername <> "none"){
		echo "<P><A HREF=\".?&vpnserver=" . $vpnserver->servername . $portparam . "\" onclick=\"show_changing_vpn_message();\">" . $vpnserver->servername . "</A></P>";
	}
	else{
		echo "<P><A HREF=\".?&vpnserver=" . $vpnserver->servername . $portparam . "\" onclick=\"show_changing_vpn_message();\">" . $vpnserver->servername . " (disable VPN)</A></P>";
	}
}
?>

