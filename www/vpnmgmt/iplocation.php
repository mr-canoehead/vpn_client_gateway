<?php
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
        echo "<TABLE class=\"ipinfotable\" id=\"IPLocationTable\">";
        echo "<TR><TD>Country Name</TD><TD>" . $ipgeoxml->geoplugin_countryName . "</TD></TR>";
        echo "<TR><TD>Country Code</TD><TD>" . $ipgeoxml->geoplugin_countryCode . "</TD></TR>";
        echo "<TR><TD>Region Name</TD><TD>" . $ipgeoxml->geoplugin_regionName . "</TD></TR>";
        echo "<TR><TD>Region Code</TD><TD>" . $ipgeoxml->geoplugin_regionCode . "</TD></TR>";
        echo "<TR><TD>City</TD><TD>" . $ipgeoxml->geoplugin_city . "</TD></TR>";
        echo "<TR><TD>Public IP</TD><TD>" . $ipaddress . "</TD></TR>";
        echo "</TABLE>";
// ---
?>
