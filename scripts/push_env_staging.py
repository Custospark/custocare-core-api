#!/usr/bin/env python3
"""Compose and upload the STAGING server .env for Custocare via SFTP.

Reads Backend/.env (local dev values + commented # STAGING_* DB block),
overrides environment URLs, drops the staging comment block, uploads to
/home/u214605677/domains/custocare-staging-api.custospark.com/.env.

SSH creds come from Custosell Backend/.env SSH_DEPLOY_* (same account).
Output is masked: path + byte count only. Secrets never reach stdout.

Usage:
    python scripts/push_env_staging.py
"""
import os
import sys

import paramiko

APP_DIR = '/home/u214605677/domains/custocare-staging-api.custospark.com'
APP_URL = 'https://custocare-staging-api.custospark.com'
FRONTEND_URL = 'https://custocare-staging.custospark.com'


def parse_simple(path, prefix=None):
    out = {}
    with open(path, encoding='utf-8', errors='ignore') as fh:
        for raw in fh:
            line = raw.strip()
            if not line or line.startswith('#') or '=' not in line:
                continue
            key, _, val = line.partition('=')
            key = key.strip()
            if prefix and not key.startswith(prefix):
                continue
            out[key] = val.strip()
    return out


def main():
    here = os.path.dirname(os.path.abspath(__file__))
    backend = os.path.dirname(here)
    local_env_path = os.path.join(backend, '.env')

    with open(local_env_path, encoding='utf-8') as fh:
        local_lines = fh.read().splitlines()

    staging = {}
    for raw in local_lines:
        line = raw.strip()
        if line.startswith('# STAGING_') and '=' in line:
            key, _, val = line[1:].strip().partition('=')
            staging[key.strip()] = val.strip()

    out_lines = []
    for raw in local_lines:
        stripped = raw.strip()
        if stripped.startswith('# STAGING_'):
            continue
        out_lines.append(raw)

    composed = []
    for line in out_lines:
        s = line.strip()
        if s.startswith('APP_ENV='):
            composed.append('APP_ENV=staging')
        elif s.startswith('APP_DEBUG='):
            composed.append('APP_DEBUG=false')
        elif s.startswith('APP_URL='):
            composed.append(f'APP_URL={APP_URL}')
        elif s.startswith('FRONTEND_URL='):
            composed.append(f'FRONTEND_URL={FRONTEND_URL}')
        elif s.startswith('DB_CONNECTION=') and staging.get('STAGING_CONNECTION'):
            composed.append(f"DB_CONNECTION={staging['STAGING_CONNECTION']}")
        elif s.startswith('DB_HOST=') and staging.get('STAGING_DB_HOST'):
            composed.append(f"DB_HOST={staging['STAGING_DB_HOST']}")
        elif s.startswith('DB_PORT=') and staging.get('STAGING_DB_PORT'):
            composed.append(f"DB_PORT={staging['STAGING_DB_PORT']}")
        elif s.startswith('DB_DATABASE=') and staging.get('STAGING_DB_DATABASE'):
            composed.append(f"DB_DATABASE={staging['STAGING_DB_DATABASE']}")
        elif s.startswith('DB_USERNAME=') and staging.get('STAGING_DB_USERNAME'):
            composed.append(f"DB_USERNAME={staging['STAGING_DB_USERNAME']}")
        elif s.startswith('DB_PASSWORD=') and staging.get('STAGING_DB_PASSWORD'):
            composed.append(f"DB_PASSWORD={staging['STAGING_DB_PASSWORD']}")
        else:
            composed.append(line)

    required = ['DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD']
    missing = [k for k in required if not any(
        l.strip().startswith(k + '=') and len(l.strip().split('=', 1)[1]) > 0
        for l in composed
    )]
    if missing:
        print(f'[abort] staging DB values missing for: {missing}')
        sys.exit(1)

    content = '\n'.join(composed) + '\n'

    ssh_env = parse_simple(
        os.path.join(backend, '..', '..', 'Custosell', 'Backend', '.env'),
        prefix='SSH_DEPLOY_',
    )
    host = ssh_env.get('SSH_DEPLOY_HOST', '')
    port = int(ssh_env.get('SSH_DEPLOY_PORT', '22'))
    user = ssh_env.get('SSH_DEPLOY_USER', '')
    password = ssh_env.get('SSH_DEPLOY_PASSWORD', '')
    if not host or not user or not password:
        print('[abort] SSH_DEPLOY_* incomplete')
        sys.exit(1)

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        client.connect(host, port=port, username=user, password=password, timeout=20)
        sftp = client.open_sftp()
        remote = APP_DIR + '/.env'
        with sftp.open(remote, 'w') as fh:
            fh.write(content)
        sftp.close()
        print(f'[ok] uploaded {remote} ({len(content)} bytes)')
    finally:
        client.close()


if __name__ == '__main__':
    main()
