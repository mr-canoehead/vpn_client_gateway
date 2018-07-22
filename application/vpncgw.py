from flask import Flask, request, jsonify
from flask_socketio import SocketIO
import subprocess
import urllib2
import xml.etree.ElementTree as ET
import util
import os
import signal
import json
import syslog
import threading
import vpncgw_monitor
import time

OPENVPN_CONFIG_FILE = '/etc/openvpn/server.conf'
APP_PATH = '/opt/vpncgw/'
RUN_PATH = '/run/vpncgw/'
VPNCGW_STATUS_FILE = RUN_PATH + 'vpncgw_status.json'
IP_ADDR_GEO_SERVICE_URL = 'http://www.geoplugin.net/json.gp'
EXTERNAL_IP_ADDR_SERVICE_URL = 'http://bot.whatismyipaddress.com'
VPN_DISABLED_MARKER_FILE = APP_PATH + 'vpn.disabled'
MONITOR_DISABLED_MARKER_FILE = APP_PATH + 'no.monitor'

application = Flask(__name__)
socketio = SocketIO(application)

def shell_exec(cmd):
	output = subprocess.check_output(cmd.split())
	return output

def statusUpdater(sio):
	nextUpdateTime = 0
	while True:
		try:
			status_output = shell_exec('/usr/bin/python vpncgw_monitor.py --oneshot --printstatus')
		except:
			pass
		try:
			json_object = json.loads(status_output)
			valid_json = True
		except ValueError, e:
			valid_json = False
		if valid_json:
			json_object['source'] = 'statusUpdater'
			json_object['sourcePID'] = os.getpid()
			try:
				sio.emit('vpncgwstatus',json.dumps(json_object),broadcast=True)
			except IOError:
				pass
		time.sleep(5)

def get_current_server():
	if os.path.isfile(VPN_DISABLED_MARKER_FILE):
		enabled = False
        else:
		enabled = True
	with open(OPENVPN_CONFIG_FILE,'r') as f:
	    configfile = f.readlines()
        serverconf = ""
        for line in configfile:
		line_tokens = str.split(line)
                if len(line_tokens) > 0:
			if line_tokens[0] == 'remote':
        	        	servername = line_tokens[1]
	                        portnumber = line_tokens[2]
				tree = ET.parse('vpnservers.xml')
				for elem in tree.findall('./vpnservers/vpnserver'):
				        if elem.find('servername').text == servername:
				                countryname = elem.find('countryname').text
						regionname = elem.find('regionname').text
				tree = ET.parse('countryflags.xml')
				for elem in tree.findall('./country'):
					if elem.find('name').text == countryname:
						flagfile = elem.find('flagfile').text
        currentserver = {'servername':servername,'serverport':portnumber,'countryname':countryname,'regionname':regionname,'flagfile':flagfile,'enabled':enabled}
	return currentserver

def enable_vpn(startservice = True):
	if os.path.isfile(VPN_DISABLED_MARKER_FILE):
	        # remove disabled marker file
	        os.unlink(VPN_DISABLED_MARKER_FILE)
	        # set openvpn service to start automatically on boot
	        util.enable_service('openvpn')
	        # remove any existing forwarding rules
	        shell_exec('sudo iptables -F FORWARD')
	        # add rules for forwarding via VPN
	        shell_exec('sudo iptables -A FORWARD -j forward_rules_vpn')
	        # enable killswitch (no outbound traffic if VPN is not connected)
	        shell_exec('sudo iptables -F killswitch')
	        shell_exec('sudo iptables -t filter -A killswitch -j killswitch_on')
	        # save iptables
		os.system('sudo su -c \'iptables-save > /etc/iptables/rules.v4\'')
		if (startservice is not None) and (startservice == False):
			pass
		else:
			util.start_service('openvpn')
	socketio.emit('serverchange', None, broadcast=True)
	return get_current_server()

def disable_vpn():
        # create disabled marker file
	shell_exec('touch ' + VPN_DISABLED_MARKER_FILE)
	util.stop_service('openvpn')
        util.disable_service('openvpn')
	shell_exec('sudo iptables -F FORWARD')
        # add forwarding rule for lan traffic
        shell_exec('sudo iptables -A FORWARD -j forward_rules_lan')
        # disable killswitch (allow all outbound traffic over LAN)
        shell_exec('sudo iptables -F killswitch')
        shell_exec('sudo iptables -t filter -A killswitch -j killswitch_off')
        # save iptables
	os.system('sudo su -c \'iptables-save > /etc/iptables/rules.v4\'')
	socketio.emit('serverchange', None ,broadcast=True)

