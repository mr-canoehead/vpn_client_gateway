<?php
$columns=3;
$counter=1;
echo "<TABLE class=\"ChoicesTable\">\n<TR>\n";
$vpnserverinfo = simplexml_load_file('vpnmgmt/vpnservers.xml');
$countryinfo = simplexml_load_file('vpnmgmt/countryflags.xml');
$basicvpnservers = $vpnserverinfo->xpath('/basicvpnservers/servername');
$maxservers = $vpnserverinfo->basicvpnservers->servername->count();
foreach($vpnserverinfo->basicvpnservers->servername as $servername){
	$servernamestr = (string) $servername;
	$xpathquery = '//vpnserver[servername="' . $servernamestr . '"]';
	$serverinfo = $vpnserverinfo->xpath($xpathquery);
	$countrynamestr = $serverinfo[0]->countryname;
	$xpathquery = '//country[name="' . $countrynamestr . '"]';
	$regionstr = $serverinfo[0]->regionname;
	$country = $countryinfo->xpath($xpathquery);
	$flagfilestr= (string) $country[0]->flagfile;
	echo "<TD>" . "\n";
	echo "<A HREF=\".?vpnserver=" . $servernamestr . "\" onclick=\"show_changing_vpn_message();\"><IMG width=40% SRC=\"images/flags/" . $flagfilestr . "\"/></A>" . "\n";
	echo "<P>" . $countrynamestr;
	if ($regionstr<>"") echo " (" . $regionstr . ")";
	echo "</P>\n";
	echo "</TD>" . "\n";
	if (($counter % $columns == 0) and ($counter < $maxservers)) echo "</TR>\n<TR>\n";
	$counter = $counter + 1;
}
echo "</TR>\n";
echo "</TABLE>\n";
?>
