from flask import Flask, request, jsonify
from flask_socketio import SocketIO
import subprocess
import urllib2
from lxml import etree
import util
import os
import signal
import json
import syslog
import threading
import vpncgw_monitor
import time
from speedtest import Speedtest
import pathlib

OPENVPN_CONFIG_FILE = '/etc/openvpn/server.conf'
APP_PATH = '/opt/vpncgw/'
RUN_PATH = '/run/vpncgw/'
VPNCGW_STATUS_FILE = RUN_PATH + 'vpncgw_status.json'
IP_ADDR_GEO_SERVICE_URL = 'http://www.geoplugin.net/json.gp'
VPN_DISABLED_MARKER_FILE = APP_PATH + 'vpn.disabled'
MONITOR_DISABLED_MARKER_FILE = APP_PATH + 'no.monitor'
VPNSERVERSXML_FILE = APP_PATH + 'vpnservers.xml'
COUNTRYFLAGSXML_FILE = APP_PATH + 'countryflags.xml'
SPEEDTEST_LOCK_FILE = RUN_PATH + 'speedtest.lock'
SPEEDTEST_LOCK_FILE_MAX_AGE_SECONDS = 300
SPEEDTEST_RESULTS_FILE = RUN_PATH + 'speedtest.results'
SPEEDTEST_GETSERVERS_TIMEOUT = 15
SPEEDTEST_DOWNLOAD_TIMEOUT = 45
SPEEDTEST_UPLOAD_TIMEOUT = 45

application = Flask(__name__)
socketio = SocketIO(application)

class XMLFileData:
# data structure for storing an XML tree, it automatically re-parses
# the source XML file if that file is modified. Re-parsing occurs during
# the next query (xpath, find, findall).

	def load(self,filepath):
		self.xml_ok = True
		try:
			self.xmltree = etree.parse(filepath)
		except etree.XMLSyntaxError as err:
			self.xml_ok = False
			self.xml_parse_error = err
			errormsg = "Error parsing " + VPNSERVERSXML_FILE + " :" + str(self.xml_parse_error)
			syslog.syslog(errormsg)
		self.filepath = filepath
		self.filedatetime = os.path.getmtime(filepath)

	def stale(self):
		if self.filedatetime != os.path.getmtime(self.filepath):
			stale = True
		else:
			stale = False
		return stale

	def query(self,query_func,querystr,arg2 = None):
		if self.stale():
			self.reload()
		if self.xmltree is None:
			result = None
		else:
			if arg2 == None:
				result = query_func(querystr)
			else:
				result = query_func(querystr,arg2)
		return result

	def xpath(self,querystr,arg2 = None):
		return self.query(self.xmltree.xpath,querystr,arg2)

	def find(self,querystr,arg2 = None):
		return self.query(self.xmltree.find,querystr,arg2)

	def findall(self,querystr,arg2 = None):
		return self.query(self.xmltree.findall,querystr,arg2)

	def reload(self):
		if self.filepath is not None:
			self.load(self.filepath)

	def __init__(self,filepath = None):
		if filepath is not None:
			self.load(filepath)
		else:
			self.filepath = None
			self.xmltree = None
			self.filedatetime = None
			self.xml_ok = None
			self.xml_parse_error = None


# load vpnserver and countryflag data
vpnservers_data = XMLFileData(VPNSERVERSXML_FILE)
countryflags_data = XMLFileData(COUNTRYFLAGSXML_FILE)


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

def get_server_details(servername):
# finds vpnserver element in vpnservers.xml, returns dictionary containing details.
        vpnserver_details = {}
        servers = vpnservers_data.xpath(".//vpnserver[servername='" + servername + "']")
        if len(servers) > 0:
                countryname_elem = servers[0].find('countryname')
                regionname_elem = servers[0].find('regionname')
                serverport_elem = servers[0].find('serverport')
                cacertfile_elem = servers[0].find('cacertfile')
                tlsauthkeyfile_elem = servers[0].find('tlsauthkeyfile')

                if countryname_elem is not None:
                        vpnserver_details['countryname'] = countryname_elem.text
                else:
                        vpnserver_details['countryname'] = None
                if regionname_elem is not None:
                        vpnserver_details['regionname'] = regionname_elem.text
                else:
                        vpnserver_details['regionname'] = None
                if serverport_elem is not None:
                        vpnserver_details['serverport'] = serverport_elem.text
                else:
                        vpnserver_details['serverport'] = None
                if cacertfile_elem is not None:
                        vpnserver_details['cacertfile'] = cacertfile_elem.text
                else:
                        vpnserver_details['cacertfile'] = None
                if tlsauthkeyfile_elem is not None:
                        vpnserver_details['tlsauthkeyfile'] = tlsauthkeyfile_elem.text
                else:
                        vpnserver_details['tlsauthkeyfile'] = None
        return vpnserver_details

