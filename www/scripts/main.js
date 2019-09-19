var ignoreUpdatesTime;
var socketioNamespace = '/vpncgw';
var socket = io(socketioNamespace);

// Event handlers for server sent data
socket.on('serverchange', (data) => serverChangeNotification(data));
socket.on('vpncgwstatus', (data) => statusUpdate(data));
socket.on('currentserver', (data) => populateCurrentServer(data));
socket.on('serverlist', (data) => populateServerList(data));
socket.on('iplocation', (data) => populateIPGeolocation(data));
socket.on('traceroute', (data) => populateTraceroute(data));
socket.on('syslog', (data) => populateSyslog(data));
socket.on('speedtest', (data) => populateSpeedtest(data));

function populateTraceroute(data) {
	var tracerouteInfoContainer = document.getElementById("TracerouteInfoContainer");
	var tracerouteData = document.getElementById("tracerouteData");
        e = document.createElement('pre');
        e.setAttribute('id','tracerouteData');
	e.innerHTML = data;
        tracerouteInfoContainer.appendChild(e);
	tracerouteInfoContainer.classList.remove("showLoadingIcon");
}

function show_traceroute() {
	show_element("TracerouteOverlay");
	var tracerouteInfoContainer = document.getElementById("TracerouteInfoContainer");
	tracerouteInfoContainer.classList.add("showLoadingIcon");
	delete_all_children(tracerouteInfoContainer);
	socket.emit('gettraceroute');
}

function populateServerList(data) {
	if (data['servergroup'] == 'basic'){
		populateBasicServers(data['serverlist']);
	}
	else{
		populateAdvancedServers(data['serverlist']);
	}
}

function serverChangeNotification(data) {
	clearStatus();
	get_current_vpn_server();
}

function setIcon(elementId,iconName) {
	ele = document.getElementById(elementId);
	ele.classList.remove('warnIcon');
	ele.classList.remove('okIcon');
	ele.classList.remove('disabledIcon');
	ele.classList.remove('loadingIcon');
	ele.classList.add(iconName);
}

function statusUpdate(status_json) {
	if (Date.now() <= ignoreUpdatesTime)
		return;
	var st = document.getElementsByClassName('StatusTable');
		for (i = 0; i < st.length; i++) {
			st[i].style.opacity='1';
		}
	document.getElementById('GatewayStatusOverlay').style.opacity='0';
	var vpnserver_cookie_val = getCookie("vpnserver");
	var vpnstate_cookie_val = (getCookie("vpnstate") == 'true');
	// process status updates sent from server via SocketIO message
	var status_data = JSON.parse(status_json);
	//console.log(status_data);
	var status_string = "";
	var gateway_status_ok = true;
	var vpn_status_ok = true;
	var internet_status_ok = true;
	var currentServer = status_data['currentserver']['servername'];
	var vpn_enabled = status_data['currentserver']['enabled'];
	if ((currentServer != vpnserver_cookie_val) || (vpn_enabled != vpnstate_cookie_val)){
		get_current_vpn_server();
	}

	service_status = status_data['openvpn']['service'];
	if (service_status == 'disabled'){
		setIcon('openvpnServiceStatusIcon','disabledIcon');
		document.getElementById('openvpnServiceStatus').innerText = 'disabled';
		setIcon('openvpnServiceStateIcon','disabledIcon');
		document.getElementById('openvpnServiceState').innerText = 'n/a';
	}
	else {
		if (service_status != 'active'){
			setIcon('openvpnServiceStatusIcon','warnIcon');
			setIcon('openvpnServiceStateIcon','disabledIcon');
			document.getElementById('openvpnServiceStatus').innerText = 'inactive';
			document.getElementById('openvpnServiceState').innerText = 'n/a';
			vpn_status_ok = false;
		}
		else {
			setIcon('openvpnServiceStatusIcon','okIcon');
			document.getElementById('openvpnServiceStatus').innerText = 'active';
			if ('state' in status_data['openvpn']) {
				if (status_data['openvpn']['state']['state_name'] != 'CONNECTED'){
					setIcon('openvpnServiceStateIcon','warnIcon');
					document.getElementById('openvpnServiceState').innerText = 'connecting...';
					vpn_status_ok = false;
				}
				else {
					setIcon('openvpnServiceStateIcon','okIcon');
					document.getElementById('openvpnServiceState').innerText = 'connected';
				}
			}
			else {
				setIcon('openvpnServiceStateIcon','disabledIcon');
				document.getElementById('openvpnServiceState').innerText = 'n/a';
			}
		}

	}
	if (status_data['system']['dns_ok'] != 'True'){
		setIcon('dnsStatusIcon','warnIcon');
		document.getElementById('dnsStatus').innerText = 'not responding';
		internet_status_ok = false;
	}
	else {
		setIcon('dnsStatusIcon','okIcon');
		document.getElementById('dnsStatus').innerText = 'ok';
	}

	if (status_data['system']['inet_ok'] != 'True')
		internet_status_ok = false;

	if (internet_status_ok == false) {
		setIcon('internetStatusIcon','warnIcon');
		document.getElementById('internetStatus').innerText = 'not connected';
	}
	else {
		document.getElementById('internetStatus').innerText = 'connected';
		setIcon('internetStatusIcon','okIcon');
	}

	document.getElementById('memoryUsageValue').innerText = Math.round(status_data['system']['mem_usage'],0);
	show_element('memoryUsageValue');
	show_element('memoryUsageUnit');

	document.getElementById('cpuUsageValue').innerText = Math.round(status_data['system']['cpu_load'],0);
	show_element('cpuUsageValue');
	show_element('cpuUsageUnit');

	document.getElementById('cpuTempValue').innerText = Math.round(status_data['system']['cpu_temp'],0);
	show_element('cpuTempValue');
	show_element('cpuTempUnit');
}

