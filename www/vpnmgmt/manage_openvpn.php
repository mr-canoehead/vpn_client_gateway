<?php
$vpnexitpoint=$_GET["vpnexitpoint"];
if (isset($vpnexitpoint)){
if ($vpnexitpoint != "none"){
$vpnshellcmd= 'sed "s/remote .*/remote ' . $vpnexitpoint . ' 1194/" < /etc/openvpn/server.conf.template > /etc/openvpn/server.conf';
if (file_exists('/var/www/vpnmgmt/vpn.disabled')){
//	echo "<H2>VPN service is disabled. Enabling VPN service...</H2>";
	$result = unlink('/var/www/vpnmgmt/vpn.disabled');
	$result = shell_exec('sudo update-rc.d openvpn defaults');
}
$result = shell_exec('sudo service openvpn status');
if (strpos($result,'is running') !== false){
//	echo "Stopping VPN service...\n";
	$result = shell_exec('sudo service openvpn stop');
}
$result = shell_exec($vpnshellcmd);
$result = shell_exec('sudo service openvpn start');
}
else {
$result = shell_exec('sudo service openvpn status');
if (strpos($result,'is running') !== false){
//      echo "Stopping VPN service...\n";
        $result = shell_exec('sudo service openvpn stop');
}
else {
//      echo "Service already stopped.\n";
}

if (file_exists('/var/www/vpnmgmt/vpn.disabled')){
  //      echo "<H2>VPN service is already disabled.</H2>";
}
else{
    //    echo "<H2>VPN service is enabled. Disabling VPN service...</H2>";
        $result = shell_exec('echo > /var/www/vpnmgmt/vpn.disabled');
        $result = shell_exec('sudo update-rc.d openvpn remove');
}
}
function getServerAddress() {
if(array_key_exists('SERVER_ADDR', $_SERVER))
    return $_SERVER['SERVER_ADDR'];
elseif(array_key_exists('LOCAL_ADDR', $_SERVER))
    return $_SERVER['LOCAL_ADDR'];
elseif(array_key_exists('SERVER_NAME', $_SERVER))
    return gethostbyname($_SERVER['SERVER_NAME']);
else {
    // Running CLI
    if(stristr(PHP_OS, 'WIN')) {
        return gethostbyname(php_uname("n"));
    } else {
        $ifconfig = shell_exec('/sbin/ifconfig eth0');
        preg_match('/addr:([\d\.]+)/', $ifconfig, $match);
        return $match[1];
    }
  }
}
}
?>
