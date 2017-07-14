<!doctype html>
<META HTTP-EQUIV="CONTENT-TYPE" CONTENT="text/html; charset=utf-8">
<html lang="en">
<link rel="stylesheet" type="text/css" href="index.css" />
<script type="text/javascript" src="http://code.jquery.com/jquery-1.5.2.min.js"></script>
<?php include 'vpnmgmt/manage_openvpn.php';?>
<script type="text/javascript">
        function show_advanced() {
		$("#VPNSection").show();
		$("#Admin").hide();
		$("#Tools").hide();
                $("#ChooseVPNBasic").hide();
                $("#ChooseVPNAdvanced").show();  
        }
        function show_basic() {
		$("#VPNSection").show();
		$("#Admin").hide();
		$("#Tools").hide();
                $("#ChooseVPNBasic").show();
                $("#ChooseVPNAdvanced").hide();  
        }
	function show_tools(){
		$("#Tools").show();
		$("#VPNSection").hide();
		$("#Admin").hide();
	}
	function show_admin(){
		$("#Tools").hide();
		$("#VPNSection").hide();
		$("#Admin").show();
		<?php
		if(file_exists('vpnmgmt/vpn.disabled')){
                        echo "$(\"#EnableVPNMenuButton\").show();";
                        echo "$(\"#DisableVPNMenuButton\").hide();";
		}
		else{
                        echo "$(\"#EnableVPNMenuButton\").hide();";
                        echo "$(\"#DisableVPNMenuButton\").show();";
		}
		?>
	}

</script>

<script>
        function showIPGeolocation() {
               $("#IPInfoBoxTableContainer").html(null);
                $("#IPInfoOverlay").show();
                $.get("vpnmgmt/iplocation.php",function(data){
                        $("#IPInfoBoxTableContainer").html(data);
	                $("#IPInfoBoxTableContainer").css('background','white');
                });
        }
        function hide_iplocationinfo(){
                $("#IPInfoOverlay").hide();
                $("#IPInfoBoxTableContainer").css('background','');
        }

	function show_traceroute() {
		$("#TracerouteInfoContainer").html(null);
		$("#TracerouteOverlay").show();
                $.get("vpnmgmt/traceroute.php",function(data){
                        $("#TracerouteInfoContainer").html(data);
	                $("#TracerouteInfoContainer").css('background','white');
                });

	}

        function hide_traceroute(){
                $("#TracerouteOverlay").hide();
		$("#TracerouteInfoContainer").css('background-color','');

        }
	function show_shutdown(){
		$("#ShutdownOverlay").show();
	}
	function hide_shutdown(){
		$("#ShutdownOverlay").hide();
	}
        function shutdown() {
                $("#ShutdownButtonTable").hide();
		$("#ShutdownInfoContainer").html("<P>Shutting down. Unplug after 60 seconds.<P>");
                $.get("vpnmgmt/shutdown.php",function(data){
                });
        }

	function show_enable_vpn(){
		$("#EnableVPNOverlay").show();
	}
	function hide_enable_vpn(){
		$("#EnableVPNOverlay").hide();
	}
	function show_disable_vpn(){
		$("#DisableVPNOverlay").show();
	}
	function hide_disable_vpn(){
		$("#DisableVPNOverlay").hide();
	}
	function show_reboot(){
                $("#RebootOverlay").show();
        }
        function hide_reboot(){
                $("#RebootOverlay").hide();
        }
        function reboot() {
		$("#RebootButtonTable").hide();
                $.get("vpnmgmt/reboot.php",function(data){
                });
		var counter=90;
		var id;
		id = setInterval(function() {
    		counter--;
    		if(counter < 0) {
        		clearInterval(id);
			$("#RebootOverlay").hide();
			window.location.reload();
    		} else {
			$("#RebootInfoContainer").html("<P>Rebooting. Page will reload in " + counter.toString() + " seconds.<P>");
    		}
		}, 1000);
        }

	function enable_vpn() {
		$("#EnableVPNOverlay").hide();
		show_changing_vpn_message();
                $.get("vpnmgmt/enablevpn.php",function(data){
                });
		window.location.reload();
	}

	function disable_vpn() {
		$("#DisableVPNOverlay").hide();
		show_changing_vpn_message();
                $.get("vpnmgmt/disablevpn.php",function(data){
                });
		window.location.reload();
		$("#VPNChangeMessageOverlay").show().delay(5000).fadeOut('fast');
	}

        function show_syslog() {
		$("#SyslogInfoContainer").html(null);
		$("#SyslogOverlay").show();
		$.get("vpnmgmt/syslog.php",function(data){
                        $("#SyslogInfoContainer").html(data);
                });
        }
        function hide_syslog() {
		$("#SyslogOverlay").hide();
        }

	function show_changing_vpn_message(){
		$("#ChangingVPNMessageOverlay").show();
	}