def get_country_flagfile(countryname):
	flagfile = None
	countrydetails = countryflags_data.xpath(".//country[name='" + countryname + "']")
	if len(countrydetails) > 0:
		flagfile = countrydetails[0].find('flagfile').text
	return flagfile


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
				server_details = get_server_details(servername)
				if server_details is not None:
					countryname = server_details['countryname']
					regionname = server_details['regionname']
					if countryname is not None:
						flagfile = get_country_flagfile(countryname)
					else:
						flagfile = None
        currentserver = {'servername':servername,'serverport':portnumber,'countryname':server_details['countryname'],'regionname':server_details['regionname'],'cacertfile':server_details['cacertfile'],'tlsauthkeyfile':server_details['tlsauthkeyfile'],'flagfile':flagfile,'enabled':enabled}
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
	CANCEL_SPEEDTEST = True
	clear_speedtest()
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
			# get ca certificate & tls-auth key filename elements for the new server
			server = vpnservers_data.find(".//vpnserver[servername='" + newserver + "']")
			server_details = get_server_details(newserver)
		        if server_details is None:
		                return_data = {'error':'server not found'}
				return return_data
		        else:
		                cacertfile = server_details['cacertfile']
		                tlsauthkeyfile = server_details['tlsauthkeyfile']
			if util.service_active('openvpn'):
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
				if len(line_tokens) > 0:
		                        if line_tokens[0] == 'remote':
	        	                        existingport = line_tokens[2]
		                                if (existingport is not None) and (existingport != '0'):
		                                        portnumber = existingport
						else:
							portnumber = newport
		                                serverconf += 'remote ' + newserver + ' ' + portnumber + '\n'
					elif line_tokens[0] == 'ca' and cacertfile is not None:
						serverconf += 'ca ' + cacertfile + '\n'
					elif line_tokens[0] == 'tls-auth' and tlsauthkeyfile is not None:
						serverconf += 'tls-auth ' + tlsauthkeyfile + '\n'
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
	CANCEL_SPEEDTEST = False
	return return_data

def get_server_list():
	servergroup = request.args.get('servergroup')
	if servergroup not in set(['basic', 'advanced']):
		return {'error': 'invalidservergroup'}
	else:
		countryflags = {}
		countries = countryflags_data.findall('./country')
		for c in countries:
			countryname = c.find('name').text
			flagfile = c.find('flagfile').text
			countryflags[countryname]=flagfile
		serverdetails={}
		basicservers = vpnservers_data.findall('./basicvpnservers/servername')
		vpnservers = vpnservers_data.find('./vpnservers')
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
	opener = urllib2.build_opener()
	opener.addheaders = [('User-Agent', 'Mozilla/5.0')]
	try:
		webreq = urllib2.urlopen(IP_ADDR_GEO_SERVICE_URL)
		geodata = json.loads(webreq.read())
	except:
		geodata = {'error':'Unable to connect to external service.'}
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

