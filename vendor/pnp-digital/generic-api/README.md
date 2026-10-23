# Laravel Generic API Package

This package adds support for generic API endpoints for use with apps, etc. Will add CRUD endpoints for all models in the
system plus a login and logout endpoint, user registration and typescript typings.

Supports laravel 10 and later with PHP 8.2 or later.

------------------------

# Adding the package to your laravel project

Adding the package to the project is simple and will take most of the heavy lifting away from the application.

### Installation Summary

1. Add the following to `composer.json`:
   ```json
    "repositories": [
        {
            "type": "vcs",
            "url":  "git@github.com:pnp-digital/generic-api.git",
            "no-api": true
        }
    ],
   ```
2. Ensure you have a public key added which can access this SSH repository
3. Issue a composer require command: ```composer require pnp-digital/generic-api:dev-main```
4. API endpoints can then be accessed at `/api/v1/<routename>` e.g. `/api/v1/users`. For further details, please see below.

## Basic vs. Extended results

By default, all API calls return `basic` result types. In summary, this means that there are no recursive parts to the returned data, just simple flat arrays. This is useful when you just want the basics of information.

You can instead request `extended` results, which will return related data in each API result. This recursion is limited to one level deep.

To explicitly request `basic` data, specify it in your URL, for example `/api/v1/basic/users` and to explicitly request `extended` data do the same but with `extended` in the URL, for example `/api/v1/extended/users`.

> **Warning**  
> Requesting `extended` for a `POST` or `PUT` is not supported and will generate an error.

------------------------

# API endpoints

