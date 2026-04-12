#!/bin/sh
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SSL_DIR="${SCRIPT_DIR}/../../ssl"
CERT_FILE="${SSL_DIR}/origin.pem"
KEY_FILE="${SSL_DIR}/origin-key.pem"

if [ -f "$CERT_FILE" ] && [ -f "$KEY_FILE" ]; then
    echo "Origin certificate already exists, skipping."
    exit 0
fi

if [ -z "$CF_API_TOKEN" ] || [ -z "$CF_HOSTNAMES" ]; then
    echo "Error: missing required env vars."
    echo "  CF_API_TOKEN   - Cloudflare API token with Origin CA scope"
    echo "  CF_HOSTNAMES   - Comma-separated hostnames (e.g. pandy.pro,*.pandy.pro)"
    exit 1
fi

mkdir -p "$SSL_DIR"

FIRST_HOSTNAME=$(echo "$CF_HOSTNAMES" | cut -d',' -f1 | tr -d ' ')

echo "Generating private key and CSR..."
openssl req -new -newkey rsa:2048 -nodes \
    -keyout "$KEY_FILE" \
    -out /tmp/origin.csr \
    -subj "/CN=${FIRST_HOSTNAME}"

CSR_CONTENT=$(awk '{printf "%s\\n", $0}' /tmp/origin.csr)
rm -f /tmp/origin.csr

HOSTNAMES_JSON=$(echo "$CF_HOSTNAMES" | awk -F',' '{
    printf "["
    for (i=1; i<=NF; i++) {
        gsub(/^ +| +$/, "", $i)
        printf "\"%s\"", $i
        if (i < NF) printf ","
    }
    printf "]"
}')

PAYLOAD="{\"hostnames\":${HOSTNAMES_JSON},\"requested_validity\":5475,\"request_type\":\"origin-rsa\",\"csr\":\"${CSR_CONTENT}\"}"

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
    rm -f "$KEY_FILE"
    exit 1
fi

echo "$RESPONSE" | sed -n 's/.*"certificate":"\([^"]*\)".*/\1/p' | sed 's/\\n/\n/g' > "$CERT_FILE"

chmod 644 "$CERT_FILE"
chmod 600 "$KEY_FILE"

echo "Origin certificate saved to ${SSL_DIR}"
echo "  Certificate: ${CERT_FILE}"
echo "  Private key: ${KEY_FILE}"
