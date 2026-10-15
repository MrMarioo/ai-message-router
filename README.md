# AI Message Router

Proof of concept of a microservice that takes a free-form message from an employee, interprets it with a locally
running LLM (Ollama) and forwards it by email to the right department. The decision is made by an **AI agent** that
calls a tool (`send_department_email`) on its own. The email is captured by Mailpit with the `Reply-To` header set
to the original sender.

Stack: **PHP 8.4 / Laravel 13 / `laravel/ai`** · **Ollama** (`qwen2.5:3b`, CPU) · **Mailpit** · **Scramble** (OpenAPI).

## Initial setup

Requirements: Docker with Compose v2, ~3 GB of free disk space for the model weights, free ports `8080` and `8025`.

-   Clone the repository and start the environment:

```bash
git clone <repository-url> wskz && cd wskz
docker compose up -d
```

-   The first start builds the API image and downloads the model (~2 GB), which takes a few minutes depending on the
    connection. Follow the progress with:

```bash
docker compose logs -f ollama-init    # ends with "Model qwen2.5:3b is ready."
docker compose ps                     # wskz should be "healthy", wskz-ollama-init "exited (0)"
```

The `web` container starts only after the model has been pulled and warmed up, so a healthy `wskz` container means the
API is ready to take requests.

| Service                            | URL                                  |
| ---------------------------------- | ------------------------------------ |
| API                                | http://localhost:8080                |
| OpenAPI documentation (Swagger UI) | http://localhost:8080/api/v1/docs    |
| OpenAPI specification (JSON)       | http://localhost:8080/api/v1/docs.json |
| Mailpit (captured emails)          | http://localhost:8025                |

## Usage

-   Send a message:

```bash
curl -s -X POST http://localhost:8080/api/v1/messages \
  -H 'Content-Type: application/json' \
  -d '{"email": "jan.nowak@example.com", "message": "Nie działa mi komputer"}'
```

-   Response (7–12 s on an 8-core CPU, up to ~20 s for the first request after a start):

```json
{
  "data": {
    "department": "help-desk@example.com",
    "subject": "Komputer nie działa",
    "routed_by": "agent",
    "agent_reply": "Message received and forwarded to the help-desk@example.com department."
  }
}
```

-   Open Mailpit: the email is addressed to `help-desk@example.com` with `Reply-To: jan.nowak@example.com`. The same
    check through the Mailpit API:

```bash
curl -s 'http://localhost:8025/api/v1/messages?limit=1' \
  | python3 -c 'import json,sys; m=json.load(sys.stdin)["messages"][0]; print(m["To"][0]["Address"], m["ReplyTo"][0]["Address"])'
```

Other messages worth trying: `Chciałbym zgłosić urlop na jutro` (kadry), `Nie działa Wi-Fi na drugim piętrze` (it),
`Chcę polecić znajomego do rekrutacji` (human-resources), `Gdzie mogę zaparkować rower?` (other).

`scripts/smoke.sh` sends ten such messages and verifies the recipient and `Reply-To` of each one in Mailpit.

## How it works

```
POST /api/v1/messages {email, message}
  └─ MessageController ── RouteMessageRequest (validation)
       └─ MessageRouter
            └─ RouteMessageAgent (laravel/ai, provider: ollama)
                 │  instructions: department list + rules, "call the tool exactly once"
                 └─ tool: send_department_email {to, subject, summary}
                      └─ Mail::to(department)->send(RoutedMessageMail)   Reply-To = sender
                                └─ SMTP → Mailpit
```

-   `app/Enums/Department.php` – the five addresses with a description of what each department handles. Single
    source of truth for the tool schema (`enum` in JSON Schema), the agent instructions and this README.
-   `app/Ai/Agents/RouteMessageAgent.php` – the `laravel/ai` agent. Temperature 0, at most 3 steps, 180 s timeout.
-   `app/Ai/Tools/SendDepartmentEmail.php` – the agent's only tool. Validates the address (anything outside the list
    goes to `other@`), sends at most one email per request and remembers where it sent it, so the application reads
    the decision from the tool instead of parsing the model's text.
-   `app/Services/Routing/MessageRouter.php` – ties it together. When the model finishes without calling the tool it
    gets one stricter retry. When that fails too, the message goes deterministically to `other@example.com` and the
    response carries `routed_by: "fallback"`. A connection problem with Ollama results in HTTP 502.