Each API endpoint is named based upon the model it refers to. For example `/api/v1/users` refers to the `App\Models\User` model.
The following methods are supported on each endpoint unless [disabled at the model level](#restricting-access-to-endpoints).

**The design of the way the endpoints works is similar to [RESTFUL API design](https://restfulapi.net/http-methods/) instead
of trying to do everything via `POST` like our older API calls.**

## GET
Issue a `GET` request to fetch records. Your view of the data may be restricted by the `apiFilter()` method.

### Search Queries

You can add filters to the URL parameters, e.g. `/api/v1/users?first_name=Freddy&last_name=Kreuger`

Each URL parameter you specify must be the name of a database field, as visible in the database.
You can prefix a database field with a table name, e.g. users.first_name. Multiple parameters will combine using `AND`
in the SQL, e.g. the example URL above would do something equivalent to
```sql
SELECT * FROM users WHERE first_name = 'Freddy' AND last_name = 'Kreuger'
````
Please note the example above is greatly simplified for readability.

#### Search Operators

You can specify the following operators in brackets after a field name to specify a query operator:


| SQL Operator | Query Suffix    | Notes                                                                              |
|--------------|-----------------|------------------------------------------------------------------------------------|
| =            | (equals)        |                                                                                    |
| >            | (greater)       |                                                                                    |
| <            | (less)          |                                                                                    |
| <=           | (lessequals)    |                                                                                    |
| >=           | (greaterequals) |                                                                                    |
| !=           | (notequals)     |                                                                                    |
| LIKE         | (like)          | The % symbols will not be added for you. Add them where required to the value.     |
| NOT LIKE     | (notlike)       | The % symbols will not be added for you. Add them where required to the value.     |
| IN           | (in)            | Specify the list of choices in the value of the paramter as a comma separated list |
| NOT IN       | (notin)         | Specify the list of choices in the value of the paramter as a comma separated list |

#### Searching all fields

You can search all fillable fields in the object (not child objects or linked objects!) by specifying the field name
as '`*`'. This will perform an `OR` match against all fields in the `fillable` array of the model.

#### Searching a list of fields

You can separate a list of field names with , in the query, to search all fields in the list. Only the end of the list
needs an operator, if necessary, which will apply to the whole list as a group. For example:

```
field1,field2,field3[like]=%abc%
```

will search field1, field2, and field3 using the LIKE operator for 'abc'.

### Pagination

API results obtained by `GET` are paginated, with a default of 50 results per page. You can specify the page number,
and number of items per page by passing any of the following optional URL parameters:

| URL Parameter | Meaning                       |
|---------------|-------------------------------|
| page          | Current page number to return |
| page_size     | Number of records per page    |

Pagination and sorting information is attached to all returned results and is formatted as in the example below:
```json
{
    "pagination": {
        "sort": "id",
        "page_size": 50,
        "page": 1,
        "total_pages": 1
    }
}
```

### Sorting

You can sort results obtained by `GET` by specifying the `sort` URL parameter. It will be used as a comma separated
list of fields to sort by. This will affect pagination.

## POST
Issue a `POST` request to add one record. The field names should be identical those within the database.
The `id` field should never be included when adding a new record. Data relating to the model fields should be a `JSON` blob in the `POST` data.

### Multiple records

You may insert multiple records at once by passing an array of objects in the json, and using the endpoint /v1/multiple/tablename.
If you do this, each record will be individually inserted.

On completion, the data section of the json contains the newly added record, including any defaulted fields:

```json
{
    "data": [
        {
            "id": 5,
            "owner_id": 1,
            "issue_title": "Test issue",
            "issue_description": "full description",
            "plot_id": 3,
            "issue_status": "In Review",
            "created_at": "2023-09-15 11:17:48",
            "created_by": 1,
            "updated_at": "2023-09-15 11:17:48",
            "updated_by": 1,
            "deleted_at": null,
            "deleted_by": null
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

## PUT
Issue a `PUT` request to modify one record. The `id` field should always be included and will be used to identify the record to modify.
The ID can be included as either a URL parameter or `JSON POST` data. Data relating to the model fields should be a `JSON` blob in the `PUT` data.

### Multiple records

You may update multiple records at once by passing an array of objects in the json, and using the endpoint /v1/multiple/tablename.
If you do this, each record will be individually updated, and each object must contain a defined ID field.

On completion, the data section of the json contains the newly added record, including any defaulted fields in a similar way to `POST`, above.

## DELETE
Issue a `DELETE` request to delete one record.  The `id` field should always be included and will be used to identify the record to delete. Any other fields are ignored.
The ID can be included as either a URL parameter or `JSON POST` data. Any other `POST` data is ignored.

### Multiple records

You may delete multiple records at once by passing an array of ids in the json, and using the endpoint /v1/multiple/tablename.
If you do this, all records will be deleted in one query.

On completion, the deleted `id` value is confirmed in the `data` section of the json:

```json
{
   "data": [
      {
         "id": 55
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

------------------------

# Backed Enums

Any PHP backed enums that you have inside your laravel application will be detected
and presented via the API endpoints like a read-only table. To detect and present
an enum to the generic-api, the table should be correctly named and placed within
the `App\Enums` namespace, e.g. `App\Enums\MyTypeEnum` is valid, and both `App\Classes\MyTypeEnum`
and `App\Enums\MyType` are not (the first is not within the correct namespace and the second
does not end in `Enum`).

Backed enums which are presented to the generic-api support **filtering**, **sorting**
and **pagination** just like a table, but will only support the `GET` method, as
the data is read-only.

Enumerations will show in the artisan `api:endpoints` command.

------------------------

# Filtering results server side

By implementing `Pipeandpiper\API\Interfaces\ApiFilterInterface`, you gain access to the following methods on any Eloquent
model.

For both `apiFilter` and `apiPostProcess` you can get the details of the currently logged-in user via `request()->user()`.

## apiFilter()
`apiFilter()` allows modification of the `Builder` eloquent query. To make use of it, you should implement the following interface method on your model:
```php
public function apiFilter(Builder $b): Builder
{
    return $b;
}
```

The function should accept the Eloquent builder being used to create the query, and can adjust this query as required, returning either the original query, or a modified form of it.
You can use this to restrict the API results to those visible by a specific user for example.

## apiPostProcess()
`apiPostProcess()` allows modification of the array contents after any pagination. To make use of it you should implement the following interface method on your model:

```php
public function apiPostProcess(array $a): array
{
    return $a;
}
```

The post process method takes an array of arrays, where each row and groups of named columns are contained within. You should return either the original parameter, or a modified form of it.
You can use this method to adjust the array values, for example if you wanted to reformat a value depending on a users permissions.

> **Important**
> You should not use this function to remove rows from the result set! If you do this, there may be less results in the data than the pagination indicates. To filter out results completely, use `apiFilter()`.

# Transforming input

You can implement a transformer that implements the `Pipeandpiper\API\Interfaces\InputTransformerInterface`, allowing you to adjust the input of an API call before it is processed. This can be used for example to upload a file and store the filename to a filename field.

For any `PUT` or `POST` request, the input will be passed through your transformer if it derives from
`InputTransformerInterface`, and is in an array of transformers in your model in the public const `API_TRANSFORMERS`, for example:

If a transformer throws an exception, this exception will be passed to the API consumer.

```php
use Pipeandpiper\API\Interfaces\ApiFilterInterface;

class MyModel implements ApiFilterInterface
{
    public const API_TRANSFORMERS = [ IssuePhotoApiTransformer::class ];
}
```
Below is an example of a transformer which is used to save an uploaded file, replacing its form field with the uploaded URL to the file:

```php
<?php

namespace App\Transformers;

use App\Traits\FileFieldBase64Trait;
use Illuminate\Validation\ValidationException;
use Pipeandpiper\API\Interfaces\InputTransformerInterface;

class IssuePhotoApiTransformer implements InputTransformerInterface
{
    use FileFieldBase64Trait;

    private const ALLOWED_TYPES = [
        "image/jpg",
        "image/jpeg",
        "image/tif",
        "image/tiff",
    ];

    private const MAX_FILE_SIZE = 1024 * 1024 * 60;

    /**
     * Called before the row is inserted or updated, allowing for the data to be changed.
     * Throw to abort the insert/update before it occurs.
     * 
     * @inheritDoc
     * @throws ValidationException
     */
    public function transform(array $input): array
    {
        $input["issue_photo_file"] = $this->saveFromBase64(
            $input["issue_photo_file"],
            "issue_photo_file",
            self::ALLOWED_TYPES,
            self::MAX_FILE_SIZE,
            "issues",
            "jpg"
        );
        return $input;
    }
  
    /**
     * This method is called AFTER the row is updated or inserted.
     * Note that the insert or update, the transform() call, and this call
     * are wrapped inside a transaction, so throwing from here can roll back
     * the update/insert.   
     * @param array $input Array form of inserted/updated data 
     * @param Model $model Updated model
     * @return void
     */
    public function after($input, Model $model): void
    {
    }
}
```

------------------------

# Authentication

Authentication is performed via [Laravel Sanctum](https://laravel.com/docs/10.x/sanctum) and will integrate with
[Laravel Fortify](https://laravel.com/docs/10.x/fortify) at the application level. This package provides four
authentication-related endpoints:

## POST /api/v1/login
Posting to this endpoint with JSON containing a username, password, and optionally a device name will attempt to log in
the user. For example:

```json
{
    "email": "user@pipeandpiper.co.uk",
    "password": "myc00lp455w0rd1!",
    "device_name": "iFruit Pro"
}
```

Upon successful login, an API token is returned, along with all the details of the user who is logging in.
This API token should be used in the `Authorisation` header of further requests:

```json
{
   "data": [
      {
         "id": 1,
         "email": "hello@pipeandpiper.co.uk",
         "company_id": 3,
         "email_verified_at": null,
         "two_factor_confirmed_at": null,
         "first_name": "P&P",
         "last_name": "Developers",
         "phone": null,
         "mobile": "07123 456789",
         "role": null,
         "is_enabled": true,
         "is_admin": true,
         "is_builder": false,
         "is_super": true,
         "last_login": null,
         "logins": 1,
         "created_by": 1,
         "notification_token": "d4VuIhRpy09hgAY4Cw8oCI:APA91bEgwWIwFg28iA0ZZoLxtRQhlEDyla74YKoXMT6pz6oRo-vupx9n1iVB4348AjPy09CBZUnQgKpwtuyhDDfmQAVPl52NNx6Yt1alIvl9QN4ScmokpebCGoeqflqYd4zISvDyOLS8",
         "created_at": "2023-08-03 10:36:48",
         "updated_at": "2023-08-11 14:24:59",
         "updated_by": 1,
         "deleted_at": null,
         "deleted_by": null,
         "token": "26|rCoC66CQfjOteGWcbplzPBzzUX4EAXpWhpZuRMHW"
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

## POST /api/v1/logout
If logged in, calling this endpoint will log out the user, destroying their token.

## POST /api/v1/register
This API endpoint will register a new user and log them in, returning a token. All fields required for the eloquent User
model of the application should be specified, except for ID, is_super, and is_admin which will be stripped out or defaulted
to unprivileged values, for example:

```json
{
    "email": "newreg5@pipeandpiper.co.uk",
    "password": "XXXX",
    "first_name": "New",
    "last_name": "User 5",
    "device_name": "Nokia 3310 :-)",
    "app": 1,
    "app_version": "0.0.0"
}
```

> **Important**
> To disable the ability to register new users via the API, set the environment variable `API_USER_REGISTRATION_DISABLED`
> to a truthy value in the application `.env` file.

Upon completion registration, full details of the user will be returned alongside the bearer token:

```json
{
   "data": [
      {
         "id": 1,
         "email": "hello@pipeandpiper.co.uk",
         "company_id": 3,
         "email_verified_at": null,
         "two_factor_confirmed_at": null,
         "first_name": "P&P",
         "last_name": "Developers",
         "phone": null,
         "mobile": "07123 456789",
         "role": null,
         "is_enabled": true,
         "is_admin": true,
         "is_builder": false,
         "is_super": true,
         "last_login": null,
         "logins": 1,
         "created_by": 1,
         "notification_token": "d4VuIhRpy09hgAY4Cw8oCI:APA91bEgwWIwFg28iA0ZZoLxtRQhlEDyla74YKoXMT6pz6oRo-vupx9n1iVB4348AjPy09CBZUnQgKpwtuyhDDfmQAVPl52NNx6Yt1alIvl9QN4ScmokpebCGoeqflqYd4zISvDyOLS8",
         "created_at": "2023-08-03 10:36:48",
         "updated_at": "2023-08-11 14:24:59",
         "updated_by": 1,
         "deleted_at": null,
         "deleted_by": null,
         "token": "26|rCoC66CQfjOteGWcbplzPBzzUX4EAXpWhpZuRMHW"
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

## POST /api/v1/resetpassword
This API endpoint will send the user an e-mail with a link to reset their password. The user's email address should be
provided as the single JSON parameter, for example:
```json
{
    "email": "hello@pipeandpiper.co.uk"
}
```

> **Important**
> To disable the ability to reset passwords via the API, set the environment variable `API_USER_PASSWORD_RESET_DISABLED`
> to a truthy value in the application `.env` file.

------------------------

# Commands

This package adds new commands to your laravel instance. These are:

## List API definitions

```
php artisan api:definitions
```

This will generate typescript typings for all API endpoints (basic non-recursive structures only) and output it to `storage/types/generated.d.ts`. For example:

![image](https://github.com/pipeandpiper/generic-api/assets/108274351/5f9517b2-ebcc-4589-8e5f-118961b0e6d1)


> **Important**
> This should be run in an environment with access to the database as it reverse-engineers the schema to generate these types, so run this for example within the docker container.

## List API endpoints

```
php artisan api:endpoints
```

This will output a list of all supported API endpoints in human-readable tabular form. Use this to quickly see what is available in the event you are new to the system.

For example:

![image](https://github.com/pipeandpiper/generic-api/assets/108274351/521d80da-c057-459f-b2f0-8c186e42ba9c)


------------------------

# API versioning
When making API calls, you can optionally send the value 'app_version' in your URL parameters. If you send this, it will be
compared against the `MINIMUM_APP_VERSION` environment value. This should be a [semver](https://semver.org/) style version string. If the version
of the API is greater than that sent, the API call will error and will not operate.

------------------------

# Restricting access to endpoints
You can restrict access to a model on a per-model, per method basis by setting the constant array `API_ALLOWED_METHODS`. For example:

```php
use Pipeandpiper\API\Enums\AllowedMethodEnum;
use Pipeandpiper\API\Interfaces\ApiFilterInterface;

class County extends Model implements ApiFilterInterface
{
    public const API_ALLOWED_METHODS = [ AllowedMethodEnum::READ ];
}
```

If this is not specified, the default is to allow all API methods. The `AllowedMethodEnum` value supports the following values:

> **Important**
> Explicitly setting this constant to an empty array will prevent all access to the endpoint.

```php
enum AllowedMethodEnum: string
{
    case CREATE = 'POST';
    case READ = 'GET';
    case UPDATE = 'PUT';
    case DELETE = 'DELETE';
}
```

------------------------

# Automatic model joins for enhanced GET results
To ensure that your models are automatically joined when a user requests `enhanced` details in a `GET` request, you should ensure the following is true:
* All models should have correct **foreign key relations in MySQL** where the key names are automatically created by laravel migrations
* All related types should have a `BelongsTo`, `HasMany` or `HasOne` relation, where the name of the function should match the singular form of the related table. For example if `posts` relates to `likes` there should be a relation for it called `likes()`.

For example:
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModelWithRelations extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [];

    protected $casts = [];

    public function company(): HasOne
    {
        return $this->hasOne(Company::class, 'id', 'company_id');
    }

    public function plots(): HasMany
    {
        return $this->hasMany(Plot::class, 'site_id', 'id');
    }
}
```

# Arbitrary API endpoints

If the endpoint you wish to add is not a model or an enum, you can attach `get`, `post`, `put` or `delete` methods to any
class and have this package pick it up and call it when the API endpoint is called. To do this you must place it under the
namespace `App\Endpoints` and the class must implement at least one of these interfaces:

| Interface Class                                        | Functionality                              |
|--------------------------------------------------------|--------------------------------------------|
| Pipeandpiper\API\Interfaces\ApiEndpointGetInterface    | Allows for GET requests to the endpoint    |
| Pipeandpiper\API\Interfaces\ApiEndpointPostInterface   | Allows for POST requests to the endpoint   |
| Pipeandpiper\API\Interfaces\ApiEndpointPutInterface    | Allows for PUT requests to the endpoint    |
| Pipeandpiper\API\Interfaces\ApiEndpointDeleteInterface | Allows for DELETE requests to the endpoint |

For each of these interfaces you should implement a method in your class called 'get', 'post', 'put', or 'delete'. The
parameters are the same for all four, and all four should return a fractal resource item:

```php
namespace App\Endpoints;

use Illuminate\Http\Request;
use League\Fractal\Resource\Item;
use Pipeandpiper\API\Interfaces\ApiEndpointGetInterface;
use Pipeandpiper\API\Serializers\PNPJSONAPISerializer;
use Pipeandpiper\API\Transformers\NullTransformer;

class Test implements ApiEndpointGetInterface
{
    /**
     * @inheritDoc
     */
    public function get(Request $request, PNPJSONAPISerializer $serializer, string $detailMode, int $page, int $pageSize, string $orderBy): Item
    {
        $arr = [
            [
                "hats" => 42,
                "clowns" => 8,
            ]
        ];
        return new Item($arr, new NullTransformer());
    }
}
```

If you wish to return non-success from the API endpoint, you should throw any exception derived from `Exception`. You can
use the serializer to set pagination of your result set, if you return a result set, using the `$pageSize`, `$orderBy` and
`$page` variables.

## Custom API token authentication

In addition to the built-in Sanctum authentication, the Generic API allows the consuming application to provide its own
bearer token authentication.

This can be used where an application manages API credentials itself, or where tokens are issued by another system.

To enable custom token authentication, implement `ApiTokenResolverInterface`:

```php
<?php

namespace App\Services;

use Pipeandpiper\API\Interfaces\ApiTokenResolverInterface;

class ApiTokenResolver implements ApiTokenResolverInterface
{
    public function resolveToken(string $token): ?int
    {
        // Validate the supplied token using application-specific logic.

        // Return the ID of the user represented by the token.
        return 123;

        // Return null if the token is invalid.
    }
}
```

Register the implementation with the Laravel service container:

```php
use App\Services\ApiTokenResolver;
use Pipeandpiper\API\Interfaces\ApiTokenResolverInterface;

public function register(): void
{
    $this->app->bind(ApiTokenResolverInterface::class, ApiTokenResolver::class);
}
```

The token is supplied using the normal HTTP bearer token header:

```http
Authorization: Bearer <token>
```

`resolveToken()` should return the ID of the user represented by the token, or `null` if the token is not valid.

The consuming application is responsible for all application-specific token behaviour, including how tokens are generated,
stored, expired and revoked. The Generic API only requires a valid token to resolve to a user ID.

Once resolved, the request is authenticated as that user. Existing API permissions, filtering, audit fields and other
behaviour based on the authenticated user therefore continue to apply normally.

Custom token authentication is optional. If no `ApiTokenResolverInterface` implementation is registered, the Generic
API continues to use its existing Sanctum authentication only.

# Transactions

The Generic API supports short-lived staged transactions for grouping multiple mutating API requests into a single database transaction.

A transaction is started using `POST /api/v1/transaction/start`. While a transaction is active for the authenticated user, subsequent `POST`, `PUT` and `DELETE` requests are staged in the Laravel cache rather than immediately applied. `GET` requests continue to operate normally.

The transaction can then be completed using `POST /api/v1/transaction/commit`, which replays all staged requests inside a single database transaction, or discarded using `POST /api/v1/transaction/rollback`.

Only one transaction may be active for an authenticated user at a time. Staged transactions expire after 10 seconds.

## Conflict detection and TOCTOU protection

Generic model mutations maintain a revision number for the affected model. `POST`, `PUT` and `DELETE` operations against the same model all update the same revision, including their `multiple` variants.

When a mutation is staged, the current revision of its model is stored with the queued request. Before a transaction is committed, the stored revisions are compared with the current revisions. If any model used by the transaction has changed since its request was staged, the entire transaction is rejected before any of its queued mutations are applied.

Revision tracking is deliberately model-level rather than row-level. For example, if a transaction stages changes to models C, D and E, and another API request changes any record represented by D before the transaction commits, the transaction will fail. A change to unrelated model F does not affect it.

This provides optimistic conflict detection and prevents the transaction being committed against unexpectedly changed state between staging and commit (TOCTOU).

The same mechanism covers overlapping staged transactions. Queuing requests does not itself change a model revision. When one transaction commits successfully, its mutations update the affected model revisions; another transaction which staged changes against any of those same model revisions will therefore be rejected when it subsequently attempts to commit.

Revision state is maintained in the Laravel cache and represents mutations performed through Generic API model endpoints. Code which modifies application data outside the Generic API does not participate in this revision tracking.

## Implementation notes

Staged requests are stored in the Laravel cache against the authenticated user. The original expiry time is retained as requests are added to the queue.

During commit, revision checks are performed before replay begins. The queued requests are then replayed through the normal API controller inside one `DB::transaction()`. Individual per-operation database transaction wrappers are disabled during replay so that the complete queue forms one atomic database transaction.

If any replayed request throws an exception, the database transaction is rolled back. The staged transaction remains available until it is explicitly rolled back or reaches its original expiry time.

The configured cache backend must support Laravel atomic locks. Cache locking is used to prevent concurrent requests for the same authenticated user from overwriting the staged transaction queue.

------------------------

# Idempotency

The Generic API supports optional [idempotency](https://cloud.google.com/discover/idempotency) protection for mutating requests (`POST`, `PUT` and `DELETE`).

Idempotency is enabled for an individual request by supplying the `X-Idempotency` header with a positive integer value:

```http
X-Idempotency: 42
```

The value is a sequence number belonging to the authenticated API consumer. The consumer may increment this value for each
mutating request it makes.

You can use any monotonically increasing positive integer value for `X-Idempotency`, but we recommend using
[Snowflake IDs](https://en.wikipedia.org/wiki/Snowflake_ID).

The header is optional. If `X-Idempotency` is not supplied, no idempotency processing is performed and the request follows
the normal API flow unchanged.

## Sequence handling

For each consumer, the API retains the most recently completed idempotent request.

When a request supplies an `X-Idempotency` value:

* If the value is greater than the last successfully processed value, the request is processed normally.
* If the value is equal to the last successfully processed value and the request is identical, the operation is not
* executed again and the response from the original request is returned.
* If the value is equal to the last successfully processed value but represents a different request, the request is rejected.
* If the value is lower than the last successfully processed value, the request is rejected.

Sequence numbers do not need to be consecutive. For example, after successfully processing sequence `10`, sequence `15` is
valid, but subsequent sequences `10` through `15` cannot be used for different requests.

The request method, path, query string and request body are hashed to identify whether a repeated sequence number represents
the same request. This allows an identical request to be retried safely without permitting the sequence number to be reused
for a different operation.

## Failed requests

A sequence number is only retained when the request completes successfully.

If processing fails before a successful response is produced, the sequence number is not consumed and the consumer may
retry the request using the same `X-Idempotency` value.

For example:

```text
POST /api/v1/customers
X-Idempotency: 42
    -> request fails

POST /api/v1/customers
X-Idempotency: 42
    -> request is attempted again
```

If the first request succeeds but its response does not reach the consumer, the consumer may also retry the same request:

```text
POST /api/v1/customers
X-Idempotency: 42
    -> request succeeds, response is lost

POST /api/v1/customers
X-Idempotency: 42
    -> original response is returned
       POST is not executed again
```

## Storage and locking

Idempotency state is stored using the Laravel Cache implementation. The Generic API does not create or require any database
tables for idempotency.

Only the latest sequence number, request hash and response are retained for each consumer. Persistence therefore follows
the behaviour of the cache driver configured by the consuming application. For example, when using Laravel's file cache,
idempotency state is retained on disk and survives application restarts.

Clearing or otherwise losing the configured application cache will also clear the remembered idempotency state. This
facility is intended to protect consumers against accidental request replay and ambiguous HTTP retries; it does not
provide an absolute exactly-once execution guarantee.

Cache locking is used while an idempotent request is processed to prevent two concurrent requests for the same consumer
from independently passing the sequence check.

The configured cache backend must therefore support Laravel atomic locks if idempotency is to be used.

Idempotency state is deliberately cache-backed rather than durable application data. Clearing or losing the application
cache also clears the remembered idempotency sequence. This facility is intended to protect consumers against accidental
request replay and ambiguous HTTP retries; it does not provide an absolute exactly-once execution guarantee.

## Concurrent requests

Consumers using `X-Idempotency` should issue mutating requests in sequence.

For example, if requests `51` and `52` are issued concurrently and `52` reaches the API first, `52` may be processed
first and `51` will subsequently be rejected as an older sequence.

Consumers which require concurrent independent mutation requests may omit `X-Idempotency` from those requests.

## Frontend API

Idempotency is also available through `/api/frontend`.

The same `X-Idempotency` behaviour applies to `POST`, `PUT` and `DELETE` requests made through the frontend API. The
authenticated session identifies the consumer where no bearer token is present.