function clearStatus() {
	// ignore update messages for 5 seconds
	ignoreUpdatesTime = Date.now() + 5000;
	setIcon('internetStatusIcon','loadingIcon');
	setIcon('dnsStatusIcon','loadingIcon');
	setIcon('openvpnServiceStateIcon','loadingIcon');
	setIcon('openvpnServiceStatusIcon','loadingIcon');
	document.getElementById('cpuTempValue').innerText = '';
	document.getElementById('cpuUsageValue').innerText = '';
	document.getElementById('memoryUsageValue').innerText = '';
	document.getElementById('internetStatus').innerText = '';
	document.getElementById('dnsStatus').innerText = '';
	document.getElementById('openvpnServiceState').innerText = '';
	document.getElementById('openvpnServiceStatus').innerText = '';
	hide_element('cpuUsageUnit');
	hide_element('cpuTempUnit');
	hide_element('memoryUsageUnit');
	var st = document.getElementsByClassName('StatusTable');
		for (i = 0; i < st.length; i++) {
			st[i].style.opacity='0';
		}
	document.getElementById('GatewayStatusOverlay').style.opacity='1';
}

// force browser to load stylesheet (instead of using cached version),
// this is used for development purposes and should be removed in a deployed system.
document.getElementById('documentLink').setAttribute('href','/styles/index.css?v=' + Math.random().toString());

function sleep (time) {
	return new Promise((resolve) => setTimeout(resolve, time));
}

function setCookie(cname, cvalue, exdays) {
	var d = new Date();
	d.setTime(d.getTime() + (exdays*24*60*60*1000));
	var expires = "expires="+ d.toUTCString();
	document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}

function getCookie(cname) {
	var name = cname + "=";
	var decodedCookie = decodeURIComponent(document.cookie);
	var ca = decodedCookie.split(';');
	for(var i = 0; i < ca.length; i++) {
		var c = ca[i];
		while (c.charAt(0) == ' ') {
			c = c.substring(1);
		}
		if (c.indexOf(name) == 0) {
			return c.substring(name.length, c.length);
		}
	}
	return "";
}

function show_element(ele) {
	if (ele instanceof Array)
		for (i = 0; i < ele.length; i++)
			try {
				document.getElementById(ele[i]).style.display = "block";
			}
			catch(err) {
				console.log(err);
			}
	else if (typeof ele === 'string' || ele instanceof String) {
			try {
				document.getElementById(ele).style.display = "block";
			}
			catch(err) {
				console.log(err);
			}
	}
	else if (ele instanceof Element){
		try {
			ele.style.display = "block";
		}
		catch(err) {
			console.log(err);
		}
	}
}