def speedtest_worker(sio):
        def run_thread(t, timeout_seconds):
                timeout_time = time.time() + timeout_seconds
                t.start()
                while t.isAlive() and time.time() < timeout_time:
                        time.sleep(0.25)
                if t.isAlive():
                        timed_out = True
                else:
                        timed_out = False
                return timed_out

	ABORT_SPEEDTEST = False
	servers = []
	s = Speedtest()
        try:
                sio.emit('speedtest','{ "progress": "getservers" }',broadcast=False)
        except IOError:
                pass
	# get a speedtest server
        t = threading.Thread(target=s.get_servers,args = (servers,))

	thread_timed_out = run_thread(t, SPEEDTEST_GETSERVERS_TIMEOUT)
	if thread_timed_out:
		try:
			sio.emit('speedtest', '{ "error": "Timeout while getting server list."}',broadcast=False)
			ABORT_SPEEDTEST = True
		except:
			pass

	if not ABORT_SPEEDTEST:
		t = threading.Thread(target=s.get_best_server)
		thread_timed_out = run_thread(t,SPEEDTEST_GETSERVERS_TIMEOUT)
		if thread_timed_out:
			try:
				sio.emit('speedtest', '{"error" : "Timeout while getting best server."}',broadcast=False)
				ABORT_SPEEDTEST = True
			except:
				pass

	if not ABORT_SPEEDTEST:
		try:
			sio.emit('speedtest','{ "progress": "download" }',broadcast=False)
		except IOError:
			pass
	        t = threading.Thread(target=s.download)
		thread_timed_out = run_thread(t,SPEEDTEST_DOWNLOAD_TIMEOUT)
		if thread_timed_out:
			try:
				sio.emit('speedtest', '{"error" : "Timeout while testing download speed."}',broadcast=False)
				ABORT_SPEEDTEST = True
			except:
				pass

	if not ABORT_SPEEDTEST:
		try:
			sio.emit('speedtest','{ "progress": "upload" }',broadcast=False)
		except IOError:
			pass
	        t = threading.Thread(target=s.upload)
		thread_timed_out = run_thread(t,SPEEDTEST_UPLOAD_TIMEOUT)
		if thread_timed_out:
			try:
				sio.emit('speedtest', '{"error" : "Timeout while testing upload speed."}',broadcast=False)
				ABORT_SPEEDTEST = True
			except:
				pass

	if not ABORT_SPEEDTEST:
		output = s.results.dict()
		speedtestResults = { }
		speedtestResults['results'] = output
		try:
			sio.emit('speedtest',json.dumps(speedtestResults),broadcast=True)
		except IOError:
			pass

		try:
			# save results to a file
			outfile = open(SPEEDTEST_RESULTS_FILE, 'w')
			json.dump(output, outfile)
		except IOError:
			syslog.log("Unable to create / write to speed test results file.")

	try:
		# remove lock file
		os.unlink(SPEEDTEST_LOCK_FILE)
	except IOError:
		syslog.log("Unable to remove speed test lock file")


def speedtest():
	speedtest_locked = False
	results_exist = os.path.isfile(SPEEDTEST_RESULTS_FILE)
	if results_exist:
		file_stat = os.stat(SPEEDTEST_RESULTS_FILE)
		results_age = time.time() - file_stat.st_mtime

	if os.path.isfile(SPEEDTEST_LOCK_FILE):
		file_stat = os.stat(SPEEDTEST_LOCK_FILE)
		lockfile_age = time.time() - file_stat.st_mtime
		if lockfile_age < SPEEDTEST_LOCK_FILE_MAX_AGE_SECONDS:
			speedtest_locked = True
		else:
			# stale lock file, ignore it and update file modification time
			pathlib.Path(SPEEDTEST_LOCK_FILE).touch()
	elif results_exist and results_age < SPEEDTEST_LOCK_FILE_MAX_AGE_SECONDS:
		speedtest_locked = True
	else:
		# create lock file
		pathlib.Path(SPEEDTEST_LOCK_FILE).touch()
	if speedtest_locked is not True:
		# spawn worker thread to run tests
                speedtestWorkerThread = threading.Thread(target=speedtest_worker,args = (socketio,))
                speedtestWorkerThread.start()
		output = {'response' : 'running' }
	else:
		# a test is in progress or has been run very recently
		output = {'response' : 'locked' }
		# add cached results if they exist
		if os.path.isfile(SPEEDTEST_RESULTS_FILE):
			try:
				resultsFile = open(SPEEDTEST_RESULTS_FILE,'r')
				previousResults = json.load(resultsFile)
			except:
				previousResults = {}
		else:
			previousResults = {}
		output['results'] = previousResults
	return output

def clear_speedtest():
	if os.path.isfile(SPEEDTEST_LOCK_FILE):
		try:
			os.unlink(SPEEDTEST_LOCK_FILE)
		except:
			syslog.log("Unable to remove speed test lock file.")
	if os.path.isfile(SPEEDTEST_RESULTS_FILE):
		try:
			os.unlink(SPEEDTEST_RESULTS_FILE)
		except IOError:
			syslog.log("Unable to remove speed test results file.")

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
		"speedtest" : speedtest,
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