### Routing rules (from the agent instructions)

| Department                    | Scope                                                                                        |
| ----------------------------- | -------------------------------------------------------------------------------------------- |
| `kadry@example.com`           | personnel and payroll administration: leave, sick leave, contracts, salary, certificates    |
| `human-resources@example.com` | recruitment, onboarding, trainings, benefits, workplace conflicts and complaints             |
| `help-desk@example.com`       | user support: computer or printer not working, passwords, access, software                   |
| `it@example.com`              | infrastructure: network, VPN, servers, security incidents, new hardware, system permissions  |
| `other@example.com`           | everything else or too vague to classify                                                     |

## Architecture decisions

| Decision                   | Choice                                                                                    | Why                                                                                                                                                   |
| -------------------------- | ----------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| Framework and SDK          | Laravel 13 + the official `laravel/ai`                                                    | native Ollama provider, tool calling, `Agent::fake()` for tests without a model                                                                        |
| Processing                 | synchronous in the request                                                                | one request = one email, no queue and no worker; easiest to verify in a PoC                                                                           |
| Model                      | `qwen2.5:3b`                                                                              | small (1.9 GB), supports tool calling, acceptable on CPU; switchable through `OLLAMA_MODEL`                                                             |
| Resilience to a small model | address `enum` in the schema, guard against a second call, one retry, fallback to `other@` | a 3B model sometimes skips the tool call; the API never fails because of it, it marks the fallback explicitly instead                                  |
| Mail catcher               | Mailpit instead of MailHog                                                                | MailHog has been unmaintained since 2020; Mailpit has a REST API used by the smoke test. The task allows "a similar tool"                              |
| OpenAPI                    | `dedoc/scramble`                                                                          | generates the specification from the FormRequest and attributes, no hand-written YAML; UI at `/api/v1/docs`                                            |
| Database                   | SQLite inside the container                                                               | `laravel/ai` needs its conversation tables; no extra container required                                                                               |
| API image                  | code copied into the image, `composer install` at build time                              | works on a machine without PHP and Composer; no bind mounts                                                                                           |
| Readiness                  | `ollama-init` (pull + warm-up) as a dependency of `web`                                   | `docker compose up -d` finishes with an environment that is ready for a request, not just started                                                     |
| GPU                        | optional override                                                                         | CPU by default, because not every machine has the NVIDIA Container Toolkit                                                                             |

## Configuration

Variables read by `docker compose` (put them in `.docker/.env`, see `.docker/.env.example`, or export them):

| Variable                | Default      | Description                                                            |
| ----------------------- | ------------ | ---------------------------------------------------------------------- |
| `OLLAMA_MODEL`          | `qwen2.5:3b` | model pulled at start and used by the agent; must support tools        |
| `WEB_EXTERNAL_PORT`     | `8080`       | API port on the host                                                   |
| `MAILPIT_EXTERNAL_PORT` | `8025`       | Mailpit UI port on the host                                            |

-   Switching the model (pulls the new one, `web` restarts with the new configuration):

```bash
OLLAMA_MODEL=qwen3:4b docker compose up -d
```

-   NVIDIA GPU (requires the NVIDIA Container Toolkit on the host):

```bash
docker compose -f docker-compose.yml -f .docker/docker-compose.gpu.yml up -d
```

## Local Development

-   Unit and feature tests do not need Ollama: the agent is replaced with `RouteMessageAgent::fake()` and mail with
    `Mail::fake()`. Pint and Larastan (level 7) run in CI as well:

```bash
docker compose exec web php artisan test
docker compose exec web composer lint:check
docker compose exec web composer analyse
```

-   End-to-end smoke test (needs the running environment):

```bash
scripts/smoke.sh                                            # default ports
scripts/smoke.sh http://localhost:18080 http://localhost:18025
```

## What I'd improve with more time

-   **Asynchronous processing**: a queue with a worker and a `202 Accepted` + status endpoint, so the HTTP request
    does not wait 10 s for the model.
-   **Larger model behind a flag**: `qwen3:8b` classifies borderline `help-desk`/`it` and `kadry`/`human-resources`
    cases more reliably, at the cost of response time on CPU.
-   **Authentication and rate limiting**: the endpoint is open, which is fine for a PoC only.
-   **Observability**: persist each routing decision (agent reply, tool call, duration) for auditing and prompt tuning.