function hide_element(ele) {
	if (ele instanceof Array)
		for (i = 0; i < ele.length; i++)
			try {
				document.getElementById(ele[i]).style.display = "none";
			}
			catch(err) {
				console.log(err);
			}


	else if (typeof ele === 'string' || ele instanceof String) {
		try {
			document.getElementById(ele).style.display = "none";
		}
		catch(err) {
			console.log(err);
		}
	}
	else if (ele instanceof Element){
		try {
			ele.style.display = "none";
		}
		catch(err) {
			console.log(err);
		}
	}
}

function delete_all_children(ele) {
// deletes all child elements of a node
	while (ele.firstChild)
	    ele.removeChild(ele.firstChild);
}

function show_tools(){
	show_element("Tools");
	hide_element(["VPNSection","Admin"]);
}

function show_admin(){
	show_element("Admin");
	hide_element(["Tools","VPNSection"]);
}

function show_changing_vpn_message(){
	show_element("ChangingVPNMessageOverlay");
	sleep(2000).then(() => {
		hide_element("ChangingVPNMessageOverlay");
	})
}

function show_vpn_change_message(){
	show_element("VPNChangeMessageOverlay");
	sleep(2000).then(() => {
		hide_element("VPNChangeMessageOverlay");
	})
}

function getStatus(){
	socket.emit('getvpncgwstatus');
}

function populateCurrentServer(serverinfo) {
	try {
		var vpnserver_cookie_val = getCookie("vpnserver");
		var vpnstate_cookie_val = (getCookie("vpnstate") == 'true');
		setCookie("vpnserver",serverinfo["servername"],1);
		setCookie("vpnstate",serverinfo["enabled"],1);
		headingtext = "Current VPN server:";
		if (serverinfo["enabled"] == false){
			headingtext += " none. All traffic originates from your ISP.";
			hide_element(["CurrentVPNFlag","DisableVPNMenuButton"]);
			show_element("EnableVPNMenuButton");
		}
		else{
			var currentVPNFlag = document.getElementById("CurrentVPNFlag");
			delete_all_children(currentVPNFlag);
			var currentVPNFlagTable = document.createElement("TABLE");
			currentVPNFlagTable.setAttribute("id","CurrentVPNFlagTable");
			var tb = document.createElement("tbody");
			var tr = document.createElement("TR");
			var td = document.createElement("TD");
			var img = document.createElement("img");
			img.setAttribute("id","CurrentVPNFlagImage");
			img.setAttribute("src","images/flags/" + serverinfo["flagfile"]);
			var location = document.createElement("p");
			location.setAttribute("id","ServerLocation");
			location.innerHTML = serverinfo["countryname"] + "<br>";
			if (serverinfo["regionname"] != null) location.innerHTML += serverinfo["regionname"];
			td.appendChild(img);
			td.appendChild(location);
			tr.appendChild(td);
			tr.appendChild(document.createElement("td"));
			tr.appendChild(document.createElement("td"));
			tb.appendChild(tr);
			currentVPNFlagTable.appendChild(tb);
			currentVPNFlag.appendChild(currentVPNFlagTable);
			headingtext += " " + serverinfo["servername"];
			show_element(["CurrentVPNFlag","DisableVPNMenuButton"]);
			hide_element("EnableVPNMenuButton");
		}
		document.getElementById("CurrentVPNServerHeading").innerHTML = headingtext;
		if ((vpnstate_cookie_val != serverinfo['enabled']) || (vpnserver_cookie_val != serverinfo['servername'])) {
			show_vpn_change_message();
		}
	} catch(err){
		// something bad happened...
	}
}

function get_current_vpn_server() {
	socket.emit('getcurrentserver');
}

function set_current_vpn_server_changing(changing_message) {
	var currentServerHeading = document.getElementById('CurrentVPNServerHeading');
	currentServerHeading.innerText = 'Current VPN server: '.concat(changing_message);
	hide_element('CurrentVPNFlag');
}

function change_vpn_server(servername,serverport) {
	setCookie("vpnserver",servername,1);
	//setCookie("vpnstate",serverinfo["enabled"],1);
	show_changing_vpn_message();
	set_current_vpn_server_changing('changing server...');
	request_data = {"request":"changeserver","servername": servername,"serverport": serverport};
	socket.emit('changeserver',request_data);
	clearStatus();
	return false;
}

