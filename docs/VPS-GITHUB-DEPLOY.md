# GitHub → Radio Rubben test-VPS

Status 21.10.2026: prepared infrastructure; not installed or connected to VPS.
Scope: Studio test code at 5.249.252.78. Old Uniweb production workflow remains unchanged.

## Design and trust boundary

Dedicated branch `deploy/vps-test`, environment `studio-vps-test`, user `rr-deploy`.
Push to that branch triggers verification then the fixed SSH gateway. Normal feature
branches and pull requests cannot transfer files. Environment deployment branch rule
must allow only `deploy/vps-test`; retain any existing approval requirements.
The initial committed manifest requests **snapshot only**. Creation of the deployment
branch must wait until the environment and bootstrap are ready.

The SSH public key has OpenSSH `restrict` and a forced command. It cannot open a
normal shell, allocate a PTY, forward ports, or upload arbitrary files via SFTP.
The only sudo command is `/usr/bin/python3 -I /usr/local/libexec/rr-vps-deploy.py`.
The helper and SSH authorization are root-owned. This key nevertheless authorizes
Studio application code changes: protect it like application-administration access.
Code could access data as the existing application user. No self-hosted runner is installed.

Allowed files: existing-parent `app/` and `public/` PHP, JS/MJS, CSS, max 64 files,
2 MiB each and 12 MiB encoded request. No deletes, symlinks, config, vendor,
Composer dependencies, WordPress, Nginx, systemd configuration or arbitrary shell.
System/infrastructure changes and new parent directories require a separate reviewed
bootstrap update. This first gateway does not install the AI worker service/timer.

Exact source commit plus per-file before/after SHA256 is reviewed in
`deploy/vps-test.json`. The packager exports only those files from that commit.
The candidate source's Composer suite, PHP syntax and JS syntax must pass first,
in a job without SSH secrets. This is not a test of the entire mixed active VPS
release: dependency compatibility must be reviewed from the snapshot as well.

The gateway checks paths, checksums and root-owned non-writable code ancestry.
It takes a private per-file backup and durable pending journal, writes each file
atomically, reloads shared PHP-FPM and restarts the dedicated Entra FPM, then checks
service state, HTTPS and installed hashes. These cache refreshes are necessary for
new PHP code; shared PHP-FPM also hosts the test WordPress pool. There is no WP code
or configuration change. Multi-file replacement is not globally atomic; use small
compatible file sets. Health checks do not prove login or every application feature.

Failure rolls back written code only, with a second cache refresh and health check.
Concurrent changes block rollback and preserve the journal. A process kill also
leaves the pending journal; further apply is blocked pending inspection. No automatic
recovery may overwrite a newer code edit or restore old runtime data.
Private receipts/backups: `/var/lib/rr-vps-deploy/`. GitHub receives code hashes,
source commit, status and backup path, never config contents, music or API keys.

## One-time setup

1. Review the PR and pinned setup commit. Do not merge into main just to enable
   this workflow: main already has a separate production auto-deploy workflow.
2. On Windows PowerShell, create a dedicated key (do not overwrite an existing key):
   `ssh-keygen -t ed25519 -f "$env:USERPROFILE\.ssh\rr-vps-github" -C "rr-vps-github"`.
   For this machine key press Enter for an empty passphrase at both prompts.
   Keep the private file on your computer; never paste it into chat or Git.
3. Copy only the public file to the VPS using your existing Ubuntu SSH login:
   `scp "$env:USERPROFILE\.ssh\rr-vps-github.pub" ubuntu@5.249.252.78:/tmp/rr-vps-github.pub`.
4. Export `scripts/vps-deploy-bootstrap.sh` and `scripts/vps-deploy-gateway.py`
   from the reviewed exact commit using the existing rr-build repository. Run
   `sudo bash /path/to/reviewed/scripts/vps-deploy-bootstrap.sh /tmp/rr-vps-github.pub`.
   The installer stops if a previous setup exists. On partial failure inspect the
   reported state; do not rerun blindly or remove unrelated SSH authorization.
   A root-ownership check failure requires inspecting actual code ownership; do
   not recursively chmod/chown the app or data to bypass it.
5. Create environment `studio-vps-test` in this repository's GitHub Settings →
   Environments. Restrict deployment branches to `deploy/vps-test`.
   Add environment secrets:
   - `VPS_TEST_SSH_KEY`: private file from step 2. PowerShell can copy it locally
     with `Get-Content -Raw "$env:USERPROFILE\.ssh\rr-vps-github" | Set-Clipboard`.
     Paste directly into GitHub, save, then clear clipboard with `Set-Clipboard -Value ''`.
   - `VPS_TEST_KNOWN_HOSTS`: the public known_hosts line printed by the installer,
     obtained directly from `/etc/ssh/ssh_host_ed25519_key.pub` on this VPS.
6. After green infrastructure CI and successful bootstrap, create `deploy/vps-test`
   from the reviewed infrastructure head. That push runs the snapshot automatically.
   Codex can create/update the branch and read Actions logs/receipt artifacts;
   the available connector cannot administer secrets or dispatch new workflows.
   No workaround grants it secret-administration access.

## Following deployments

Read the snapshot artifact and active source dependencies. Propose a narrow manifest:

```json
{
  "mode": "check",
  "commit": "exact-40-character-source-commit",
  "files": [
    {"path": "app/example.php", "before": "active-sha256", "after": "candidate-sha256"}
  ]
}
```

Use null before only for a genuinely absent file. No dynamic full-branch sync.
Push a reviewed check manifest to `deploy/vps-test`; read the result. For an authorized
release, change only mode to `apply`, push, and inspect the receipt and actual UI.
Do not claim deployed from a check result or a successful build. Subsequent edits to
that branch are deploy actions and require authorization for the concrete release.
Snapshot requests may be rerun through the existing failed-job rerun facility or
a new snapshot-only commit. They do not change Studio code or services.

## Recovery / revocation

To revoke gateway access, remove only `/home/rr-deploy/.ssh/authorized_keys` and the
`VPS_TEST_SSH_KEY` environment secret. Keep code backups and receipts.
For a pending deploy, inspect root-private pending.json and its backup manifest;
compare each live hash to before/after, restore only matching failed-deploy files,
refresh PHP caches and verify health before clearing the journal. Unknown live
hashes require investigation. Runtime data and configuration are never restored.

## Verification evidence and remaining gates

Local: seven synthetic tests cover success/backup, checksum drift, health-failure
rollback, preservation of concurrent changes, interrupted-deploy lockout, forbidden
paths/symlink/duplicate/hash rejection, and exclusion of private data from snapshots.
Bash syntax and workflow YAML parsing pass. Actual Actions results are in the PR.
Not yet verified: SSH account/secret setup, server code ownership, firewall reachability,
first snapshot, actual VPS apply/rollback, or user-facing functionality after deploy.

Official references:
- https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-secrets
- https://docs.github.com/en/actions/reference/workflows-and-actions/deployments-and-environments
- https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax
