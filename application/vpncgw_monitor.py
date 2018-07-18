#!/usr/bin/env python

# Monitors the openvpn service, writes status information to a JSON file. This script
# interacts with the openvpn management interface, the management interface must be
# enabled via the openvpn configuration file using the 'management' directive, e.g.
# 'management 0.0.0.0 7505'
# openvpn management interface will listen for connections from all interfaces on
# port 7505.
#
# This script supports the simple password authentication scheme used by the
# management interface. To enable password authentication, include the password
# filename on the 'management' directive line, e.g.:
# 'management 0.0.0.0 7505 managementpwdfile'
# The management interface will use the password stored in 'managementpwdfile' to
# authenticate connections.

import subprocess
import socket
import argparse
import time
import os.path
import sys
import signal
import re
import json
import psutil
import syslog


BUFFER_SIZE = 1024
MANAGEMENT_IP = "localhost"
MANAGEMENT_PORT = "7505"
OPENVPN_CONFIG_PATH = "/etc/openvpn/"
OPENVPN_CONFIG_FILE = "server.conf"
VPN_DISABLED_MARKER_FILE = "vpn.disabled"
PASSWORD_PROMPT="PASSWORD:"
PASSWORD_SUCCESS="SUCCESS:"
OUTPUT_FILE="/run/vpncgw/vpncgw_status.json"
BIG_LOOP_INTERVAL_MS=5000
MONITOR_DISABLED_MARKER_FILE = '/opt/vpncgw/no.monitor'
ONESHOT = False
PRINTSTATUS = False

# signal handler for ctr+break
def signal_handler(signal, frame):
        sys.exit(0)
signal.signal(signal.SIGINT, signal_handler)

def get_cpu_temp():
# gets cpu temp, returns temperature in degrees celcius
	if os.path.isfile('/sys/class/thermal/thermal_zone0/temp') == True:
		args=['cat','/sys/class/thermal/thermal_zone0/temp']
		try:
			p = subprocess.Popen(args, stdout=subprocess.PIPE)
			output, err = p.communicate()
			cpu_temp = round(float(output)/1000.0,1)
		except IOError as err:
			cpu_temp = 0
	else:
		cpu_temp = 0
	return cpu_temp

def get_mem_usage():
# gets local machine's memory usage, returns usage as a decimal value between 0 and 1
	mem_stats = psutil.virtual_memory()
	return mem_stats.percent

def get_cpu_load():
# returns cpu load as a decimal value between 0 and 1
	return psutil.cpu_percent(interval=1)

def check_dns():
# tests if DNS queries are working
	args=['dig','+short','+timeout=1','+tries=2','-4','www.example.com']
	p = subprocess.Popen(args, stdout=subprocess.PIPE)
	output, err = p.communicate()
	regex = '^[^;].*'
	m = re.compile(regex)
	matches = m.findall(output)
	if matches:
		return True
	else:
		return False

def check_inet():
# tests internet connectivity
	s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
	s.settimeout(1)
	inet_ok = False
	tries = 0
	while (inet_ok == False) and (tries < 3):
		inet_ok = True
		try:
			s.connect(('www.example.com', 80))
		except IOError as e:
			inet_ok = False
		tries += 1
	return inet_ok

def openvpn_stats_dict(status_string):
# parses an openvon 'status' response string, returns the values as a dictionary
	stats_dict = {}
	status_lines = status_string.splitlines()
	for l in status_lines:
		line_tokens = l.split(',')
		stat = line_tokens[0]
		if (len(line_tokens) > 1):
			valuestr = str(line_tokens[1])
			if stat == "TUN/TAP read bytes":
				stats_dict['tun_tap_read_bytes'] = valuestr
			elif stat == "TUN/TAP write bytes":
				stats_dict['tun_tap_write_bytes'] = valuestr
			elif stat == "TCP/UDP read bytes":
				stats_dict['tcp_udp_read_bytes'] = valuestr
			elif stat == "TCP/UDP write bytes":
				stats_dict['tcp_udp_write_bytes'] = valuestr
			elif stat == "Auth read bytes":
				stats_dict['auth_read_bytes'] = valuestr
			elif stat == "pre-compress bytes":
				stats_dict['pre_compress_bytes'] = valuestr
			elif stat == "post-compress bytes":
				stats_dict['post_compress_bytes'] = valuestr
			elif stat == "pre-decompress bytes":
				stats_dict['pre_decompress_bytes'] = valuestr
			elif stat == "post-decompress bytes":
				stats_dict['post_decompress_bytes'] = valuestr
	return stats_dict

