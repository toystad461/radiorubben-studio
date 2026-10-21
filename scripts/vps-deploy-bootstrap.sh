#!/usr/bin/env bash
# Run once as root from a reviewed, SHA-pinned checkout. Argument: public key file.
set -Eeuo pipefail
[[ $EUID == 0 && $# == 1 ]] || { echo 'Usage: sudo bash scripts/vps-deploy-bootstrap.sh /path/to/deploy-key.pub'; exit 1; }
public_key=$(cat -- "$1")
[[ "$public_key" =~ ^ssh-ed25519\ [A-Za-z0-9+/=]+(\ [^\$\`\"\\]*)?$ ]] || { echo 'Expected one ED25519 public key'; exit 1; }
[[ $(printf '%s\n' "$public_key" | wc -l) == 1 ]] || exit 1
ssh-keygen -l -f "$1" >/dev/null
for path in /etc/sudoers.d/rr-vps-deploy /usr/local/libexec/rr-vps-deploy.py /home/rr-deploy; do
  [[ ! -e "$path" ]] || { echo "STOPP: Already exists: $path"; exit 1; }
done
if getent passwd rr-deploy >/dev/null; then echo 'STOPP: rr-deploy already exists'; exit 1; fi
source_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
python3 -m py_compile "$source_dir/vps-deploy-gateway.py"
/usr/sbin/sshd -t
work=$(mktemp -d)
trap 'rm -rf -- "$work"' EXIT
printf '%s\n' 'rr-deploy ALL=(root) NOPASSWD: /usr/bin/python3 -I /usr/local/libexec/rr-vps-deploy.py' > "$work/sudoers"
visudo -cf "$work/sudoers"
useradd --create-home --shell /bin/sh rr-deploy
# Root owns the SSH directory: the account cannot replace its forced command.
chown root:root /home/rr-deploy
chmod 755 /home/rr-deploy
install -d -o root -g root -m 755 /home/rr-deploy/.ssh /usr/local/libexec
printf 'restrict,command="/usr/bin/sudo -n /usr/bin/python3 -I /usr/local/libexec/rr-vps-deploy.py" %s\n' "$public_key" > "$work/authorized_keys"
install -o root -g root -m 644 "$work/authorized_keys" /home/rr-deploy/.ssh/authorized_keys
install -o root -g root -m 644 "$source_dir/vps-deploy-gateway.py" /usr/local/libexec/rr-vps-deploy.py
install -o root -g root -m 440 "$work/sudoers" /etc/sudoers.d/rr-vps-deploy
install -d -o root -g root -m 700 /var/lib/rr-vps-deploy
visudo -c >/dev/null
printf '%s\n' '{"mode":"snapshot"}' | sudo -u rr-deploy sudo -n /usr/bin/python3 -I /usr/local/libexec/rr-vps-deploy.py > "$work/snapshot.json"
python3 -c 'import json,sys; d=json.load(open(sys.argv[1])); print("PASS: Gateway snapshot; code files:",len(d["files"]))' "$work/snapshot.json"
echo 'GitHub known_hosts value (public host key from this VPS):'
awk '{print "5.249.252.78 " $1 " " $2}' /etc/ssh/ssh_host_ed25519_key.pub
echo 'OK: Forced-command gateway installed. No Studio code or service configuration changed.'