function populateIPGeolocation(data) {
	function createRow(description,value) {
		var tr = document.createElement('TR');
		var td = document.createElement('TD');
		td.innerText = description;
		tr.appendChild(td);
		td = document.createElement('TD');
		td.innerText = value;
		tr.appendChild(td);
		return tr;
	}
	var ipgeo_json = data;
	var tableContainer = document.getElementById("IPInfoBoxTableContainer");
	var t = document.createElement('TABLE');
	t.setAttribute('class','ipinfotable');
	t.setAttribute('id','IPLocationTable');
	var tb = document.createElement('TBODY');
	tb.appendChild(createRow("Country Name", ipgeo_json["geoplugin_countryName"]));
	tb.appendChild(createRow("Country Code", ipgeo_json["geoplugin_countryCode"]));
	tb.appendChild(createRow("Region Name", ipgeo_json["geoplugin_regionName"]));
	tb.appendChild(createRow("Region Code", ipgeo_json["geoplugin_regionCode"]));
	tb.appendChild(createRow("City", ipgeo_json["geoplugin_city"]));
	tb.appendChild(createRow("Public IP Address", ipgeo_json["geoplugin_request"]));
	t.appendChild(tb);
	document.getElementById("IPInfoBox").classList.remove("showLoadingIcon");
	tableContainer.appendChild(t);
}

function show_iplocationinfo() {
	var tableContainer = document.getElementById("IPInfoBoxTableContainer");
	document.getElementById("IPInfoBox").classList.add("showLoadingIcon");
	delete_all_children(tableContainer);
	show_element("IPInfoOverlay");
	socket.emit('getiplocation');
}

function hide_iplocationinfo(){
	hide_element("IPInfoOverlay");
}

function populateSpeedtest(speedtestData) {
	var speedtestText = "";
	var e_status = document.getElementById("SpeedtestStatusMessage");
	var e_progress = document.getElementById("SpeedtestProgress");
	var e_results = document.getElementById("SpeedtestResults");
	var e_container = document.getElementById("SpeedtestInfoContainer");
	if (speedtestData.hasOwnProperty('progress')){
		switch(speedtestData.progress) {
			case "getservers":
				text = "Getting server list...\n";
				break;
			case "download":
				text = "Testing download speed...\n";
				break;
			case "upload":
				text = "Testing upload speed...";
				break;
			default:
				text = null;
		}
		if (text != null){
			e_progress.innerText += text;
		}
	}
	else if (speedtestData.hasOwnProperty('results')){
		e_container.classList.remove("showLoadingIcon");
		e_status.innerText = "Speed test results:";
		hide_element(e_progress);
		delete_all_children(e_results);
		e_results.appendChild(speedtestResultsHTML(speedtestData.results));
		show_element(e_results);
	}
	else if (speedtestData.hasOwnProperty('error')){
		e_container.classList.remove("showLoadingIcon");
		e_status.innerText = "Error:";
		e_progress.innerText = speedtestData.error;
	}
}

function speedtestResultsHTML(results) {
// create html elements for speedtest results

	function bps_to_Mbps(bps) {
		return bps / Math.pow(10,6);
	}

	const MS_PER_MINUTE = 60000;
	var resultsList = new Array()
	resultsList.push({label : 'Download Mbps', value : bps_to_Mbps(results.download).toFixed(2)});
	resultsList.push({label : 'Upload Mbps', value : bps_to_Mbps(results.upload).toFixed(2)});
	resultsList.push({label: 'Ping ms', value: (results.ping).toFixed(2)});
	resultsList.push({label: 'Host', value: results.server.host});
	resultsList.push({label: 'Sponsor', value: results.server.sponsor});
	resultsList.push({label: 'Country', value: results.server.country});
	resultsList.push({label: 'Timestamp', value: results.timestamp});
	var e_resultsTable = document.createElement('div');
	resultsList.forEach(function (arrayItem) {
		var e_resultRow = document.createElement('div');
		e_resultRow.classList.add("speedtestResultRow");
		var e_label = document.createElement('div');
		e_label.classList.add("speedtestLabelColumn");
		e_label.innerText = arrayItem.label;
		var e_value = document.createElement('div');
		e_value.classList.add("speedtestValueColumn");
		e_value.innerText = arrayItem.value;
		e_resultRow.appendChild(e_label);
		e_resultRow.appendChild(e_value);
		e_resultsTable.appendChild(e_resultRow);
	});
	return e_resultsTable;
}

