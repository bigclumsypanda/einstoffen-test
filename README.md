# Invoice webhook

CodeIgniter 4, MariaDB, Redis, Mockoon (FakeUPS) and Mailpit.

## Run locally

Requires Docker Engine and Docker Compose v2. Ports 8080, 8082, 8083, 8025, 1025, 3306 and 6379 must be available.

```bash
cp env .env
docker compose build
docker compose run app sh -c "composer install && php spark app:migrate"
docker compose up -d
```

**Services**:

- **Webhook**: `http://localhost:8080/webhooks/invoices`
- **Captured emails** (**Mailpit**): `http://localhost:8025`
- **FakeUPS API** (**Mockoon**): `http://localhost:8083`
- **phpMyAdmin**: `http://localhost:8082`

## How the webhook works and interacts with the shipment API

An external system sends a request to our webhook, and we validate it. Then, in a transaction, we create a customer, their data, and an invoice on our side.
We try to respond to this system as quickly as possible and not keep it waiting for our response.
The request to the external postal API is made in a separate job in the queue.
Once we have a provisional shipment ID, we can send the user an email with the tracking number.


## Send a webhook

```bash
curl -i http://localhost:8080/webhooks/invoices \
  -H 'Content-Type: application/json' \
  --data '{
    "event": "invoice.created",
    "invoice_id": "INV-2026-00123",
    "customer": {
      "id": "CUST-4711",
      "name": "Muster Optik GmbH",
      "email": "kontakt@musteroptik.example"
    },
    "amount": 249.90,
    "currency": "CHF",
    "status": "open",
    "due_date": "2026-10-15",
    "created_at": "2026-09-08T10:15:00Z"
  }'
```

Expected response: **202 Accepted**. Inspect the shipment in Mockoon and the customer email in Mailpit after the worker runs. Invalid JSON or contract fields return **400**.

Deletion is idempotent:

```bash
curl -i http://localhost:8080/webhooks/invoices \
  -H 'Content-Type: application/json' \
  --data '{"event":"invoice.deleted","invoice_id":"INV-2026-00123"}'
```

## Verify and operate

Start the stack if it is not running:

```bash
docker compose up -d
```

The tests use the separate `ci4_test` database. Create it before running the test suite:

```bash
docker compose exec -T db mariadb -uroot -prootpassword < docker/mariadb/init/01-test-database.sql
docker compose exec app composer test -- --no-coverage
docker compose exec app composer phpcs
docker compose exec app composer phpstan
```
