# Laravel Housing API

An asynchronous REST API for importing housing offers from suppliers, searching for the cheapest available offer, and creating safe reservations.

## Tech Stack

* PHP 8.3
* Laravel 12
* MySQL 8.4
* Redis 8
* Laravel Queue
* Eloquent ORM
* PHPUnit
* Docker / Docker Compose

## Features

* Asynchronous offer imports
* Import idempotency
* Supplier and property management
* Upsert of existing offers
* Cheapest available offer per property
* Filtering by dates, guests, and city
* Database-level sorting and pagination
* Safe concurrent reservations
* Request validation
* Feature tests

## Project Structure

```text
.
├── docker/
│   ├── mysql/
│   │   └── init/
│   └── php/
│       └── Dockerfile
├── src/
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Jobs/
│   │   └── Models/
│   ├── database/
│   │   ├── factories/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/
│   │   └── api.php
│   └── tests/
└── docker-compose.yml
```

## Requirements

* Docker
* Docker Compose

The application itself does not require PHP, Composer, MySQL, or Redis to be installed on the host machine.

## Installation

Clone the repository:

```bash
git clone git@github.com:MaksymPushylin/laravel-housing-api.git
cd laravel-housing-api
```

Start the containers:

```bash
docker compose up -d --build
```

Install PHP dependencies:

```bash
docker compose exec php composer install
```

Generate the application key:

```bash
docker compose exec php php artisan key:generate
```

Run migrations:

```bash
docker compose exec php php artisan migrate
```

Seed the database:

```bash
docker compose exec php php artisan db:seed
```

## Running the Application

Start Laravel's development server inside the PHP container:

```bash
docker compose exec php php artisan serve --host=0.0.0.0 --port=8000
```

The API will be available at:

```text
http://localhost:8000
```

## Queue Worker

Imports are processed asynchronously using Redis.

Start the queue worker in another terminal:

```bash
docker compose exec php php artisan queue:work redis
```

The import endpoint returns immediately with HTTP `202 Accepted`. The actual offer processing is performed by `ProcessImportJob`.

## API

### Create Import

```http
POST /api/imports
```

Example:

```json
{
    "supplier": "supplier-a",
    "external_import_id": "import-001",
    "sent_at": "2026-09-04 12:00:00",
    "offers": [
        {
            "external_id": "offer-001",
            "property": {
                "code": "property-001",
                "name": "Central Apartment",
                "city": "Kyiv"
            },
            "check_in": "2026-10-01",
            "check_out": "2026-10-05",
            "max_guests": 2,
            "price": 120.00,
            "currency": "USD",
            "available_units": 3,
            "expires_at": "2026-09-30 23:59:59"
        }
    ]
}
```

Response:

```http
202 Accepted
```

```json
{
    "id": 1,
    "status": "pending"
}
```

### Get Import Status

```http
GET /api/imports/{import}
```

Returns the current processing status and progress:

```json
{
    "id": 1,
    "supplier": "supplier-a",
    "external_import_id": "import-001",
    "sent_at": "2026-09-04T12:00:00.000000Z",
    "status": "completed",
    "total_offers": 10,
    "processed_offers": 10,
    "error": null,
    "created_at": "2026-09-04T12:00:00.000000Z",
    "completed_at": "2026-09-04T12:01:00.000000Z"
}
```

### Search Properties

```http
GET /api/properties
```

Required parameters:

```text
check_in
check_out
guests
```

Optional parameters:

```text
city
page
per_page
sort_by
sort_dir
```

Example:

```text
/api/properties?check_in=2026-10-01&check_out=2026-10-05&guests=2&city=Kyiv&sort_by=price&sort_dir=asc
```

For each property the API returns the cheapest currently available offer matching the search criteria.

Filtering, selecting the cheapest offer, sorting, and pagination are performed at the database level.

### Create Reservation

```http
POST /api/offers/{offer}/reservations
```

Example:

```json
{
    "client_reference": "booking-001",
    "customer_name": "John Doe",
    "customer_email": "john@example.com"
}
```

Response:

```http
201 Created
```

## Idempotency

Import idempotency is enforced at the database level.

The `imports` table has a unique constraint on:

```text
supplier_id + external_import_id
```

Therefore, the same import cannot be created twice for the same supplier, even if two requests arrive concurrently.

When a duplicate import is received, the existing import is returned instead of creating and processing it again.

Offers use a unique constraint on:

```text
supplier_id + external_id
```

An existing offer from the same supplier is updated by a new import instead of creating a duplicate record.

## Concurrent Reservations

Reservations use a database transaction together with a row-level lock.

Before creating a reservation, the offer row is locked using `SELECT ... FOR UPDATE`.

The application then checks `available_units` and decrements it inside the same transaction.

Conceptually:

```text
Transaction
    |
    +-- lock offer row
    |
    +-- check available_units
    |
    +-- decrement available_units
    |
    +-- create reservation
    |
    +-- commit
```

This prevents two concurrent requests from successfully booking the last available unit.

`client_reference` is also protected by a unique database constraint to prevent duplicate client references.

## Import Processing

Import processing is performed by `ProcessImportJob`.

Each offer is processed inside its own database transaction:

1. Find or create the property by its unique code.
2. Create or update the supplier's offer.
3. Increment `processed_offers`.

The import status changes through:

```text
pending → processing → completed
                  ↓
                failed
```

The job is configured with:

```php
public int $tries = 1;
```

This prevents a failed partially processed import from being automatically replayed and incorrectly incrementing `processed_offers`.

## Database

Main entities:

```text
Supplier
   |
   +── Import
   |
   +── Offer ─── Property
           |
           └── Reservation
```

Important database constraints:

* `suppliers.code` — unique
* `properties.code` — unique
* `imports(supplier_id, external_import_id)` — unique
* `offers(supplier_id, external_id)` — unique
* `reservations.client_reference` — unique
* Foreign keys between related entities

## Tests

Run the test suite:

```bash
docker compose exec php php artisan test
```

Current test suite covers:

* Import creation and processing
* Supplier validation
* Duplicate imports
* Existing offer updates
* Import status endpoint
* Cheapest offer selection
* Successful reservations
* Reservations with no available units
* Duplicate client references
* Preventing a second reservation after the last unit is booked

## Notes

Currency conversion is outside the scope of this assignment. Offer prices are compared by their numeric value.

The project intentionally avoids unnecessary architectural complexity such as a full DDD/Clean Architecture implementation, since the assignment focuses on the API, database design, asynchronous processing, and concurrency handling.
