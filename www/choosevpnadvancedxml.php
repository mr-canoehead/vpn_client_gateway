<?php
$vpnserverinfo = simplexml_load_file('vpnmgmt/vpnservers.xml');
$countryinfo = simplexml_load_file('vpnmgmt/countryflags.xml');
$lastcountry= NULL;
foreach($vpnserverinfo->vpnservers->vpnserver as $vpnserver){
	$country = (string) $vpnserver->countryname;
	$servername = (string) $vpnserver->servername;
        if ($servername <> "none"){
		if ($country <> $lastcountry){
			echo "<br>";
			echo "<H3>" . $country . "</H3>";
			$lastcountry = $country;
        	}
        	echo "<P><A HREF=\".?vpnexitpoint=" . $vpnserver->servername . "\">" . $vpnserver->servername . "</A></P>";
	}
}
?>