function show_speedtest() {
	show_element("SpeedtestOverlay");
	var speedtestInfoContainer = document.getElementById("SpeedtestInfoContainer");
	speedtestInfoContainer.classList.add("showLoadingIcon");
	show_element("SpeedtestProgress");
	hide_element("SpeedtestResults");
	socket.emit('speedtest');
}

function hide_speedtest(){
	hide_element("SpeedtestOverlay");
}


function enable_status_updates() {
	clearInterval(statusInterval);
	statusInterval = setInterval(getStatus,1000);
	show_element("VPNStatusSection");
}

function disable_status_updates() {
	clearInterval(statusInterval);
	hide_element("VPNStatusSection");
}

function hide_traceroute(){
	hide_element("TracerouteOverlay");
}

function show_shutdown(){
	show_element("ShutdownOverlay");
}

function hide_shutdown(){
	hide_element("ShutdownOverlay");
}

function shutdown() {
	hide_element("ShutdownButtonTable");
	document.getElementById("ShutdownInfoContainer").innerHTML = "<P>Shutting down. Unplug after 60 seconds.<P>";
	socket.emit('shutdown');
}

function show_enable_vpn(){
	show_element("EnableVPNOverlay");
}

function hide_enable_vpn(){
	hide_element("EnableVPNOverlay");
}

function show_disable_vpn(){
	show_element("DisableVPNOverlay");
}

function hide_disable_vpn(){
	hide_element("DisableVPNOverlay");
}

function show_reboot(){
	show_element("RebootOverlay");
}

function hide_reboot(){
	hide_element("RebootOverlay");
}

function reboot() {
	hide_element("RebootButtonTable");
	socket.emit('reboot');
	var counter=90;
	var id;
	id = setInterval(function() {
    	counter--;
    	if(counter < 0) {
        	clearInterval(id);
		hide_element("RebootOverlay");
		show_basic();
    	}
	else {
		document.getElementById("RebootInfoContainer").innerHTML = "<P>Rebooting. Page will reload in " + counter.toString() + " seconds.<P>";
    	}
	}, 1000);
}

function enable_vpn() {
	hide_element("EnableVPNOverlay");
	show_changing_vpn_message();
	set_current_vpn_server_changing('enabling vpn...');
	socket.emit('enablevpn');
	sleep(2000).then(() => {
		show_vpn_change_message();
	})
	//updateVPNChangeTime();
	//enable_status_updates();
	clearStatus();
	show_basic();
}

function disable_vpn() {
	//disable_status_updates();
	clearStatus();
	hide_element("DisableVPNOverlay");
	show_changing_vpn_message();
	set_current_vpn_server_changing('disabling vpn...');
	socket.emit('disablevpn');
	sleep(2000).then(() => {
		show_basic();
		get_current_vpn_server();
	})
	sleep(2000).then(() => {
		show_vpn_change_message();
	})
	//updateVPNChangeTime();
}

function server_click(s,p) {
// callback function used for 'click' events
	return function() {
		change_vpn_server(s,p);
	}
}

function populateBasicServers(serverlist_json) {
	var ChooseVPNBasic = document.getElementById("ChooseVPNBasic");
	delete_all_children(ChooseVPNBasic);
	var servers = serverlist_json;
	var numservers = servers.length;
	var numcolumns = 3;
	var numrows = Math.ceil(numservers/numcolumns);
	var currentserver = 0;
	var serverTable = document.createElement("div");
	serverTable.setAttribute("id","basicServersTable");
	for (row = 1; row <= numrows; row++){
		var tr = document.createElement("div");
		if (row < numrows)
			tr.setAttribute("class","basicServersTableRow");
		else
			tr.setAttribute("class","basicServersTableLastRow");
		for (column = 1; column <= numcolumns; column++) {
			var cell = document.createElement("div");
			cell.setAttribute('class','basicServersTableCell');
			if (currentserver < numservers) {
				var server = servers[currentserver];
				var servername = server["servername"];
				var serverport = server["serverport"];
				var countryname = server["countryname"];
				var regionname = server["regionname"];
				if (regionname == null)
					regionname = "";
				var flag = document.createElement("INPUT");
				flag.setAttribute("class","basicServersFlag");
				flag.setAttribute("type","image");
				flag.setAttribute("src","images/flags/" + server["flagfile"]);
				flag.setAttribute("height","60%");
				flag.addEventListener("click", server_click(servername, serverport), false);
				var country_info = document.createElement('div');
				country_info.innerHTML = countryname + "<br>" + regionname;
				cell.appendChild(flag);
				cell.appendChild(country_info);
				currentserver++;
			}
		        tr.appendChild(cell);
			serverTable.appendChild(tr);
		}
	}
	//serverTable.appendChild(tbody);
	ChooseVPNBasic.appendChild(serverTable);
}

