# Generic API

This document describes the public interface provided by the Generic
API. It is intended for developers consuming the API and does not cover
how the API is implemented or configured internally.

The examples in this document use generic resources such as `users`,
`clients` and `customers`. The resources available to you, and the
methods allowed on each resource, depend on the system you are accessing
and your permissions within it.

The API follows REST-style conventions and uses JSON for request and
response data.

------------------------------------------------------------------------

# Base URL

API endpoints are available beneath:

```text
/api/v1/
```

Each resource has its own endpoint. For example:

```text
/api/v1/users
/api/v1/clients
/api/v1/customers
```

The exact list of available resources is supplied with the API for the
system you are integrating with.

------------------------------------------------------------------------

# Authentication

API requests require an API token.

The token should be supplied as a bearer token in the `Authorization` header:

```http
Authorization: Bearer <token>
```

Authentication tokens can be obtained using the login endpoint.

------------------------------------------------------------------------

# Response format

Successful requests return a JSON response containing the result in
`data`.

A typical response is:

```json
{
    "data": [
        {
            "id": 123,
            "name": "Example Customer"
        }
    ],
    "errors": [],
    "status": "success",
    "message": "",
    "pagination": {
        "sort": "id",
        "page_size": 50,
        "page": 1,
        "total_pages": 1
    }
}
```

Errors use the same general response structure, with `status` set to
`error` and details supplied in `errors` and `message`.

------------------------------------------------------------------------

# Basic and extended results

By default, `GET` requests return `basic` results. These contain the
fields belonging directly to the requested resource without recursively
including related resources.

For example:

```http
GET /api/v1/users
```

You can explicitly request basic results using:

```http
GET /api/v1/basic/users
```

Where supported, `extended` results include related data one level deep:

```http
GET /api/v1/extended/users
```

For example, an extended `customer` result may include its related
`client` object or other related records.

Extended results are intended for retrieval only and are not supported
for `POST` or `PUT` requests.

------------------------------------------------------------------------

# GET

Use `GET` to retrieve records.

```http
GET /api/v1/customers
```

The records returned are subject to the permissions and data access
restrictions of the authenticated user.

## Retrieving a specific record

Records can be filtered by their ID:

```http
GET /api/v1/customers?id=123
```

The same filtering mechanism can be used with other fields.

## Filtering

Add fields as URL query parameters to filter the result:

```http
GET /api/v1/customers?name=Example
```

Multiple filters are combined:

```http
GET /api/v1/customers?client_id=10&status=active
```

Filters operate on fields exposed by the resource.

## Search operators

The following operators can be added to a field name:

| Operator              | Suffix            | Example                          |
|-----------------------|-------------------|----------------------------------|
| Equal                 | `(equals)`        | `id(equals)=123`                 |
| Greater than          | `(greater)`       | `id(greater)=100`                |
| Less than             | `(less)`          | `id(less)=200`                   |
| Less than or equal    | `(lessequals)`    | `id(lessequals)=200`             |
| Greater than or equal | `(greaterequals)` | `id(greaterequals)=100`          |
| Not equal             | `(notequals)`     | `status(notequals)=inactive`     |
| LIKE                  | `(like)`          | `name(like)=%Example%`           |
| NOT LIKE              | `(notlike)`       | `name(notlike)=%Example%`        |
| IN                    | `(in)`            | `status(in)=active,pending`      |
| NOT IN                | `(notin)`         | `status(notin)=inactive,deleted` |

For `LIKE` and `NOT LIKE`, wildcard `%` characters are not added
automatically.

## Searching multiple fields

A comma-separated list of fields can be searched together:

```http
GET /api/v1/customers?name,email(like)=%example%
```

This searches both `name` and `email` using the supplied operator and
value.

Where supported, `*` can be used to search all searchable fields:

```http
GET /api/v1/customers?*(like)=%example%
```

## Pagination

`GET` results are paginated. The default page size is 50 records.

Use `page` and `page_size` to control pagination:

```http
GET /api/v1/customers?page=2&page_size=100
```

Pagination information is included in the response:

```json
{
    "pagination": {
        "sort": "id",
        "page_size": 100,
        "page": 2,
        "total_pages": 8
    }
}
```

## Sorting

Use the `sort` parameter to specify the fields used to sort the result:

```http
GET /api/v1/customers?sort=name
```

Multiple sort fields can be supplied as a comma-separated list:

```http
GET /api/v1/customers?sort=client_id,name
```

Sorting is applied before pagination.

------------------------------------------------------------------------

# POST

Use `POST` to create a new record.

```http
POST /api/v1/customers
Content-Type: application/json
```

Example request:

```json
{
    "name": "Example Customer",
    "email": "customer@example.com",
    "client_id": 10
}
```

