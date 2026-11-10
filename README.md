# Agent d’impression SURA — Ubuntu + OKPOS OK-50

## 1. Préparer l’hébergement
1. Téléverser le dossier `htdocs` complet en conservant les chemins.
2. Importer `sql/print_queue.sql` dans la même base MySQL que le RMS.
3. Vérifier que le site est en HTTPS.
4. Le secret partagé est défini dans `config/print_agent.php`. Le copier dans `print-agent/config.json` (champ `agent_token`). Ne jamais le mettre dans JavaScript ni le publier.

## 2. Installer sur Ubuntu
Copier `print-agent` dans `~/sura-print-agent`, puis :
```bash
cd ~/sura-print-agent
cp config.example.json config.json
nano config.json
chmod 600 config.json
python3 print_agent.py
```
Renseigner `server_url` avec l’URL HTTPS exacte du site (avec / final) et le même token que `config/print_agent.php`. Garder `/dev/usb/lp4` si c’est le périphérique OKPOS sur ce PC.

## 3. Autoriser l’accès à l’imprimante
```bash
sudo usermod -aG lp "$USER"
```
Se déconnecter/reconnecter Ubuntu. Tester l’écriture avec l’agent. Si `/dev/usb/lp4` change, corriger `printer_device`.

## 4. Service automatique (facultatif)
Après avoir testé manuellement, créer `~/.config/systemd/user/sura-print-agent.service` :
```ini
[Unit]
Description=SURA Cloud Print Agent
After=network-online.target
Wants=network-online.target

[Service]
ExecStart=%h/sura-print-agent/venv/bin/python %h/sura-print-agent/print_agent.py
WorkingDirectory=%h/sura-print-agent
Restart=always
RestartSec=5

[Install]
WantedBy=default.target
```
Puis :
```bash
systemctl --user daemon-reload
systemctl --user enable --now sura-print-agent
systemctl --user status sura-print-agent
```

## Fonctionnement
Navigateur authentifié → `api/print/create.php` → table `print_jobs` sur l’hébergement → agent Ubuntu qui interroge HTTPS → `/dev/usb/lp4` → confirmation. Aucun port entrant n’est ouvert sur Ubuntu. Les autres appareils peuvent demander une impression tant que le PC Ubuntu et l’agent sont allumés et connectés à Internet.

## Sécurité
- Le navigateur ne reçoit jamais le token de l’agent.
- L’agent utilise exclusivement des requêtes HTTPS sortantes.
- Le compte SQL de l’application reste celui défini dans `config/database.php`.
- Après installation, protéger `config/print_agent.php` et `config/` contre le téléchargement direct (règle `.htaccess` existante à vérifier selon l’hébergeur).
