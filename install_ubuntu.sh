#!/bin/bash
set -e
DIR="$HOME/sura-print-agent"
mkdir -p "$DIR"
cp print_agent.py "$DIR/"
cp config.example.json "$DIR/"
python3 -m venv "$DIR/venv"
cat <<EOF
Agent copié dans $DIR
1) cp config.example.json config.json
2) Modifier server_url et agent_token dans config.json
3) Tester : $DIR/venv/bin/python $DIR/print_agent.py
4) Installer ensuite le service systemd (instructions README).
EOF
