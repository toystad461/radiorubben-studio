# Studio deployment: isolated Render staging

## Source and scope
Built on PR #19, runtime f7ce90ce393a625ba4b9ffc4cf494457dbb04244.
Its source reconciliation is reused, not claimed as a new live-server verification.
Main is still not the production baseline. PR #20's Entra test helpers are separate.
No production, WordPress, DNS, Entra tenant, OneDrive or radio service changes.

## What is implemented
- PHP 8.4/Apache Docker image, public/ as the only document root, locked Composer dependencies.
- Apache Basic authentication around every page and asset, except minimal healthz.php.
  Startup refuses absent credentials or a password shorter than 20 characters.
- Separate /var/studio persistent volume for config/data and sessions.
  Code is replaced on deployment; data is retained. Never attach a production disk.
- Initial auth mode demo deliberately permits only the login page, not an internal session.
  This first stage verifies hosting, packaging and isolation, not full editorial acceptance.
- No cron or worker services. No production data import. Mail transport disabled.
  Private integration files refuse startup; AI/Vipps env credentials are removed at startup.
- Container CI verifies private paths, authentication barrier, closed application auth,
  persistence after container recreation and refusal to start without access credentials.
  Existing PHP 8.2/8.4, JavaScript and package workflows remain enabled.
- Uniweb apply now requires manual workflow_dispatch and the exact approved SHA.
  STUDIO_DEPLOY_AUTO_APPLY no longer enables automatic production writes.

## Provisioning
1. Wait for all actual mandatory PHP/JavaScript/package/container jobs to succeed on
   the exact candidate head. Render checksPass alone also accepts skipped/neutral checks.
2. Connect the existing private GitHub repo to Render. Review current Starter + 1 GB
   disk pricing in the actual workspace before creating the service. No price guessed here.
3. Use render.yaml from infra/render-staging-20261001. Keep Blueprint Auto Sync off
   during setup: first creation/sync is not guaranteed to wait for CI.
4. Set STAGING_BASIC_USER; retain the generated password in a password manager.
   Set STUDIO_BASE_URL to the actual HTTPS onrender.com address. Never put secrets in chat.
5. Confirm health 200, anonymous pages/assets 401, protected login page 200,
   no public private files and persistent synthetic data after a redeploy.
   Port 10000 is internal behind Render HTTPS; never expose Apache directly to the Internet.
6. Only after this: configure a separate Entra test app and selected test users,
   with its exact callback URI. Follow PR #20's test protocol. Do not copy production
   accounts or change tenant permissions implicitly. Real login remains unverified.
7. Once the base PR is integrated and the baseline is accepted, change the deployment
   branch intentionally to the agreed staging branch/main. Do not leave production tied
   to this temporary implementation branch.

## Production gate and rollback
Uniweb continues to serve production. No production deployment is enabled by this PR.
Before any apply: fresh server hashes and composer/vendor comparison, private backup,
approved exact commit, explicit file list, authenticated UI checks and integrations.
Use existing Studio release workflow with mode=apply and approved_sha matching the
tested source. Existing enable/SSH/environment checks still apply. This manual invocation
is not a claim that GitHub required-reviewer protection is available on the account.

For Render staging rollback, redeploy the last tested commit; keep auto deploy off until
the faulty change is corrected. Disk contents do not roll back with code. With one disk
the service is single-instance and deployment may cause a short interruption.
Production migration and data/schema rollback need a separate reviewed procedure.

## Remaining external work
Render account/service creation, actual URL, price, private settings, first successful
deployment and real login must be recorded before claiming a usable test environment.
Source code CI does not verify any of those. OneDrive backup is not implemented.
