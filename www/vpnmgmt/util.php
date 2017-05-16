<?php
function host_os_type()
{
        $uname = php_uname();
        if (stripos($uname, 'osmc') !== false) {
                $host_os_type = "osmc";
        }
        else
                if (stripos($uname, 'raspbian') !== false) {
                        $host_os_type = "raspbian";
                }
                else
                        if (stripos($uname, 'alpine') !== false) {
                                $host_os_type = "alpine";
                        }
                       else
                                if (stripos($uname, 'debian') !== false) {
                                        $host_os_type = "debian";
                                }
                                else
                                        $host_os_type = "other";
        return $host_os_type;
}

function stop_service($service_name)
{
	$result = shell_exec("sudo service $service_name stop");
	return $result;
}

function start_service($service_name)
{
	$result = shell_exec("sudo service $service_name start");
	return $result;
}

function disable_service($service_name)
{
	switch(host_os_type()){
		case "alpine":
			$disable_service_cmd = "sudo rc-update del $service_name";
			break;
		default:
			$disable_service_cmd = "sudo update-rc.d $service_name disable";
	}
	$result = shell_exec($disable_service_cmd);
	return $result;
}

function enable_service($service_name)
{
	switch(host_os_type()){
		case "alpine":
			$enable_service_cmd = "sudo rc-update add $service_name";
			break;
		default:
			$enable_service_cmd = "sudo update-rc.d $service_name enable";
	}
	$result = shell_exec($enable_service_cmd);
	return $result;
}

function save_fs_changes()
{
// some alpine systems use a ramdisk, this function performs a local backup to save filesystem changes to the physical disk

	if(host_os_type() == "alpine")
		$result = shell_exec("sudo lbu commit");
	else
		$result = NULL;
	return $result;
}

function reboot()
{
	switch(host_os_type()){
		case "alpine":
			$reboot_cmd = "sudo reboot";
			break;
		default:
			$reboot_cmd = "sudo shutdown -r now";
	}
	$result = shell_exec($reboot_cmd);
	return $result;
}

function shutdown()
{
	switch(host_os_type()){
		case "alpine":
			$shutdown_cmd = "sudo halt";
			break;
		default:
			$shutdown_cmd = "sudo shutdown -h now";
	}
	$result = shell_exec($shutdown_cmd);
	return $result;
}
?>