</script>

<HEAD>
	<TITLE>VPN Client Gateway Management</TITLE>
	<link rel="shortcut icon" href="/images/favicon.ico" type="image/x-icon" />
</HEAD>

<div id="VPNChangeMessageOverlay" class="screenoverlay">
        <div id="VPNChangeMessage" class="vpnchangemessage">
                <H2>VPN Changed<H2>
                <P>Remember to restart media apps!<P>
        </div>
</div>

<div id="ChangingVPNMessageOverlay" class="screenoverlay">
        <div id="ChangingVPNMessage" class="changingvpnmessage">
                <H2>Changing VPN<H2>
                <P>This may take a few moments...<P>
        </div>
</div>

<div id="IPInfoOverlay" class="screenoverlay">
        <div id="IPInfoBox">
                <div id="IPInfoBoxTitle">
                        <H2>IP Geolocation<H2>
                </div>
                <div id="IPInfoBoxTableContainer">
                </div>
                <div id="ButtonContainer">
                        <button id="IPInfoCloseButton" onclick="hide_iplocationinfo();">Close</button>
                </div>
        </div>
</div>

<div id="TracerouteOverlay" class="screenoverlay">
        <div id="TracerouteInfoBox">
                <div id="TracerouteInfoBoxTitle">
                        <H2>Traceroute<H2>
                </div>
                <div id="TracerouteInfoContainer">
                </div>
		<div class="ButtonSpacer"></div>
                <div id="ButtonContainer">
                        <button id="TracerouteInfoCloseButton" onclick="hide_traceroute();">Close</button>
                </div>
        </div>
</div>

<div id="SyslogOverlay" class="screenoverlay">
        <div id="SyslogInfoBox">
                <div id="SyslogInfoBoxTitle">
                        <H2>syslog<H2>
                </div>
                <div id="SyslogInfoContainer">
                </div>
                <div class="ButtonSpacer"></div>
                <div id="ButtonContainer">
                        <button id="SyslogInfoCloseButton" onclick="hide_syslog();">Close</button>
                </div>
        </div>
</div>

<div id="DisableVPNOverlay" class="screenoverlay">
        <div id="DisableVPNInfoBox">
                <div id="DisableVPNInfoBoxTitle">
                        <H2>Disable VPN<H2>
                </div>
                <div id="DisableVPNInfoContainer">
			<P>Disabling VPN service. Network traffic will be forwarded via your normal ISP internet connection.<P>
                </div>
		<div class="ButtonSpacer"></div>
                <div id="ButtonContainer">
                        <table id="DisableVPNButtonTable">
			<tr>
				<td><button id="DisableVPNCancelButton" onclick="hide_disable_vpn();">Cancel</button></td>
				<td></td>
                        	<td><button id="DisableVPNContinueButton" onclick="hide_disable_vpn();show_changing_vpn_message();window.location.href='.?vpnserver=disable';">Continue</button></td>
			</tr>
			</table>
                </div>
        </div>
</div>

<div id="EnableVPNOverlay" class="screenoverlay">
        <div id="EnableVPNInfoBox">
                <div id="EnableVPNInfoBoxTitle">
                        <H2>Enable VPN<H2>
                </div>
                <div id="EnableVPNInfoContainer">
			<P>Enabling VPN service. Network traffic will be forwarded via your VPN connection.<P>
                </div>
		<div class="ButtonSpacer"></div>
                <div id="ButtonContainer">
                        <table id="EnableVPNButtonTable">
			<tr>
				<td><button id="EnablePNCancelButton" onclick="hide_enable_vpn();">Cancel</button></td>
				<td></td>
                        	<td><button id="EnableVPNContinueButton" onclick="hide_enable_vpn();show_changing_vpn_message();window.location.href='.?vpnserver=enable';">Continue</button></td>
			</tr>
			</table>
                </div>
        </div>
</div>

<div id="ShutdownOverlay" class="screenoverlay">
        <div id="ShutdownInfoBox">
                <div id="ShutdownInfoBoxTitle">
                        <H2>Shut Down VPN Client Gateway<H2>
                </div>
                <div id="ShutdownInfoContainer">
			<P>Warning: after shutting down the VPN Client Gateway server, it must be powered back on manually.<P>
                </div>
		<div class="ButtonSpacer"></div>
                <div id="ButtonContainer">
                        <table id="ShutdownButtonTable">
			<tr>
				<td><button id="ShutdownCancelButton" onclick="hide_shutdown();">Cancel</button></td>
				<td></td>
                        	<td><button id="ShutdownContinueButton" onclick="shutdown();">Continue</button></td>
			</tr>
			</table>
                </div>
        </div>
