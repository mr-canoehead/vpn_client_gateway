<?php
echo "Rebooting server...";
$result = shell_exec('sudo shutdown -r now');
?>
