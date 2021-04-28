# Privado VPN

download configuration files
```
wget https://privado.io/apps/ovpn_configs.zip
unzip ovpn_configs.zip -d /home/pi/openvpn
```

extract `ca.crt` file
```
sudo chmod +x /home/pi/vpn_client_gateway/application/scripts/extract_ifile.sh
/home/pi/vpn_client_gateway/application/scripts/extract_ifile --tag=ca --input=/home/pi/openvpn/fra-002.ovpn --output=/home/pi/openvpn/ca.crt
sudo cp /home/pi/openvpn/ca.crt /etc/openvpn/client/ca.crt
```

copy configuration files
```
sudo cp /home/pi/vpn_client_gateway/application/vpn_providers/privado/vpncgw.conf /etc/openvpn/client/vpncgw.conf
sudo cp /home/pi/vpn_client_gateway/application/vpn_providers/privado/vpnservers.xml /etc/openvpn/client/vpnservers.xml
sudo cp /home/pi/vpn_client_gateway/application/vpn_providers/privado/change_dns.sh /etc/openvpn/client/change_dns.sh
```
