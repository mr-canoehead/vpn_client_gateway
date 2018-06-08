<h1>Pi-Powered VPN Client Gateway</h1>
The VPN Client Gateway forwards network traffic through a Virtual Private Network connection. To facilitate geospoofing for media streaming, it includes a management web page that allows you to switch VPN servers by simply clicking on a country flag.

To build your own VPN Client Gateway you will need a Raspberry Pi (or similar lightweight Linux computer) and a [Private Internet Access](https://www.privateinternetaccess.com), [PureVPN](https://www.purevpn.com/), [Newshosting](https://www.newshosting.com/), or [NordVPN](https://www.nordvpn.com/) VPN account.

The project files can be download from the [Releases page](https://github.com/mr-canoehead/vpn_client_gateway/releases) or by [downloading the repository zip file](https://github.com/mr-canoehead/vpn_client_gateway/archive/master.zip).

The [Installation Guide](https://github.com/mr-canoehead/vpn_client_gateway/wiki/Installation-Guide) can be found in the project wiki.

Here is a screenshot showing the management web page:

![basic_screenshot](https://cloud.githubusercontent.com/assets/10369989/6698111/0762937e-ccb3-11e4-898e-b9be8fe8ef5e.png)

<h1>PPTP Notes (Jetty840)</h1>

This version of vpn_client_gateway adds on support for pptp in addition to the current openvpn.  PPTP is inherently insecure,
however vpn providers like purevpn require pptp to be used instead of openvpn for access to certain media.

<h2>Configuration</h2>

* Follow regular installation instructions for vpn_client_gateway

* sudo apt-get install pptp-linux

* sudo cp vpn_client_gateway-master/www/vpnmgmt/vpn_providers/purevpn/server.conf.pptp /etc/ppp/peers/server.conf (for other vpn providers ammend this file for your vpn provider)

* sudo vi /etc/ppp/peers/server.conf (Change $USERNAME and $PASSWORD to the userame/password for your vpn account)

* sudo cp vpn_client_gateway-master/www/vpnmgmt/vpn_providers/pptp.service /lib/systemd/system

* sudo chmod o+x /etc/ppp/peers

* sudo chmod 644 /etc/ppp/peers/server.conf

* sudo chown www-data:www-data /etc/ppp/peers/server.conf

* When executing fw-config to configure the firewall, ensure to say yes to enabling pptp

* To setup a server to use pptp, set <method>pptp</method> in your vpnservers.xml