function get_basic_vpn_servers() {
	socket.emit('getserverlist', {'servergroup':'basic'});
}

function compare_servers(a,b) {
	if (a['countryname'] < b['countryname'])
		return -1;
	if (a['countryname'] > b['countryname'])
		return 1;
	return 0;
}

function populateAdvancedServers(serverlist_json) {
	var ChooseVPNAdvanced = document.getElementById("ChooseVPNAdvanced");
	delete_all_children(ChooseVPNAdvanced);
	var servers = serverlist_json;
	servers.sort(compare_servers);
	var numservers = servers.length;
	var currentserver = 0;
	lastCountry="";
	for (row = 1; row < numservers; row++){
		var server = servers[currentserver];
		//console.log(server);
		var country = server["countryname"];
		var region = server["regionname"];
		if (region == null)
			region = "";
		if (country != lastCountry) {
			lastCountry = country;
			var heading = document.createElement("h3");
			heading.innerHTML = country;
			ChooseVPNAdvanced.appendChild(heading);
		}
		var url = document.createElement("a");
		url.setAttribute("class","serverUrl");
		url.setAttribute("href","javascript:void(0);");
		url.addEventListener("click", server_click(server["servername"], server["serverport"]), false);
		url.innerHTML = server["servername"];
		ChooseVPNAdvanced.appendChild(url);
		if (region != "") {
			var r = document.createTextNode(" (" + region + ")");
			ChooseVPNAdvanced.appendChild(r);
		}
		linebreak = document.createElement("br");
		ChooseVPNAdvanced.appendChild(linebreak);
		currentserver++;
	}
}

function get_advanced_vpn_servers() {
	socket.emit('getserverlist', {'servergroup':'advanced'});
}

function populateSyslog(data) {
	syslogData = data;
	var syslogInfoContainer = document.getElementById("SyslogInfoContainer");
	var e = document.createElement('pre');
	e.setAttribute('id','syslogData');
	e.innerText = syslogData;
	syslogInfoContainer.classList.remove("showLoadingIcon");
	syslogInfoContainer.appendChild(e);
}

function show_syslog() {
	var syslogInfoContainer = document.getElementById("SyslogInfoContainer");
	delete_all_children(syslogInfoContainer);
	syslogInfoContainer.classList.add("showLoadingIcon");
	show_element("SyslogOverlay");
	socket.emit('getsyslog');
}

function hide_syslog() {
	hide_element("SyslogOverlay");
}

function show_basic() {
	get_current_vpn_server();
	get_basic_vpn_servers();
	show_element(["VPNSection","ChooseVPNBasic"]);
	hide_element(["Admin","Tools","ChooseVPNAdvanced"]);  
}

function show_advanced() {
	get_advanced_vpn_servers();
	show_element(["VPNSection","ChooseVPNAdvanced"]);
	hide_element(["Admin","Tools","ChooseVPNBasic"]);
}

function updateVPNChangeTime() {
	var vpnStatusHeading = document.getElementById("VPNStatusHeading");
	vpnStatusHeading.innerText = "VPN status: changing vpn...";
	var e = document.getElementById("VPNStatusSection");
	var att = document.createAttribute("vpnchangetime");
	att.value = Date.now();
	e.setAttributeNode(att);
}

function page_load() {
	setIcon('internetStatusIcon','loadingIcon');
	setIcon('dnsStatusIcon','loadingIcon');
	setIcon('openvpnServiceStatusIcon','loadingIcon');
	setIcon('openvpnServiceStateIcon','loadingIcon');
	get_basic_vpn_servers();
	get_current_vpn_server();

}