</div>

<div id="RebootOverlay" class="screenoverlay">
        <div id="RebootInfoBox">
                <div id="RebootInfoBoxTitle">
                        <H2>Reboot VPN Client Gateway<H2>
                </div>
                <div id="RebootInfoContainer">
                        <P>Rebooting will take approximately 90 seconds. All sessions will be terminated.<P>
                </div>
                <div class="ButtonSpacer"></div>
                <div id="ButtonContainer">
                        <table id="RebootButtonTable">
                        <tr>
                                <td><button id="RebootCancelButton" onclick="hide_reboot();">Cancel</button></td>
                                <td></td>
                                <td><button id="RebootContinueButton" onclick="reboot();">Continue</button></td>
                        </tr>
                        </table>
                </div>
        </div>
</div>

<BODY ID="body" LANG="en-CA" DIR="LTR">
<script>
<?php
$vpnserver=$_GET["vpnserver"];
if (isset($vpnserver)){
	//Display modal overlay
	echo "$(\"#VPNChangeMessageOverlay\").show().delay(5000).fadeOut('fast');";
        $full_url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $base_url = strtok($full_url,'?');
	//Upate URL to strip off parameters
	echo "window.history.pushState('','','$base_url');";
}
?>
</script>
<div class="header">
<H1>VPN Client Gateway Management</H1>
</div>
<div id="MainContainer">
	<div id="NavigationMenu" class="buttonmenu">
		<ul>
		<li><a href="javascript:void(0);" onclick="show_basic();">Basic</a></li>
		<div class="menubuttonspacer"></div>
		<li><a href="javascript:void(0);" onclick="show_advanced();">Advanced</a></li>
		<div class="menubuttonspacer"></div>
		<li><a href="javascript:void(0);" onclick="show_tools();">Tools</a></li>
		<div class="menubuttonspacer"></div>
		<li><a href="javascript:void(0);" onclick="show_admin();">Admin</a></li>
		</ul>
	</div>
	<div id="PageContainer">
		<div id="VPNSection">
		<div id="CurrentVPNSection">
			<script>$(function(){$("#CurrentVPNSection").load("currentvpnsection.php");}); </script> 
		</div>
		<div id="VPNChooser">
		<H2>Choose new VPN server:</H2>
			<div id="ChooseVPNBasic">
				<script>$(function(){$("#ChooseVPNBasic").load("choosevpnbasicxml.php");}); </script> 
			</div>
			<div id="ChooseVPNAdvanced">
				<script>$(function(){$("#ChooseVPNAdvanced").load("choosevpnadvancedxml.php");}); </script> 
			</div>
		</div>
		</div>
		<div id="Tools">
			<div id="ToolsMenu" class="buttonmenu">
			<ul>
			<li><a href="javascript:void(0);" onclick="showIPGeolocation();">Get IP address geolocation</a></li>
			<div class="menubuttonspacer"></div>
			<li><a href="javascript:void(0);" onclick="show_traceroute();">Run traceroute</a></li>
			<div class="menubuttonspacer"></div>
			<li><a href="javascript:void(0);" onclick="show_syslog();">View syslog</a></li>
			</ul>
			</div>
		</div>
		<div id="Admin">
			<div id="AdminMenu" class="buttonmenu">
			<ul>
                        <div id="EnableVPNMenuButton">
                                <li><a href="javascript:void();" onclick="show_enable_vpn();">Enable VPN</a></li>
                                <div class="menubuttonspacer"></div>
                        </div>
                        <div id="DisableVPNMenuButton">
                                <li><a href="javascript:void();" onclick="show_disable_vpn();">Disable VPN</a></li>
                                <div class="menubuttonspacer"></div>
                        </div>
			<li><a href="javascript:void();" onclick="show_reboot();">Reboot</a></li>
			<div class="menubuttonspacer"></div>
			<li><a href="javascript:void();" onclick="show_shutdown();">Shut down</a></li>
			</ul>
			</div>
		</div>
	</div>
</div>
<FOOTER>
<img id="OpenVPNLogo" class="openvpnlogo" src="images/openvpn_logo_powered_by.png" alt="OpenVPN logo"/>
</FOOTER>
</body>
</html>