def openvpn_state_dict(state_string):
# parses an openvpn 'state' response string, returns the values as a dictionary
	state_dict = {}
	state_values=state_string.split(",")
	if len(state_values) > 0:
		state_dict['udt'] = str(state_values[0])
	else: state_dict['udt'] = "0"
	if len(state_values) > 1:
		state_dict['state_name'] = state_values[1]
	else:
		state_dict['state_name'] = "unknown"
	if len(state_values) > 2:
		state_dict['state_description'] = state_values[2]
	if len(state_values) > 3:
		state_dict['tun_tap_local_ip'] = state_values[3]
	if len(state_values) > 4:
		state_dict['remote_server'] = state_values[4]
	return state_dict

def vpncgw_monitor_main():
	ap = argparse.ArgumentParser();
	ap.add_argument("-o","--oneshot", action="store_true",required=False,help='run once and exit')
	ap.add_argument("-p","--printstatus", action="store_true",required=False,help='print status JSON to stdout')
	args = vars(ap.parse_args())
	if args['oneshot']:
		ONESHOT = True
	else:
		ONESHOT = False
	if args['printstatus']:
		PRINTSTATUS = True

	# big loop
	while True:
		error = ""
		vpncgw_status_dict = {}
		vpncgw_status_dict['status_datetime'] = str(int(time.time()))
		loop_start_ms = int(round(time.time() * 1000))

		### get openvpn service status
		vpncgw_status_dict['openvpn']={}
		vpncgw_status_dict['currentserver']={}
		if os.path.isfile(VPN_DISABLED_MARKER_FILE) == True:
			vpncgw_status_dict['currentserver']['enabled'] = False
			vpncgw_status_dict['openvpn']['service'] = "disabled"
		else:
			vpncgw_status_dict['currentserver']['enabled'] = True
			args=['service','openvpn','status']
			p = subprocess.Popen(args, stdout=subprocess.PIPE)
			output, err = p.communicate()
			if len(output) > 2 and output.splitlines()[2].strip().split()[1].lower() == "active":
				vpncgw_status_dict['openvpn']['service'] = "active"
			else:
				vpncgw_status_dict['openvpn']['service'] = "inactive"
		###

		### get openvpn management interface config info
		mgt_if_password_required = False
		mgt_if_configured = False
		if os.access(OPENVPN_CONFIG_PATH + OPENVPN_CONFIG_FILE, os.R_OK) != True:
			error = "mgt_if_config_read_permission"
		else:
			try:
				with open(OPENVPN_CONFIG_PATH + OPENVPN_CONFIG_FILE, "r") as openvpn_config:
					for line in openvpn_config:
						if re.search('^\s*management\s+',line):
							mgt_if_configured = True
							management_config=line.strip().split()
							if len(management_config) > 1:
								MANAGEMENT_IP=management_config[1]
							if len(management_config) > 2:
								MANAGEMENT_PORT=management_config[2]
							if len(management_config) > 3:
								MANAGEMENT_IF_PASSWORD_FILE=management_config[3]
								mgt_if_password_required = True
						elif re.search('^\s*remote\s+',line):
							remote_info = line.strip().split()
							#syslog.syslog('remote info: ' + ''.join(remote_info))
							if len(remote_info) > 1:
								vpncgw_status_dict['currentserver']['servername'] = remote_info[1]
							if len(remote_info) > 2:
								vpncgw_status_dict['currentserver']['serverport'] = remote_info[2]
			except IOError as e:
				error = "mgt_if_config_read_error"

		if (not error) and (mgt_if_configured) and (mgt_if_password_required == True):
			if os.access(OPENVPN_CONFIG_PATH + MANAGEMENT_IF_PASSWORD_FILE, os.R_OK) != True:
				error = "mgt_if_password_file_read_permission"
			else:
				try:
					with open (OPENVPN_CONFIG_PATH + MANAGEMENT_IF_PASSWORD_FILE, "r") as pwdfile:
					    MANAGEMENT_IF_PASSWORD=pwdfile.readlines()[0]
				except IOError as e:
					error = "mgt_if_password_file_read_error"
		###
		if (not error) and (mgt_if_configured == True):
			s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
			s.settimeout(1)
			if vpncgw_status_dict['openvpn']['service'] == "active":
				try:
					s.connect((MANAGEMENT_IP, int(MANAGEMENT_PORT)))
				except IOError as e:
					error = "mgt_if_sock_connect"
				if not error:
					try: 
						data = s.recv(BUFFER_SIZE)
					except IOError as e:
						error = "mgt_if_sock_read"
					if PASSWORD_PROMPT in data:
						if mgt_if_password_required != True:
							error = "mgt_if_password_required"
						else:
							try:
								s.send(MANAGEMENT_IF_PASSWORD + "\n")
							except IOError as e:
								error = "mgt_if_sock_write"
							time.sleep(0.25)
							try:
								data = s.recv(BUFFER_SIZE)
							except IOError as e:
								error = "mgt_if_sock_read"
							if PASSWORD_SUCCESS not in data:
								error = "mgt_if_password_error"
					if not error:
						# discard 'welcome' message
						try:
							data = ""
							data = s.recv(BUFFER_SIZE)
						except:
							pass
						# get openvpn state
						try:
							s.send("state\n")
						except IOError as e:
							error = "mgt_if_sock_write"
						time.sleep(0.25)
						try:
							state_string=s.recv(BUFFER_SIZE).splitlines()[0]
						except IOError as e:
							error = "mgt_if_sock_read"
						if not error:
							vpncgw_status_dict['openvpn']['state'] = {}
							vpncgw_status_dict['openvpn']['state'] = openvpn_state_dict(state_string)
							if vpncgw_status_dict['openvpn']['state']['state_name'] == 'unknown':
								error = "mgt_if_unknown_state"
					if not error:
						# get openvpn statistics
						try:
							s.send("status\n")
						except IOError as e:
							error = "mgt_if_sock_write"
						time.sleep(0.25)
						try:
							status_string=s.recv(BUFFER_SIZE)
						except IOError as e:
							error = "mgt_if_sock_read"
						if not error:
							vpncgw_status_dict['openvpn']['statistics'] = {}
							vpncgw_status_dict['openvpn']['statistics'] = openvpn_stats_dict(status_string)
					try:
						# try to close management interface session nicely
						# must send both 'quit' and 'exit' (the command varies depending on openvpn state)
						s.send("quit\n")
						s.send("exit\n")
					except IOError as e:
						pass
			s.close()
		if error != "":
			vpncgw_status_dict['error'] = error
		vpncgw_status_dict['system'] = {}
		vpncgw_status_dict['system']['dns_ok'] = str(check_dns())
		vpncgw_status_dict['system']['inet_ok'] = str(check_inet())
		vpncgw_status_dict['system']['cpu_temp'] = str(get_cpu_temp())
		vpncgw_status_dict['system']['cpu_load'] = str(round(float(get_cpu_load()),2))
		vpncgw_status_dict['system']['mem_usage'] = str(round(float(get_mem_usage()),0))

		json_output = json.dumps(vpncgw_status_dict,indent=4,separators=(',',': '))

		### write to JSON output file
		try:
			output_file = open(OUTPUT_FILE, "w")
			output_file.write(json_output)
			output_file.close()
		except IOError as e:
			pass

		if PRINTSTATUS:
			print(json_output)

		if ONESHOT:
			break;
		### sleep off any remaining interval time
		remaining_ms = BIG_LOOP_INTERVAL_MS - (int(round(time.time() * 1000)) - loop_start_ms)
		if remaining_ms > 0:
			time.sleep(remaining_ms/1000.0)
		###

if __name__ == '__main__':
        vpncgw_monitor_main()