Do not supply an `id` when creating a record. The ID is allocated by the
system.

On success, the `data` section contains the newly created record,
including its allocated ID and any values defaulted by the system.

Only fields which are writable for the resource may be supplied.

## Creating multiple records

Where supported, multiple records can be created in one request using
the `multiple` endpoint:

```http
POST /api/v1/multiple/customers
Content-Type: application/json
```

Example:

```json
[
    {
        "name": "Customer One",
        "client_id": 10
    },
    {
        "name": "Customer Two",
        "client_id": 10
    }
]
```

The records are processed as one API operation.

------------------------------------------------------------------------

# PUT

Use `PUT` to modify an existing record.

Only the fields which need to be changed need to be supplied. Fields
which are not supplied are left unchanged.

The `id` identifies the record to modify:

```http
PUT /api/v1/customers
Content-Type: application/json
```

Example:

```json
{
  "id": 123,
  "name": "Updated Customer Name"
}
```

This updates the `name` of customer `123` without requiring the complete
customer record to be submitted.

The ID may be supplied in the request data or as a URL parameter.

On success, the `data` section contains the updated record.

## Updating multiple records

Where supported, multiple records can be updated in one request:

```http
PUT /api/v1/multiple/customers
Content-Type: application/json
```

Each object must contain an `id`:

```json
[
    {
        "id": 123,
        "status": "active"
    },
    {
        "id": 124,
        "status": "inactive"
    }
]
```

The records are processed as one API operation.

------------------------------------------------------------------------

# DELETE

Where permitted, use `DELETE` to delete a record.

```http
DELETE /api/v1/customers?id=123
```

The ID may instead be supplied in the request data.

Any additional fields supplied with a delete request are ignored.

## Deleting multiple records

Where supported, multiple records can be deleted in one request:

```http
DELETE /api/v1/multiple/customers
Content-Type: application/json
```

Supply an array of IDs:

``` json
[
    123,
    124,
    125
]
```

------------------------------------------------------------------------

# Read-only resources

Some resources are read-only and support `GET` but not `POST`, `PUT` or
`DELETE`.

These may include lookup values, enumerations and other system-defined
data.

Read-only resources support the same filtering, sorting and pagination
facilities as other `GET` endpoints.

------------------------------------------------------------------------

# Permissions and data access

Not every resource supports every API method.

For example, an API may permit:

| Resource    | GET | POST | PUT | DELETE |
|-------------|-----|------|-----|--------|
| `users`     | Yes | No   | No  | No     |
| `clients`   | Yes | No   | No  | No     |
| `customers` | Yes | Yes  | Yes | No     |

The actual methods available are defined by the system providing the
API.

Access to records may also be restricted according to the authenticated
user. For example, a user with access to one client may only receive
customers belonging to that client.

Applications consuming the API should not assume that the existence of a
resource means that every operation is available.

------------------------------------------------------------------------

# TypeScript type definitions

TypeScript type definition files can be supplied for the resources
exposed by the API.

These describe the basic, non-recursive structures returned by the API
and can be used by TypeScript applications to provide compile-time type
checking and editor assistance when consuming API data.

For example:

```typescript
let customer: Customer;
```

The supplied type definitions describe resource data structures only.
API permissions and the methods available for each resource should be
taken from the API documentation supplied for the system.

------------------------------------------------------------------------

# API versioning

The API version forms part of the endpoint URL:

```text
/api/v1/
```

Clients may also supply an application version where required by the
system. If a minimum supported client version is enforced, requests from
older client versions may be rejected.

Consumers should use the documented API version and should not assume
that behaviour introduced by a later API version is available in an
earlier one.

------------------------------------------------------------------------

# Content type

Request bodies should be sent as JSON unless the documentation for a
particular endpoint states otherwise:

```http
Content-Type: application/json
```

Responses are returned as JSON.

------------------------------------------------------------------------

# Example

The following is a simple sequence for retrieving, creating and updating
customer data.

Retrieve active customers:

```http
GET /api/v1/customers?status=active&sort=name
Authorization: Bearer <token>
```

Create a customer:

```http
POST /api/v1/customers
Authorization: Bearer <token>
Content-Type: application/json
```

```json
{
    "name": "Example Customer",
    "email": "customer@example.com",
    "client_id": 10
}
```

The response contains the new record and its allocated ID:

```json
{
    "data": [
        {
            "id": 123,
            "name": "Example Customer",
            "email": "customer@example.com",
            "client_id": 10
        }
    ],
    "errors": [],
    "status": "success",
    "message": "",
    "pagination": {
        "sort": "id",
        "page_size": 50,
        "page": 1,
        "total_pages": 1
    }
}
```

Update only the customer's name:

```http
PUT /api/v1/customers
Authorization: Bearer <token>
Content-Type: application/json
```

