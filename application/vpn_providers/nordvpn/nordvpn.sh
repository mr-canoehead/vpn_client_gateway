#!/bin/bash

# Variables - NORDVPN Specific
certificates="http://downloads.nordcdn.com/configs/archives/certificates/servers.zip"
configuration="http://downloads.nordcdn.com/configs/archives/servers/ovpn.zip"
certdir="/tmp/certs/"
configdir="/tmp/config/"
xmlname="vpnservers.xml"
openvpndir="/etc/openvpn/client/nordvpn"
vpnmgmtdir="/opt/vpncgw"

mkdir -p $openvpndir
mkdir -p $vpnmgmtdir

if [ -x "$(command -v apt-get)" ]; then
        # Package Installer for required python components below
        PKG_MANAGER="apt-get"
        PKG_CACHE="/var/lib/apt/lists"
        UPDATE_PKG_CACHE="${PKG_MANAGER} update"
        PKG_UPDATE="${PKG_MANAGER} upgrade"
        PKG_INSTALL="${PKG_MANAGER} --yes --fix-missing install"
        # grep -c will return 1 retVal on 0 matches, block this throwing the set -e with an OR TRUE
        PKG_COUNT="${PKG_MANAGER} -s -o Debug::NoLocking=true upgrade | grep -c ^Inst || true"
        INSTALLER_DEPS=( python python-lxml python-pycountry )
        package_check_install() {
                dpkg-query -W -f='${Status}' "${1}" 2>/dev/null | grep -c "ok installed" || ${PKG_INSTALL} "${1}"
        }
else
        echo "OS distribution not supported, do you have Raspbian installed?"
        exit
fi

install_dependent_packages() {
        # Install packages passed in via argument array
        declare -a argArray1=("${!1}")

        for i in "${argArray1[@]}"; do
                echo -n ":::    Checking for $i..."
                package_check_install "${i}" &> /dev/null
                echo " installed!"
        done
}

createTempDir() {
        if [ -d "$1" ]; then
                echo " Directory exists, not overwriting"
        else
                mkdir -p $1
        fi
}

removeTempDir() {
        if [ -d "$1" ]; then
                rm -r $1
        else
                echo " Directory does not exist"
        fi
}

fileRetrieval() {
        # Download and decompress configuration files
        # Arguement 1 being the remote location and 2
        # being the local destination
        TMPFILE=`mktemp`
        #unzip -j option ignores subdirectories in the zip file
        wget -q "$1" -O $TMPFILE
        unzip -a -L -C -q -o -j $TMPFILE -d "$2"
        rm -r $TMPFILE
}

function createMAP() {
PYTHON_ARG="$1" python - <<END
# -*- coding: utf-8 -*-
# Requirements
import lxml.etree as xml
import pycountry
import glob
import os

def createXML(outfile):
        root = xml.Element("vpnserverinfo")
        basic = xml.Element("basicvpnservers")
        full = xml.Element("vpnservers")
        server = xml.Element("servername")
        root.append(basic)
        us_server = xml.SubElement(basic, "servername")
        us_server.text = ('us3581.nordvpn.com')
        de_server = xml.SubElement(basic, "servername")
        de_server.text = ('de524.nordvpn.com')
        fr_server = xml.SubElement(basic, "servername")
        fr_server.text = ('fr271.nordvpn.com')
        uk_server = xml.SubElement(basic, "servername")
        uk_server.text = ('uk1194.nordvpn.com')
        in_server = xml.SubElement(basic, "servername")
        in_server.text = ('in45.nordvpn.com')
        il_server = xml.SubElement(basic, "servername")
        il_server.text = ('il12.nordvpn.com')
        is_server = xml.SubElement(basic, "servername")
        is_server.text = ('is41.nordvpn.com')
        it_server = xml.SubElement(basic, "servername")
        it_server.text = ('it85.nordvpn.com')
        root.append(full)
        list = [os.path.basename(x) for x in sorted(glob.glob("/tmp/config/*udp*.*"))]
        for names in list:
                info = xml.Element("vpnserver")
                server = xml.SubElement(info, "servername")
                server.text = names.replace('.udp.ovpn','')
                short = names[:2].upper().replace('UK','GB')
                country = xml.SubElement(info, "countryname")
                country.text = pycountry.countries.get(alpha_2=short).name
		servername_underscored = server.text.replace(".","_")
		cacertfile = xml.SubElement(info, "cacertfile")
		cacertfile.text = 'nordvpn/' + servername_underscored + '_ca.crt'
		tlsauthkeyfile = xml.SubElement(info,"tlsauthkeyfile")
		tlsauthkeyfile.text = 'nordvpn/' + servername_underscored + '_tls.key'
                region = xml.SubElement(info, "regionname")
                full.append(info)
        print xml.tostring(root, pretty_print=True)
        output_file = open( '/tmp/config/vpnservers.xml', 'w' )
        output_file.write( '<?xml version="1.0"?>\n' )
        output_file.write(xml.tostring(root, pretty_print=True))
        output_file.close()

if __name__ == "__main__":
    createXML("/tmp/config/vpnservers.xml")

END
}

createTempDir ${configdir}
createTempDir ${certdir}
fileRetrieval ${certificates} ${certdir}
fileRetrieval ${configuration} ${configdir}
install_dependent_packages INSTALLER_DEPS[@]
createMAP ${configdir}

#Add datestamp checker for vpnserver.xml
cp ${configdir}/vpnservers.xml ${vpnmgmtdir}/vpnservers.xml
cp ${certdir}/*.* ${openvpndir}
# copy configs - not needed (debugging only)
#mkdir -p ${openvpndir}/config
#cp ${configdir}/*.* ${openvpndir}/config
removeTempDir ${configdir}
removeTempDir ${certdir}
