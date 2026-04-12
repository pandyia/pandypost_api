#!/bin/sh
set -e

SSL_DIR="/etc/ssl/cloudflare"
CERT_FILE="${SSL_DIR}/origin.pem"
KEY_FILE="${SSL_DIR}/origin-key.pem"

if [ -f "$CERT_FILE" ] && [ -f "$KEY_FILE" ]; then
    echo "Origin certificate already exists, skipping."
    exit 0
fi

if [ -z "$CF_API_TOKEN" ] || [ -z "$CF_HOSTNAMES" ]; then
    echo "Error: missing required env vars."
    echo "  CF_API_TOKEN   - Cloudflare API token with Origin CA scope"
    echo "  CF_HOSTNAMES   - Comma-separated hostnames (e.g. pandypost.com,*.pandypost.com)"
    exit 1
fi

HOSTNAMES_JSON=$(echo "$CF_HOSTNAMES" | awk -F',' '{
    printf "["
    for (i=1; i<=NF; i++) {
        gsub(/^ +| +$/, "", $i)
        printf "\"%s\"", $i
        if (i < NF) printf ","
    }
    printf "]"
}')

PAYLOAD=$(cat <<EOF
{
    "hostnames": ${HOSTNAMES_JSON},
    "requested_validity": 5475,
    "request_type": "origin-rsa",
    "csr": ""
}
EOF
)

echo "Requesting Origin Certificate from Cloudflare..."

RESPONSE=$(curl -s -X POST \
    "https://api.cloudflare.com/client/v4/certificates" \
    -H "Authorization: Bearer ${CF_API_TOKEN}" \
    -H "Content-Type: application/json" \
    --data "$PAYLOAD")

SUCCESS=$(echo "$RESPONSE" | grep -o '"success":[a-z]*' | head -1 | grep -o 'true\|false')

if [ "$SUCCESS" != "true" ]; then
    echo "Cloudflare API error:"
    echo "$RESPONSE"
    exit 1
fi

mkdir -p "$SSL_DIR"

echo "$RESPONSE" | sed -n 's/.*"certificate":"\([^"]*\)".*/\1/p' | sed 's/\\n/\n/g' > "$CERT_FILE"
echo "$RESPONSE" | sed -n 's/.*"private_key":"\([^"]*\)".*/\1/p' | sed 's/\\n/\n/g' > "$KEY_FILE"

chmod 644 "$CERT_FILE"
chmod 600 "$KEY_FILE"

echo "Origin certificate saved to ${SSL_DIR}"
echo "  Certificate: ${CERT_FILE}"
echo "  Private key: ${KEY_FILE}"
