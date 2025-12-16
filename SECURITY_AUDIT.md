Security audit summary

Findings:
- Local developer secret(s) detected: sk- tokens in `~/.continue/config.yaml`. Rotate immediately if valid.
- Client bundles include OpenAI client code and warn about running in browser environments. Ensure no server keys are used in client builds.
- Implemented `01_backend_painel_php/api_openai_proxy.php` to centralize OpenAI usage via server.
- Replaced `eval()` and gated shell exec; see `01_backend_painel_php/app/Support/Security.php`.

Recommended next steps:
- Rotate any exposed keys immediately and ensure personal config files are not committed.
- Run repository-wide PHP syntax checks: `scripts/check_php_syntax.sh` (uses Docker or local php).
- Scan repo for secrets: `scripts/scan_secrets.sh` and review results.
- Harden `api_openai_proxy.php` before production: require auth, restrict origins, add rate-limiting.
	- New runtime envs supported by proxy:
		- `OPENAI_PROXY_SECRET`: optional shared secret header `X-Alabama-Proxy-Key` for clients.
		- `OPENAI_PROXY_ALLOWED_ORIGIN`: optional origin prefix to restrict CORS.
		- `OPENAI_PROXY_RL_MAX_ATTEMPTS`: rate-limit count (default 60).
		- `OPENAI_PROXY_RL_WINDOW_SECONDS`: rate-limit window in seconds (default 60).
- Rebuild extension bundles with keys removed; prefer proxy calls and CI checks preventing keys in builds.

How to run checks locally:
- `bash scripts/scan_secrets.sh`  # search for sk- and common secrets
- `bash scripts/check_php_syntax.sh`  # runs `php -l` using Docker if available

CI notes:
- The repository CI now runs `scripts/scan_secrets.sh` (and will fail the job when `SCAN_FAIL_ON_MATCH=true`).
- A `php-tests` job runs `composer install` in `01_backend_painel_php` and executes PHPUnit (PHP 8.2).

If you want, I can (a) prepare a PR with automated replacements for obvious leaks, (b) add CI checks to run `php -l` in Docker, and (c) add a secret-scanning GitHub Action.
