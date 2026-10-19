# Multi-Provider Order Orchestrator

> **Provenance.** Reference version of a production recharge and top-up gateway
> I worked on from 2022 to 2026 (260+ of my own commits). Partner, operator and
> customer names are removed and every credential comes from the environment.
> The queue flow, failover rules, data model and callback handling are the real
> ones. I am happy to do a live walkthrough of any design decision.

A queue-driven Lumen service that takes one client order (mobile top-up,
data pack, utility payment) and delivers it through whichever upstream
provider is available. It picks providers by priority, fails over when one
is down, and never treats an unsigned callback as proof of success.

## What it demonstrates

- **Respond immediately, work asynchronously.** `POST /order/request`
  validates the order, writes a `PENDING` transaction with a UUID `ref_id`,
  pushes a job to Beanstalkd and returns at once. The client gets a reference
  it can poll; a slow partner never holds an HTTP connection open.
- **Priority failover per operator.** `provider_operator_maps` ranks providers
  for each operator. The worker tries them in order, so one partner going down
  changes the route, not the outcome.
- **Idempotent references.** Every order has exactly one `ref_id`. Requeue and
  resend replay by reference, so retrying is safe and auditable, never a
  second charge.
- **A callback is a hint, not a fact.** The async partner does not sign its
  webhooks. `FoxtrotCallbackTrait` re-fetches the authoritative status from the
  partner before any transaction moves to a terminal state.
- **Shape-agnostic status parsing.** Partners disagree on where the answer
  lives (`status`, `Result`, `code`, …). Extractors try known keys instead of
  assuming one envelope, so a partner changing format does not silently break
  traces.
- **Lifecycle tracing with redaction.** `provider.trace` middleware logs every
  client- and vendor-facing call. Keys matching
  `pass|token|secret|pin|api_key|authorization|signature` are redacted before
  storage, and a logging failure can never turn a good order into a 500.
- **One timeline definition.** `transaction_trace_summary` is a SQL view, so
  support tools and ad-hoc investigation read the same history.

## Flow

```
client ──POST /order/request──▶ validate ─▶ pick provider (priority)
                                   │
                                   ├─ transactions row (PENDING, ref_id)
                                   ├─ Beanstalkd 'request' tube
                                   ▼
                          200 { transaction_id }

worker ─▶ vendor controller ─▶ partner API ─┬─ sync result → mark final
                                            └─ async → wait for webhook

partner ──POST /webhook──▶ ip check ─▶ parse ─▶ re-verify with partner ─▶ final
```

## Layout

| Path | Role |
|---|---|
| `app/Http/Controllers/` | Client entry point, inbound webhook, per-partner vendor controllers |
| `app/Integrations/` | `ProviderHandler` interface and implementations, the only place partner formats are understood |
| `app/Repositories/` | Interface/implementation split, so no controller holds a query |
| `app/Traits/` | Lifecycle logging, callback re-verification, message processing |

Design decisions and their trade-offs are in **[ARCHITECTURE.md](ARCHITECTURE.md)**.

## Running locally

```bash
cp .env.example .env        # fill DB, Beanstalkd and partner settings
docker compose up -d
docker compose exec restapi-app composer install
docker compose exec restapi-app php artisan migrate
```

## Credits

The project skeleton started from the MIT-licensed
[rest-api-with-lumen](https://github.com/hasib32/rest-api-with-lumen)
boilerplate (OAuth2 via Passport, repository base classes). The order
orchestration, provider integrations, failover, tracing and callback handling
were built on top of it.

*Stack: Lumen (PHP), MySQL, Beanstalkd, Redis, Docker.*