```json
{
    "id": 123,
    "name": "Example Customer Ltd"
}
```

No other customer fields need to be resubmitted.

------------------------------------------------------------------------

# Available resources

The resources and operations available for a particular system are
supplied separately. This allows each system to expose only the data and
operations appropriate to that system while retaining the same API
conventions described in this document.

------------------------------------------------------------------------

# Transactions

The API supports short-lived transactions when several changes need to be applied together.

Start a transaction using:

```http
POST /api/v1/transaction/start
Authorization: Bearer <token>
```

After a transaction has been started, `POST`, `PUT` and `DELETE` requests made by the same authenticated API consumer are queued rather than applied immediately. `GET` requests are not queued and continue to return the current stored data.

Apply all queued changes using:

```http
POST /api/v1/transaction/commit
Authorization: Bearer <token>
```

The queued changes are applied together as a single database transaction. If one of the queued operations fails, none of the changes are committed.

To discard the queued changes instead:

```http
POST /api/v1/transaction/rollback
Authorization: Bearer <token>
```

Only one transaction may be active for an authenticated API consumer at a time. A transaction must be committed or rolled back within 10 seconds; otherwise it expires.

## Conflicting changes and TOCTOU protection

Transactions detect conflicting API changes made while requests are waiting to be committed.

When a change is queued, the API records the current revision of the affected resource model. At commit, these revisions are checked again before any queued changes are applied. If another API request has changed a model used by the transaction in the meantime, the transaction is rejected and none of its queued changes are applied.

Conflict detection is at resource model level rather than individual record level. For example, suppose a transaction queues changes to resources C, D and E. If another API consumer changes D before the transaction is committed, the transaction will be rejected. A change to unrelated resource F does not cause a conflict.

This prevents a transaction from applying changes based on state which unexpectedly changed between the time the operation was staged and the time it was used (a time-of-check to time-of-use, or TOCTOU, conflict).

The same protection applies when both consumers are using transactions. Queuing a change does not itself create a conflict. If two transactions contain changes to the same resource model, the transaction which commits first changes that model's revision. The other transaction will then be rejected when it attempts to commit.

If a transaction is rejected because of a conflict, retrieve the current data and construct a new transaction based on the new state.

Transactions detect changes made through Generic API model endpoints. Changes made directly to the underlying database or elsewhere within the application are outside this conflict detection mechanism.

------------------------------------------------------------------------

# Idempotency

`POST`, `PUT` and `DELETE` requests support optional protection against accidental duplicate requests ([idempotency](https://cloud.google.com/discover/idempotency)).

To use this facility, supply a positive integer in the `X-Idempotency` header.

You can use any increasing positive integer value for `X-Idempotency`, but we recommend using [Snowflake IDs](https://en.wikipedia.org/wiki/Snowflake_ID).

```http
POST /api/v1/customers
Authorization: Bearer <token>
Content-Type: application/json
X-Idempotency: 2100895883112632320
```

For each subsequent mutating request, use a higher value:

```http
PUT /api/v1/customers
Authorization: Bearer <token>
Content-Type: application/json
X-Idempotency: 2100895883112632341
```

```http
POST /api/v1/customers
Authorization: Bearer <token>
Content-Type: application/json
X-Idempotency: 2100895883112632375
```

The numbers must increase, but do not need to be consecutive.

For example, after successfully using `X-Idempotency: 10`, using `X-Idempotency: 15` is valid. A later request using a value lower than `15` will be rejected.

## Retrying a request

If you are unsure whether a request completed, you may retry the same request using the same `X-Idempotency` value.

If the original request completed successfully, the operation will not be performed again. The response from the original request will be returned instead.

For example:

```http
POST /api/v1/customers
X-Idempotency: 20
```

If the response is lost, the identical request may be sent again with:

```http
POST /api/v1/customers
X-Idempotency: 20
```

The customer will not be created twice. If request `20` had already completed, its original response is returned.

If the original request failed and did not complete successfully, the number is not consumed and the request may be attempted again using the same value.

An `X-Idempotency` value cannot be reused for a different request. Attempting to use the same value for different request data or a different operation will be rejected.

## Concurrent requests

When using `X-Idempotency`, mutating requests should be sent in sequence.

Do not send requests with consecutive idempotency values concurrently. If request `22` reaches the API before request `21`, request `21` may subsequently be rejected because a higher sequence number has already been processed.

## Optional use

`X-Idempotency` is optional.

Consumers which do not require duplicate request protection may omit the header entirely. Requests without the header behave normally and do not participate in the idempotency sequence.

`GET` requests do not use `X-Idempotency`.

Idempotency is intended to protect against accidental replay and allow safe retries when the result of a request is uncertain. It should not be treated as an absolute exactly-once execution guarantee.
