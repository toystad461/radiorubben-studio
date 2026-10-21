# PEIS – ElevenLabs API connection check

Date: 2026-10-21. Work area: Radio Rubben / Teknisk Produksjon.
Status: CLI diagnostic implemented and tested locally; not deployed, not live-verified.
Base: main 96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60. No changes to the app, queues, keys, approvals or production settings.

## Confirmed source facts

- `app/config.php` already loads private `config/audio-local.php`, `config/elevenlabs.key`, and `ELEVENLABS_API_KEY`. An explicitly set empty environment variable overrides other key sources. The key-file loader rejects symlinks, non-private permissions and invalid content.
- `scripts/store-elevenlabs-key.py` already provides hidden, user-operated key entry with exclusive creation and mode 0600. Do not make a parallel secret store or overwrite an existing key blindly.
- `app/integrations/ElevenLabs.php` currently uses `/v1/text-to-speech/{voice_id}` and raw `pcm_24000`.
- The current official Text to Dialogue capability documentation describes Eleven v4. Some retrieved English overview pages were older and described v3. An old documentation snapshot is not proof that v4 is unavailable.
- Keep the user's selected voice `azrGjm6gYkR15bxb9cVv` and requested `eleven_v4`. Confirm authenticated access before synthesis. Do not silently fall back to another voice or model.
- The documented v4 route is Text to Dialogue: `/v1/text-to-dialogue`, with `inputs` containing `text` and `voice_id`. Existing Text to Speech payloads must not be assumed interchangeable. This is an integration issue to verify, not proof of the live server's failure cause.

## Read-only check

Run `php scripts/elevenlabs-preflight.php /absolute/path/to/studio` with PHP 8.2+ and cURL.
The script reuses the installed Studio `load_config()` and sends only these GET requests to the fixed HTTPS ElevenLabs host:

1. `/v1/models`
2. `/v1/voices/azrGjm6gYkR15bxb9cVv`
3. `/v1/user/subscription`

It does not generate audio, activate TTS, save credentials, modify config or publish anything. It does not expose the key, account invoices, private voice descriptions, provider error bodies or raw exceptions. TLS is verified; redirects are refused; response size and request time are bounded. Authentication and rate-limit failures stop rather than being retried.

`read_access_verified_generation_not_tested` means model metadata and voice read access were verified. It is deliberately NOT a successful synthesis claim. Subscription read permissions may be unavailable independently. Account character quota is not the API key's separate credit limit. These diagnostic calls do not verify all TTD generation permissions, billing or live audio quality.

The command checks the CURRENT CLI user and environment, not PHP-FPM or another background worker. A mode-0600 key must be owned by its intended runtime user. Do not make it world-readable to fix a permission error. Do not copy production secrets into the isolated test VPS without explicit scope approval.

### Run without switching the installed Studio branch

The last recorded test Studio path is `/srv/radiorubben-test/studio`, and Thomas has confirmed the Windows SSH alias `rubben-test`. The current chat runtime does not contain that key and its TCP connection to port 22 was refused. This does not prove Windows SSH is broken. No firewall or SSH settings have been changed.

Download the diagnostic file as `elevenlabs-preflight.php`. Transfer that one file to the test user's home directory; do not git-pull or replace the deployed app just for this check.

Windows PowerShell, assuming the download was saved to Downloads:

```powershell
scp "$HOME\Downloads\elevenlabs-preflight.php" rubben-test:elevenlabs-preflight.php
ssh rubben-test "php ~/elevenlabs-preflight.php /srv/radiorubben-test/studio"
```

On a Mac with the SAME working alias and authorized key, use `$HOME/Downloads/elevenlabs-preflight.php` instead. Mac access is not confirmed. No key or password should be pasted into chat, a URL, shell arguments or Git.

If the Studio path differs or the file is not readable, the script reports a non-secret error and stops. Verify the real path/worker before changing anything.

## Interpretation and next action

- `key_missing`: determine whether a test-scoped key has already been configured for the correct runtime. Do not assume the provider account lacks a key.
- `empty_environment_key_override=true`: an empty CLI environment variable can hide a correctly installed key file; diagnose the environment, not the secret.
- `config_load_failed_check_key_file_owner_and_mode_600`: config could not load; inspect privately. Key-file ownership/0600 permissions are possible causes, not a conclusive diagnosis.
- HTTP 401 / `invalid_api_key`: verify or replace the test runtime key privately.
- HTTP 403 / `missing_permissions`: inspect the key's scopes and any IP allowlist; do not broaden all permissions automatically.
- `requested_model_listed=false`: preserve the requested model; inspect the authenticated model result/account before a generation attempt.
- `requested_voice_accessible=false`: verify the intended ElevenLabs account/workspace and voice rights.
- `read_access_verified_generation_not_tested`: next implement/verify the v4 TTD adapter and make one bounded, logged audio test using the already authorized episode text.

If a NEW key is needed, create it in the intended ElevenLabs account with the minimum generation/read scopes and a deliberate credit cap. Enter it privately using the existing hidden-entry script in the correct test runtime. Never send it in chat. No new key has been created here.

## Episode 02 boundary

The episode already has 10 checked transport segments, at most 1989 characters each. Keep these separate from the three editorial parts. The future episode job must preserve their order, save raw outputs, record request IDs/settings and stop on an uncertain result before any retry. Do not reuse the short-news function with its automatic introduction. Do not activate global queues or publishing as part of connection setup.

Use the existing intro once and the existing FX twice. After actual generation, check level variation, plosives, transitions, text coverage, duration and final decoding. No per-clip human approval requirement is introduced by this diagnostic.

## Tests actually performed

- PHP 8.4.24 syntax check: passed.
- 13 built-in offline checks: passed, including missing/invalid key, successful mocked reads, no secret leakage, authentication/rate-limit/transport failures, missing model and forbidden route.
- 4 additional local CLI fixture cases: missing Studio path; config output suppression with missing key and unchanged file; exception redaction; invalid key rejection. Passed.
- Zero real ElevenLabs synthesis requests. No account credentials were used. No successful VPS/API connection or full Composer-suite execution is claimed.

Reproduce the built-in checks with `php scripts/elevenlabs-preflight.php --self-test`.

## Sources checked on 2026-10-21

- https://elevenlabs.io/docs/api-reference/authentication
- https://elevenlabs.io/docs/overview/capabilities/text-to-dialogue
- https://elevenlabs.io/docs/it/overview/models
- https://elevenlabs.io/docs/api-reference/text-to-dialogue/convert
- https://elevenlabs.io/docs/api-reference/models/list
- https://elevenlabs.io/docs/api-reference/voices/get
- https://elevenlabs.io/docs/api-reference/user/subscription/get

The older English model overview and newer v4 documentation were not consistent in the retrieved snapshots. The live authenticated model listing and bounded audio test remain necessary. The existing app adapter is unchanged in this branch.
