# Architecture

## Component diagram

```
                    API client
                        │  POST /order/request   (Bearer token)
                        ▼
        ┌────────────────────────────────────────────────┐
        │ auth:api → throttle → provider.trace           │
        │            (ProviderTraceMiddleware)           │
        └───────────────────────┬────────────────────────┘
                                ▼
                   OrderRequestController
                       │
       ┌───────────────┼────────────────┬──────────────────┐
       ▼               ▼                ▼                  ▼
 OperatorRepository  ProviderRouteMap  generatePayload  TransactionRepository
 (validate operator) (priority pick)   (per-partner     (persist, unique ref_id)
                                        field mapping)
                                ▼
                       Beanstalkd tube 'request'
                        (job id stored on the transaction)
                                ▼
                          queue worker
                                │  dispatches by stored `route` string
                                ▼
       ┌────────────────────────┴─────────────────────────┐
       ▼                                                  ▼
 EchoTopUpApiController                    FoxtrotNewOrderVendorController
 (sync: submit → status)                   (async: submit + callback_url)
                                                          │
                                             partner calls back
                                                          ▼
                                              POST /webhook?provider=…
                                                          │
                                          ProviderServiceProvider resolves
                                          ProviderHandler implementation
                                                          ▼
                                              FoxtrotProviderHandler
                                              (+ FoxtrotCallbackTrait)
                                                          │
                                        re-verify against partner status API
                                                          ▼
                                            TransactionRepository → terminal state
                                                          ▼
                                                client callback fired

  cross-cutting:  ProviderLifecycleLogTrait ──▶ transaction_event_logs
                                             ──▶ transaction_trace_summary (SQL view)
```

## Why it's shaped this way

**The client response does not wait on the partner.** `OrderRequestController`
persists the transaction, puts it on Beanstalkd, and returns a reference. A
partner API can take seconds or hang entirely; holding a client's connection
open for that is how one slow partner becomes an outage for every client. The
returned Beanstalkd job id is written back onto the transaction immediately, so
"where is this order" is answerable by lookup rather than by inference.

**Provider selection is data, not code.** An operator can be served by several
partners, each row carrying a `priority` and a `gw_integration_code`. Failing a
partner over — or onboarding a new one — is an `UPDATE` on the route-map table.
The dispatching worker never switches on partner code; it reads the `route`
string that was stored when the transaction was built. That also means a
transaction queued before a routing change still dispatches the way it was
originally built, which matters when a route is changed mid-incident.

**One place knows partner payload differences.** Every partner wants the same
handful of facts under different field names. `generatePayload()` is the single
place that knows those differences, so nothing upstream branches on partner
code. (The version here keeps three representative shapes; the original's full
chain is the sensitive part and is not reproduced.)

**Tracing is middleware, not sprinkled logging.** `ProviderTraceMiddleware`
wraps both the client-facing and vendor-facing calls, so a trace exists whether
or not anyone remembered to add a log line. It is written defensively: the
whole trace body is wrapped in try/catch and swallows its own failures, because
a logging bug must never turn a successful order into a 500. Before anything is
persisted, `sanitize()` walks the payload and redacts any key matching
`/pass|token|secret|\bpin\b|api_key|apikey|authorization|signature/i` — that
redaction is what makes it safe to keep full request/response bodies at all.

**Status extraction is shape-agnostic.** Partners disagree about whether the
answer lives in `status`, `status_message`, `Result`, or `code`.
`extractProviderStatus()` / `extractInternalStatus()` / `extractMessage()` try a
list of known keys rather than assuming one response shape, so a partner
changing its envelope does not silently produce empty traces.

**An unsigned callback is a trigger, never truth.** The async partner provides
no signature or shared secret, so anyone who learns the callback URL could
otherwise mark an order successful. `FoxtrotCallbackTrait` therefore treats a
callback purely as a hint and re-fetches the authoritative status from the
partner before moving the transaction to a terminal state.

**Requeue and resend exist because partners go silent.** A transaction can sit
in `PENDING` because a partner never called back. `requeueByTransactionId` and
`resendByTransactionId` replay it by reference, which is safe and auditable;
the alternative — editing a status column by hand — is how reconciliation
disputes start.

## Request lifecycle — client order

```
POST /order/request
  │  auth:api                   → client resolved from bearer token
  │  throttle
  │  provider.trace             → request logged (credentials redacted)
  ▼
OrderRequestController::getOrderRequest()
  │  validate input
  │  resolve operator                       (OperatorRepository)
  │  pick provider by priority              (ProviderRouteMap)
  │  ref_id = uuid v4
  │  request_payload = generatePayload()    (per-partner mapping)
  │  storeJob()                             → transactions row, status = PENDING
  │  pheanstalk->useTube('request')->put()  → job id
  │  updateTransaction(ref_id, beanstalkd_job_id)
  │  logProviderStep('CLIENT-API', ref_id, 'ORDER-QUEUE-DISPATCH', …)
  ▼
HTTP 200 { transaction_id, status }   ← client has a reference immediately
```

## Request lifecycle — webhook

```
Partner ──POST /webhook?provider=FOXTROT──▶ WebhookController
  │  ip.checker                       → source restricted
  │  ProviderServiceProvider resolves ProviderHandler for `provider`
  │  handler->parseRequest()          → partner-specific payload → normalised
  │  logProviderStep(provider, ref_id, 'WEBHOOK-RECEIVED', …)
  │
  │  handler handles its own processing?
  │    yes → re-verify against partner status API (unsigned callback)
  │    no  → use the parsed status directly
  │
  │  transaction lookup by ref_id
  │  markConfirmed() / markFailed()
  ▼
200 acknowledged   (always ack a recognised callback; partners retry otherwise)
```

## Data model

```
transactions                 the built order + lifecycle status (unique ref_id)
order_requests               the inbound request exactly as received
providers                    partner integrations (code, route, status_route, method)
provider_operator_maps       operator ⇄ provider with priority + integration code
provider_route_maps          route string → provider, used by the worker
operators / countries        catalogue
clients / users              API consumers and auth
balance_info                 per-partner float balance, refreshed on a schedule
transaction_event_logs       every step of every transaction
transaction_trace_summary    SQL view: per-transaction timeline for support
worker_app_status            worker heartbeat
```

`transaction_trace_summary` being a **view** rather than an application query
is deliberate: support tooling and ad-hoc investigation both need the same
timeline, and a view means there is one definition of it rather than two that
drift.

## Structure walkthrough

`app/Http/Controllers/` splits into three kinds of controller: the client entry
point (`OrderRequestController`), the inbound webhook (`WebhookController`),
and per-partner vendor controllers the worker calls internally.
`app/Integrations/` holds the `ProviderHandler` interface and its
implementations — the only place partner response shapes are understood.
`app/Repositories/` is a full interface/implementation split so no controller
holds a query. `app/Traits/` carries the cross-cutting behaviour that would
otherwise be copied into every vendor controller — lifecycle logging, callback
re-verification, message processing.
