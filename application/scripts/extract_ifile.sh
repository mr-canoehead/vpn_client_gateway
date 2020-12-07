#!/bin/bash

#
# Extracts an inline file from an OpenVPN .ovpn configuration file
# Inline files are identified by tags, e.g.:
#
# <ca>
# inline file data
# </ca>
#
# The script extracts all lines between the selected opening and closing tags.
#

scriptName=$(basename "$0")

function usage()
{

usageText="
Usage: $(basename $0) [OPTIONS]

Options:
  --tag    <tag>               inline file tag
  --input  <input filename>    input .ovpn filename
  --output <output filename>   output .crt/.key filename

Example usage:

./$scriptName --tag=ca --input=myvpnconfig.ovpn --output=ca.crt

or use redirects:

./$scriptName --tag=ca < myvpnconfig.ovpn > ca.crt

or pipes:

cat myvpnconfig.ovpn | ./$scriptName --tag=ca | sudo tee /etc/openvpn/client/ca.crt > /dev/null

"

printf "%s" "$usageText"
}

ifileTag=""
inputFile="/dev/stdin"
outputFile="/dev/stdout"

while [ "$1" != "" ]; do
    parameter=$(echo $1 | awk -F= '{print $1}')
    value=$(echo $1 | awk -F= '{print $2}')
    case $parameter in
        -h | --help)
            usage >&2
            exit 0
            ;;
	--tag)
	    ifileTag=$value
	    ;;
	--input)
	    inputFile=$value
            ;;
	--output)
	    outputFile=$value
            ;;
        *)
            printf "ERROR: unknown parameter \"$parameter\"\n" >&2
            usage >&2
            exit 1
            ;;
    esac
    shift
done

if [[ "$inputFile" == "/dev/stdin" ]]; then
	if [ -t 0 ]; then
    		printf "ERROR: no input file specified; piped input expected.\n\n" >&2
    		usage >&2
    		exit 1
	fi
else
	if [[ ! -f "$inputFile" ]]; then
		printf "ERROR: input file $inputFile does not exist\n" >&2
		exit 1
	fi
fi

input=$(cat "$inputFile")


if [[ "$ifileTag" == "" ]]; then
	printf "ERROR: tag is required.\n\n" >&2
	printf "Usage:\n"
	usage >&2
	exit 1
fi

output=$(printf "%s" "$input" | sed -n -re "/\s+?<$ifileTag>/,/\s+?<\/$ifileTag>/{/\s+?(<$ifileTag>|<\/$ifileTag>)/d;p;}")
outputLen="${#output}"

if [[ $outputLen -eq 0 ]]; then
	printf "ERROR: tag \"$ifileTag\" not found.\n" >&2
	exit 1
fi

printf "%s" "${output}" > "$outputFile"
