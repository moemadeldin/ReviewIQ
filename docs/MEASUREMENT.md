# Local review measurement

## Scope and honesty rules

- Every number produced by this procedure comes from a **local setup with throwaway test pull requests**. It is not production traffic and must not be presented as such.
- **Dollar cost was not measured.** The configured models use `:free` identifiers, but whether your OpenRouter account bills them is **NOT VERIFIED**. Do not claim a cost saving or a dollar figure.
- Always quote the `n=` sample size printed next to each metric. A number without its sample size is not usable.
- At the time of writing the local `.env` used `OPENROUTER_MODEL=deepseek/deepseek-v4-flash-0731:free` with fallbacks `qwen/qwen3.8-27b:free,nvidia/nemotron-3-super-120b-a12b:free`. Verify the actual model before you run anything:

```bash
php artisan tinker --execute="echo config('services.openrouter.model');"
```

## Before you run anything

1. Set a **spending cap on the OpenRouter key** in your OpenRouter account settings, and use a **cheap model** for this exercise. Confirm the cap before continuing.
2. Use a **throwaway GitHub repository** you own. Never point this at a real repository with real reviewers.
3. Keep the webhook pointed at your local app (ngrok or equivalent) and make sure the queue worker is running (`composer dev`, or the equivalent worker command in the README).
4. Do not commit logs, `raw_response` values, tokens, or pull-request content. The command prints aggregates only.

## What the exercise will call (counts, not estimates)

For each of 10–15 small pull requests:

| Call | Count per PR |
|---|---|
| GitHub App installation token POST | 1 (cached ~55 min, so ~1 for the whole batch) |
| GitHub diff GET | 1 per review |
| GitHub review POST | 1 (+ individual comment POSTs only if the batch returns HTTP 422) |
| OpenRouter chat completion POST | **1 nominal**; worst case 3 models × 3 attempts = **9** |

For 10–15 PRs that is **10–15 nominal LLM calls**, up to **90–135 worst-case**. A second push to the same PR adds another review with the same range. No dollar figure is asserted.

## Checklist to generate data

1. Confirm the OpenRouter spending cap and cheap model (step 1 above).
2. Create the throwaway repo and register the webhook as described in the README.
3. Open **10 to 15 small pull requests** (a few changed lines each; avoid lockfiles and generated files).
4. Let each review finish; confirm comments appear on the PR.
5. Optionally push one extra commit to one PR to exercise the mid-review re-dispatch path.
6. Run the measurement command below and copy its output into your notes **in chat**, not into the repository.

## Running the command

```bash
php artisan reviews:metrics
```

To use a specific log file (for example, a copied rotated log):

```bash
php artisan reviews:metrics --log=/path/to/laravel.log
```

The command is read-only: it issues no GitHub or LLM requests and writes no files.

## Reading the output

- **First-review time, average / p95** — seconds from `pull_requests.created_at` (created at the first webhook) to the first `reviews.created_at`. This is a **proxy**, not webhook-receipt-to-comment-posted latency. p95 uses nearest-rank.
- **Invalid-line warnings per review** — count of `Issue line invalid, moved to summary` log lines divided by the number of review rows, with both values and `n=` printed. The units are *warnings per review*, not a percentage of comments.
- **Reviews by model** — counts from `raw_response.meta.model`; non-primary models indicate fallback usage.
- **Token usage** — sums `meta.usage.total_tokens` only where present; the command reports how many reviews had no usage data rather than inventing values.
- **Duplicate webhook deliveries ignored** — count of `Duplicate webhook delivery ignored` log lines versus `Webhook received`.
- **Log rotation warning** — always printed. Log-derived counts reflect only the current file; older events may be missing.

## Not measured by this command

- Dollar cost or tokens-per-dollar.
- End-to-end latency from webhook receipt to GitHub comment being posted.
- Production usage, real users, or concurrent load.
- Cache hit rate or queue throughput.
