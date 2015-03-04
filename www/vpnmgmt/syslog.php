<?php
echo "<pre id=\"SyslogText\">" . shell_exec('sudo su -c "grep -vE \'(iptables|cron|CRON)\' /var/log/syslog | tail --lines=1000"') . "</pre>";
?>