def change_server():
	syslog.syslog('Changing VPN server...')
	newserver = request.args.get('servername')
	newport = request.args.get('serverport')
	if newserver == 'none':
 		if not os.path.isfile(VPN_DISABLED_MARKER_FILE):
                	disable_vpn()
                        return_data = get_current_server()
        else:
		if (newserver is None) or (newport is None):
			return_data = {'error':'server and port required'}
		else:
			syslog.syslog('changing...')
			### NORDVPN CODE NEEDS UPDATING
	                # $hostname = explode(".", $vpnserver);
        	        # $subdomain = $hostname[0];
                	# $domain = $hostname[1];
	                # $tld = $hostname[2];
			###
        	        result = shell_exec('sudo service openvpn status')
			if ('Active: active' in result) or ('is running' in result) or ('started' in result):
	                        # echo "Stopping VPN service...\n"
				syslog.syslog('Stopping VPN service...')
        	                result = util.stop_service('openvpn')
			else:
				syslog.syslog('OpenVPN service is not running...')
	                if os.path.isfile(VPN_DISABLED_MARKER_FILE):
	                        enable_vpn(startservice = False)
			# modify /etc/openvpn/server.conf with new server name
			f = open('/etc/openvpn/server.conf','r+')
			configfile = f.readlines()
			serverconf = ""
			for line in configfile:
                        	line_tokens = str.split(line)
	                        if line_tokens[0] == 'remote':
        	                        existingport = line_tokens[2]
	                                if (existingport is not None) and (existingport != '0'):
	                                        portnumber = existingport
					else:
						portnumber = newport
	                                serverconf += 'remote ' + newserver + ' ' + portnumber + '\n'
				### NORDVPN CODE, NEEDS UPDATING
	                        # else if ($line_tokens[0] === "ca" && $domain === "nordvpn"){
        	                #         $serverconf .= "ca " . $subdomain . "_" . $domain . "_" . $tld . "_ca.crt " . "\n";
                	        # }
                        	# else if ($line_tokens[0] === "tls-auth" && $domain === "nordvpn"){
	                        #         $serverconf .= "tls-auth " . $subdomain . "_" . $domain . "_" . $tld . "_tls.key " . "\n";
        	                # }
				###
                	        else:
	                                serverconf += line
			f.seek(0)
			f.write(serverconf)
	                f.truncate()
			f.close()
                	# start openvpn service
			result = util.start_service('openvpn')
			return_data = get_current_server()
	socketio.emit('serverchange', None, broadcast=True)
	return return_data

def get_server_list():
	servergroup = request.args.get('servergroup')
	if servergroup not in set(['basic', 'advanced']):
		return {'error': 'invalidservergroup'}
	else:
		countryflags = {}
		tree = ET.parse('countryflags.xml')
		countries = tree.findall('./country')
		for c in countries:
			countryname = c.find('name').text
			flagfile = c.find('flagfile').text
			countryflags[countryname]=flagfile
		serverdetails={}
		tree = ET.parse('vpnservers.xml')
		basicservers = tree.findall('./basicvpnservers/servername')
		vpnservers = tree.find('./vpnservers')
		for s in vpnservers:
			servername = s.find('servername').text
			countryname = s.find('countryname').text
			regionname = s.find('regionname').text
			serverportelement = s.find('serverport')
			if serverportelement is not None:
				serverport = serverportelement.text
			else:
				serverport = None
			serverdetails[servername] = {}
			serverdetails[servername]['countryname'] = countryname
			serverdetails[servername]['regionname'] = regionname
			serverdetails[servername]['serverport'] = serverport
			serverdetails[servername]['flagfile'] = countryflags[countryname]
		if servergroup == 'basic':
			serverlist = []
			for s in basicservers:
				serverlist.append({'servername':s.text,'serverport':serverdetails[s.text]['serverport'],'countryname':serverdetails[s.text]['countryname'],'regionname':serverdetails[s.text]['regionname'],'flagfile':serverdetails[s.text]['flagfile']})
		else:
			serverlist = []
			for s in serverdetails:
				serverlist.append({'servername':s,
						   'serverport':serverdetails[s]['serverport'],
					           'countryname':serverdetails[s]['countryname'],
					           'regionname':serverdetails[s]['regionname'],
						   'flagfile':serverdetails[s]['flagfile']})
		return serverlist

def get_vpncgw_status():
	if os.path.isfile(VPNCGW_STATUS_FILE):
		try:
			with open (VPNCGW_STATUS_FILE, 'r') as statusfile:
				return_data=json.load(statusfile)
		except IOError:
			return_data = {'error':'statuserror'}
	else:
		return_data = {'error':'statuserror'}
	return return_data

def get_iplocation():
	webreq = urllib2.urlopen(EXTERNAL_IP_ADDR_SERVICE_URL)
	ipaddress = webreq.read()
	opener = urllib2.build_opener()
	opener.addheaders = [('User-Agent', 'Mozilla/5.0')]
	webreq = urllib2.urlopen(IP_ADDR_GEO_SERVICE_URL + '?ip=' + ipaddress)
	geodata = json.loads(webreq.read())
	return geodata

def traceroute():
	p = subprocess.Popen(['traceroute', '8.8.8.8'], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
	data, err = p.communicate()
	return data

def reboot():
	return util.reboot()

def shutdown():
	return util.shutdown()

def get_syslog():
	#output, err = shell_exec('sudo tail -100 /var/log/syslog')
	output = subprocess.check_output(['sudo','tail', '-1000', '/var/log/syslog'])
	return output

def ajax_test():
	return {'response':'Hello from the VPN Client Gateway Flask app!'}

def invalid_request():
	return {'error': 'invalidrequest'}

def function_map(request):
	func = None
	switcher = {
		"enablevpn" : enable_vpn,
		"disablevpn" : disable_vpn,
		"changeserver" : change_server,
		"getcurrentserver" : get_current_server,
		"getserverlist" : get_server_list,
	        "getvpncgwstatus" : get_vpncgw_status,
	        "getiplocation": get_iplocation,
	        "traceroute" : traceroute,
		"reboot" : reboot,
		"shutdown" : shutdown,
		"getsyslog" : get_syslog,
		"ajaxtest" : ajax_test,
	}
	# Get the function from switcher dictionary
	func = switcher.get(request)
	if func is None:
		func = invalid_request
	# return the function
	return func

@application.route("/vpncgw.ajax",methods = ['GET'])
def request_handler():
	return jsonify(function_map(request.args.get('request'))())

@application.before_first_request
def start_monitor_thread():
	syslog.syslog('Spawning updater thread...')
	if not os.path.isfile(MONITOR_DISABLED_MARKER_FILE):
		statusUpdaterThread = threading.Thread(target=statusUpdater,args = (socketio,))
		statusUpdaterThread.start()

if __name__ == '__main__':
	socketio.run(application)
