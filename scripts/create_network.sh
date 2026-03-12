#!/usr/bin/env bash

set -euo pipefail

NETWORK_NAME="email2s3_net"
SUBNET_CIDR="172.25.0.0/24"

if docker network inspect "${NETWORK_NAME}" >/dev/null 2>&1; then
  echo "Docker network '${NETWORK_NAME}' already exists."
else
  echo "Creating docker network '${NETWORK_NAME}' with subnet ${SUBNET_CIDR}..."
  docker network create \
    --driver bridge \
    --subnet "${SUBNET_CIDR}" \
    "${NETWORK_NAME}"
  echo "Docker network '${NETWORK_NAME}' created."
fi

