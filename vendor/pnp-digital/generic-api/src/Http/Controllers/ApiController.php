<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Pipeandpiper\API\Http\Controllers;

use App\Http\Controllers\Controller;
use BackedEnum;
use Exception;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use Pipeandpiper\API\Enums\AllowedMethodEnum;
use Pipeandpiper\API\Interfaces\ApiEndpointGetInterface;
use Pipeandpiper\API\Interfaces\ApiEndpointPostInterface;
use Pipeandpiper\API\Interfaces\ApiEndpointPutInterface;
use Pipeandpiper\API\Interfaces\ApiEndpointDeleteInterface;
use Pipeandpiper\API\Interfaces\ApiFilterInterface;
use Pipeandpiper\API\Interfaces\InputTransformerInterface;
use Pipeandpiper\API\Serializers\PNPJSONAPISerializer;
use Pipeandpiper\API\Traits\AuthenticationEndpointsTrait;
use Pipeandpiper\API\Transformers\NullTransformer;
use Pipeandpiper\Traits\RawRelationTrait;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use Symfony\Component\HttpFoundation\InputBag;
use Pipeandpiper\API\Traits\TransactionEndpointsTrait;

class ApiController extends Controller
{
    use AuthenticationEndpointsTrait, RawRelationTrait, TransactionEndpointsTrait;

    protected bool $withTransactions = true;

    /**
     * Handle generic API request, /api/v1/{route}, /api/v1/basic/{route} and /api/v1/extended/{route}
     *
     * @param Request $request
     * @param PNPJSONAPISerializer $serializer
     * @param string|callable|null $route
     * @return JsonResponse
     * @throws ValidationException on error in request
     */
    function __invoke(Request $request, PNPJSONAPISerializer $serializer, string|callable|null $route = null): JsonResponse
    {
        $detailMode = "basic";
        $resource = null;

        if ($route === null) {
            throw ValidationException::withMessages([
                'route' => ['No endpoint specified'],
            ]);
        }

        /** @var string $route */
        if (str_contains($route, "/")) {
            [$detailMode, $route] = explode("/", $route, 2);
            if ($detailMode === "public") {
                $detailMode = "basic";
            }
        }

        /** @var string $appVersion */
        $appVersion = $request->input('app_version', '0.0.0');
        /** @noinspection PhpUnusedLocalVariableInspection */
        $chickenLegsWithDucksFeetAndAWomansFaceToo = $request->input('app');

        /** @var string $minVersion */
        $minVersion = env("MINIMUM_APP_VERSION", "0.0.0");
        if (version_compare($appVersion, $minVersion) < 0) {
            throw ValidationException::withMessages([
                'email' => ['Minimum application version supported by this system is ' . $minVersion],
            ]);
        } elseif ($request->input('encryption_key')) {
            throw ValidationException::withMessages([
                'email' => ['Encryption key authentication for API is not supported'],
            ]);
        }

        /** @var array<string, mixed> $requestParameters */
        $requestParameters = $request->except(['app', 'app_version', 'app_size']);
        $pb = new InputBag($requestParameters);
        $request->request = $pb;
        $request->setJson($pb);

        $this->ensureIsNotRateLimited($request);

        $manager = new Manager();
        $manager->setSerializer($serializer);

        /** @phpstan-ignore function.alreadyNarrowedType */
        if (!is_string($route) && is_callable($route)) {
            $resource = $route($request, $serializer, $manager);
        } elseif (method_exists(static::class, $route)) {
            $resource = $this->$route($request, $serializer, $manager);
        } else {

            /* Find related model */
            $ns = "";
            foreach (["Models", "Endpoints"] as $namespace) {
                $model = "App\\$namespace\\" . Str::studly(Str::singular($route));
                if (class_exists($model)) {
                    $ns = $namespace;
                    break;
                }
                $model = "App\\$namespace\\" . Str::studly(Str::plural($route));
                if (class_exists($model)) {
                    $ns = $namespace;
                    break;
                }
            }

            /** @var class-string $model */
            $bypassAuth = false;
            try {
                $class = new ReflectionClass($model);
                $allowedMethods = $class->getConstant("API_ALLOWED_METHODS");
                if ($allowedMethods !== false) {
                    /** @var array<mixed> $allowedMethods */
                    $bypassAuth = in_array(AllowedMethodEnum::PUBLIC, $allowedMethods);
                }
            }
            catch (ReflectionException) {
            }

            if (!Auth::check() && !$bypassAuth) {
                throw ValidationException::withMessages([
                    'email' => ['Not authenticated'],
                ]);
            }
            if (!class_exists($model)) {
                $enumFound = false;
                foreach (["App\\Enums\\" . Str::studly(Str::singular($route)) . "Enum", "App\\Enums\\" . Str::studly(Str::singular($route))] as $enum) {
                    if (enum_exists($enum)) {
                        /** @var class-string<BackedEnum> $enum */
                        /* Enums only support GET obviously */
                        $enumFound = true;
                        if ($request->method() !== "GET") {
                            throw ValidationException::withMessages([
                                'email' => ['Method unsupported'],
                            ]);
                        }
                        try {
                            $class = new ReflectionClass($enum);
                            $allowedMethods = $class->getConstant("API_ALLOWED_METHODS");
                            if ($allowedMethods !== false) {
                                /** @var array<mixed> $allowedMethods */
                                if (!in_array(
                                    match ($request->method()) {
                                        "GET" => AllowedMethodEnum::READ,
                                        "POST" => AllowedMethodEnum::CREATE,
                                        "PUT" => AllowedMethodEnum::UPDATE,
                                        "DELETE" => AllowedMethodEnum::DELETE,
                                    },
                                    $allowedMethods
                                )) {
                                    throw ValidationException::withMessages([
                                        'email' => [
                                            sprintf(
                                                "Method %s Not allowed for endpoint %s",
                                                $request->method(),
                                                $enum
                                            )
                                        ],
                                    ]);
                                }
                            }
                        }
                        catch (ReflectionException) {
                        }
                        $resource = $this->getEnum($request, $serializer, $manager, $detailMode, $enum);
                        RateLimiter::clear($this->throttleKey($request));
                        return response()->json($manager->createData($resource));
                    }
                }
                if (!$enumFound) {
                    throw ValidationException::withMessages([
                        'email' => ['The provided route does not exist'],
                    ]);
                }
            }
            /* Find method for request type */
            if ($ns == "Models" && !method_exists(static::class, $request->method())) {
                throw ValidationException::withMessages([
                    'email' => ['Method unsupported'],
                ]);
            }
            if ($ns == "Endpoints" && !method_exists($model, $request->method())) {
                throw ValidationException::withMessages([
                    'email' => ['Method unsupported'],
                ]);
            }

            /**
             * Check permissions, if specified in the model
             */
            try {
                $class = new ReflectionClass($model);
                $allowedMethods = $class->getConstant("API_ALLOWED_METHODS");
                if ($allowedMethods !== false) {
                    /** @var array<mixed> $allowedMethods */
                    if (!in_array(
                        match ($request->method()) {
                            "GET" => AllowedMethodEnum::READ,
                            "POST" => AllowedMethodEnum::CREATE,
                            "PUT" => AllowedMethodEnum::UPDATE,
                            "DELETE" => AllowedMethodEnum::DELETE,
                            default => throw ValidationException::withMessages([
                                'email' => ['Method unsupported'],
                            ]),
                        },
                        $allowedMethods
                    )) {
                        throw ValidationException::withMessages([
                            'email' => [
                                sprintf(
                                    "Method %s Not allowed for endpoint %s",
                                    $request->method(),
                                    $model
                                )
                            ],
                        ]);
                    }
                }
            }
            catch (ReflectionException) {
            }

            if (!$this->committingApiTransaction && in_array($request->method(), ['POST', 'PUT', 'DELETE'], true) && $this->hasApiTransaction()) {
                $resource = $this->queueApiTransaction($request, $detailMode, $route, $model);
                RateLimiter::clear($this->throttleKey($request));
                return response()->json($manager->createData($resource));
            }

            /**
             * Call method for GET, POST, PUT, DELETE
             */
            if ($ns == "Models") {
                $resource = $this->{$request->method()}($request, $serializer, $manager, $detailMode, $model);
            } elseif ($ns == "Endpoints") {
                $m = new $model;
                if (
                    ($request->method() == "POST" && !($m instanceof ApiEndpointPostInterface)) ||
                    ($request->method() == "GET" && !($m instanceof ApiEndpointGetInterface)) ||
                    ($request->method() == "PUT" && !($m instanceof ApiEndpointPutInterface)) ||
                    ($request->method() == "DELETE" && !($m instanceof ApiEndpointDeleteInterface))
                ) {
                    throw ValidationException::withMessages([
                        'email' => [
                            sprintf(
                                "Method %s Not allowed for endpoint %s",
                                $request->method(),
                                $model
                            )
                        ],
                    ]);
                }
                /** @var int|float|string|bool|null $pageInput */
                $pageInput = $request->input('page');
                /** @var int|float|string|bool|null $pageSizeInput */
                $pageSizeInput = $request->input('page_size');
                $page = (int)($pageInput ?? 1);
                $pageSize = (int)($pageSizeInput ?? 50);
                $orderBy = $request->input('sort') ?? 'name';
                $resource = $m->{$request->method()}($request, $serializer, $detailMode, $page, $pageSize, $orderBy);
            }
        }
        RateLimiter::clear($this->throttleKey($request));

        /** @var \League\Fractal\Resource\ResourceInterface $resource */
        return response()->json($manager->createData($resource));
    }

    /**
     * @param Request $request
     * @param PNPJSONAPISerializer $serializer
     * @param Manager $manager
     * @param string $detailMode
     * @param class-string<BackedEnum> $model
     * @return Item
     * @throws ValidationException
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function getEnum(Request $request, PNPJSONAPISerializer $serializer, Manager $manager, string $detailMode, string $model): Item
    {
        /** @var int $page */
        $page = $request->input('page') ?? 1;
        /** @var int $pageSize */
        $pageSize = $request->input('page_size') ?? 50;
        /** @var string $orderBy */
        $orderBy = $request->input('sort') ?? 'name';

        if ($orderBy !== "name" && $orderBy !== "value") {
            throw ValidationException::withMessages([
                'email' => ['Invalid sort field'],
            ]);
        }

        $cases = ($model)::cases();
        $collection = new Collection();
        $n = 0;
        $parts = explode('\\', $model);
        $e = array_pop($parts);
        foreach ($cases as $case) {
            $collection->put($n++, [
                'name' => $case->name,
                'unit' => $e . '::' . $case->name,
                'value' => $case->value,
            ]);
        }

        $serializer->setPagination($page, $pageSize, $orderBy, $collection->count());

        $collection = $collection->sort(function($a, $b) use ($orderBy) {
            if ($a[$orderBy] == $b[$orderBy]) {
                return 0;
            }
            return ($a[$orderBy] > $b[$orderBy]) ? 1 : -1;
        });

        foreach ($request->except(['page','page_size', 'sort']) as $filter=>$value) {
            /** @var string $value */
            $collection = $collection->filter(function(array $containerValue) use ($filter, $value) {
                /** @var string $containerValueAtFilter */
                $containerValueAtFilter = $containerValue[$filter];
                return (strtolower($containerValueAtFilter) == strtolower($value));
            });
        }

        $arr = array_values($collection->forPage($page, $pageSize)->toArray());

        return new Item($arr, new NullTransformer());
    }

    /**
     * @param class-string<Model> $model
     * @param Model $mdl
     * @param Request $request
     * @param int|null $slice
     * @return Model
     * @throws Exception
     */
    protected function doPutInternal(string $model, mixed $mdl, Request $request, ?int $slice = null): mixed
    {
        /** @var array<string, mixed> $obj */
        $obj = $slice === null ? $request->all() : current($request->only($slice));
        $fields = $this->processAndRunTransformers($model, $mdl, $obj);
        $fields["updated_by"] = Auth::id();
        if (!isset($fields["updated_at"])) {
            $fields["updated_at"] = date("Y-m-d H:i:s");
        }
        /** @var Model $mdl */
        $mdl = ($model)::find($obj['id']);
        $mdl->updateOrCreate(["id" => $obj['id']], $fields);
        /** @var Model $mdl */
        $mdl = ($model)::find($obj['id']);
        $this->processAndRunPostTransformers($model, $mdl, $obj);
        return $mdl;
    }

    /**
     * @param class-string<Model> $model
     * @param Model $mdl
     * @param Request $request
     * @param int|null $slice
     * @return Model
     * @throws Exception
     */
    protected function doPostInternal(string $model, mixed $mdl, Request $request, ?int $slice = null): mixed
    {
        /** @var array<string, mixed> $obj */
        $obj = $slice === null ? $request->all() : current($request->only($slice));
        $fields = $this->processAndRunTransformers($model, $mdl, $obj);
        $fields["created_by"] = $fields["updated_by"] = Auth::id() ?? 1;
        if (!isset($fields["created_at"])) {
            $fields["created_at"] = date("Y-m-d H:i:s");
        }
        if (!isset($fields["updated_at"])) {
            $fields["updated_at"] = date("Y-m-d H:i:s");
        }
        $newRecord = $mdl->updateOrCreate(["id" => null], $fields);
        $this->processAndRunPostTransformers($model, $newRecord, $obj);
        return $newRecord;
    }

    /**
     * @param Request $request
     * @param PNPJSONAPISerializer $serializer
     * @param Manager $manager
     * @param string $detailMode
     * @param class-string<Model> $model
     * @return Item
     * @throws ReflectionException
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function get(Request $request, PNPJSONAPISerializer $serializer, Manager $manager, string $detailMode, string $model): Item
    {
        $mdl = new $model;
        $m = $mdl::query();
        if ($mdl instanceof ApiFilterInterface) {
            $m = $mdl->apiFilter($m);
        }
        /** @var int $page */
        $page = $request->input('page') ?? 1;
        /** @var int $pageSize */
        $pageSize = $request->input('page_size') ?? 50;
        /** @var string $orderBy */
        $orderBy = $request->input('sort') ?? $mdl->getTable() . '.id';
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        $serializer->setPagination($page, $pageSize, $orderBy, $mdl::count());
        foreach ($request->except(['page','page_size', 'sort']) as $filter=>$value) {
            /** @var string $value */
            if (preg_match('/^([\w,]+|\*)\((\w+)\)$/', $filter, $matches)) {
                [, $filter, $operator] = $matches;
                if ($filter === '*') {
                    $filter = $mdl->getFillable() ?? [$filter];
                } elseif (str_contains($filter, ',')) {
                    $filter = explode(',', $filter);
                } else {
                    $filter = [ $filter ];
                }
                $m->where(function($query) use ($mdl, $value, $filter, $operator) {
                    foreach ($filter as $f) {
                        if ($operator === 'in') {
                            $query->orWhereIn($mdl->getTable() . '.' . $f, explode(',', $value));
                        } elseif ($operator === 'notin') {
                            $query->orWhereNotIn($mdl->getTable() . '.' . $f, explode(',', $value));
                        } else {
                            $query->orWhere($mdl->getTable() . '.' . $f, match ($operator) {
                                'lt', 'less' => '<',
                                'gt', 'greater' => '>',
                                'lte', 'lessequals' => '<=',
                                'gte', 'greaterequals' => '>=',
                                'neq', 'notequals' => '!=',
                                'like' => 'LIKE',
                                'notlike' => 'NOT LIKE',
                                default => '=',
                            }, $value);
                        }
                    }
                });
            } else {
                if ($filter === '*') {
                    $filter = $mdl->getFillable() ?? [ $filter ];
                } elseif (str_contains($filter, ',')) {
                    $filter = explode(',', $filter);
                } else {
                    $filter = [ $filter ];
                }
                $m->where(function($query) use ($mdl, $value, $filter) {
                    foreach ($filter as $f) {
                        $query->orWhere($mdl->getTable() . '.' . $f, '=', $value);
                    }
                });
            }
        }
        $m = $this->restrictSoftDeletes($mdl, $m);
        if ($detailMode === "extended") {
            $m = $this->addAutoJoins($model, $mdl, $m);
        }
        $m->limit($pageSize)->offset($pageSize * ($page - 1))->orderBy($orderBy);
        /** @var array<int, array<string, mixed>> $arr */
        $arr = $m->get()->toArray();
        if ($mdl instanceof ApiFilterInterface) {
            $arr = $mdl->apiPostProcess($arr);
        }
        return new Item($arr, new NullTransformer());
    }

    /**
     * @param Request $request
     * @param PNPJSONAPISerializer $serializer
     * @param Manager $manager
     * @param string $detailMode
     * @param class-string<Model> $model
     * @return Item
     * @throws ValidationException
     * @noinspection PhpUnusedParameterInspection
     */
    private function post(Request $request, PNPJSONAPISerializer $serializer, Manager $manager, string $detailMode, string $model): Item
    {
        if ($detailMode === "extended") {
            throw ValidationException::withMessages([
                'route' => ['Cannot add a record using extended mode'],
            ]);
        } elseif ($detailMode === "multiple") {
            return $this->multiplePost($request, $serializer, $manager, $detailMode, $model);
        }
        if ($request->input('id')) {
            throw ValidationException::withMessages([
                'id' => ['Record ID cannot be specified with POST'],
            ]);
        }
        $mdl = new $model;
        $serializer->setPagination(1, 50, 'id', $mdl::count());
        $newRecord = new $model;
        if ($this->withTransactions) {
            DB::transaction(function () use (&$model, &$mdl, &$request, &$newRecord) {
                $newRecord = $this->doPostInternal($model, $mdl, $request);
            });
        } else {
            $newRecord = $this->doPostInternal($model, $mdl, $request);
        }
        $this->incrementTransactionRevision($model);
        return new Item([$newRecord->toArray()], new NullTransformer());
    }

    /**
     * @param Request $request
     * @param PNPJSONAPISerializer $serializer
     * @param Manager $manager
     * @param string $detailMode
     * @param class-string<Model> $model
     * @return Item
     * @throws ValidationException
     */
    private function multiplePost(Request $request, PNPJSONAPISerializer $serializer, Manager $manager, string $detailMode, string $model): Item
    {
        $returned = [];
        $mdl = new $model;
        $changeArray = $request->input();
        if (!is_array($changeArray) || !array_key_exists(0, $changeArray) || !is_array($changeArray[0])) {
            throw ValidationException::withMessages([
                'route' => ['Multiple POST must contain JSON array of objects'],
            ]);
        }
        if ($this->withTransactions) {
            DB::transaction(function () use (&$model, &$mdl, &$request, &$returned) {
                for ($n = 0; !empty($request->input((string)$n)); ++$n) {
                    $returned[] = $this->doPostInternal($model, $mdl, $request, $n);
                }
            });
        } else {
            for ($n = 0; !empty($request->input((string)$n)); ++$n) {
                $returned[] = $this->doPostInternal($model, $mdl, $request, $n);
            }
        }
        $serializer->setPagination(1, count($returned), 'id', count($returned));
        $this->incrementTransactionRevision($model);
        return new Item($returned, new NullTransformer());
    }

    /**
     * @param Request $request
     * @param PNPJSONAPISerializer $serializer
     * @param Manager $manager
     * @param string $detailMode
     * @param class-string<Model> $model
     * @return Item
     * @throws ValidationException
     */
    private function multiplePut(Request $request, PNPJSONAPISerializer $serializer, Manager $manager, string $detailMode, string $model): Item
    {
        $mdl = new $model;
        $changeArray = $request->input();
        if ($request->input("id") !== null) {
            throw ValidationException::withMessages([
                'route' => ['You cannot specify a top level single ID with multiple PUT'],
            ]);
        }
        if (!is_array($changeArray) || !array_key_exists(0, $changeArray) || !is_array($changeArray[0])) {
            throw ValidationException::withMessages([
                'route' => ['Multiple PUT must contain JSON array of objects'],
            ]);
        }
        $returned = [];
        if ($this->withTransactions) {
            DB::transaction(function () use (&$model, &$request, &$mdl, &$returned) {
                for ($n = 0; !empty($request->input((string)$n)); ++$n) {
                    /** @var array<string, mixed> $object */
                    $object = current($request->only($n));
                    if (!isset($object["id"])) {
                        throw ValidationException::withMessages([
                            'id' => ["Each record's ID must be specified for multiple PUT"],
                        ]);
                    }
                    $returned[] = $this->doPutInternal($model, $mdl, $request, $n);
                }
            });
        } else {
            for ($n = 0; !empty($request->input((string)$n)); ++$n) {
                /** @var array<string, mixed> $object */
                $object = current($request->only($n));
                if (!isset($object["id"])) {
                    throw ValidationException::withMessages([
                        'id' => ["Each record's ID must be specified for multiple PUT"],
                    ]);
                }
                $returned[] = $this->doPutInternal($model, $mdl, $request, $n);
            }
        }
        $this->incrementTransactionRevision($model);
        $serializer->setPagination(1, count($returned), 'id', count($returned));
        return new Item($returned, new NullTransformer());
    }

    /**
     * @param Request $request
     * @param PNPJSONAPISerializer $serializer
     * @param Manager $manager
     * @param string $detailMode
     * @param class-string<Model> $model
     * @return Item
     * @throws ValidationException
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function put(Request $request, PNPJSONAPISerializer $serializer, Manager $manager, string $detailMode, string $model): Item
    {
        if ($detailMode === "extended") {
            throw ValidationException::withMessages([
                'route' => ['Cannot update a record using extended mode'],
            ]);
        } elseif ($detailMode === "multiple") {
            return $this->multiplePut($request, $serializer, $manager, $detailMode, $model);
        }
        if (!$request->input('id')) {
            throw ValidationException::withMessages([
                'id' => ['Record ID must be specified for PUT'],
            ]);
        }
        $mdl = new $model;
        $m = $mdl::query();
        $m->where('id', $request->input('id'));
        $m = $this->restrictSoftDeletes($mdl, $m);
        $serializer->setPagination(1, 50, 'id', $mdl::count());
        if ($this->withTransactions) {
            DB::transaction(function () use (&$model, &$mdl, &$request) {
                $mdl = $this->doPutInternal($model, $mdl, $request);
            });
        } else {
            $mdl = $this->doPutInternal($model, $mdl, $request);
        }
        $this->incrementTransactionRevision($model);
        return new Item([$mdl->toArray()], new NullTransformer());
    }

    /**
     * @param Request $request
     * @param PNPJSONAPISerializer $serializer
     * @param Manager $manager
     * @param string $detailMode
     * @param class-string<Model> $model
     * @return Item
     * @throws ValidationException
     * @throws Exception
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function delete(Request $request, PNPJSONAPISerializer $serializer, Manager $manager, string $detailMode, string $model): Item
    {
        if ($detailMode === "extended") {
            throw ValidationException::withMessages([
                'route' => ['Cannot delete a record using extended mode'],
            ]);
        } elseif ($detailMode === "multiple") {
            return $this->deleteMultiple($request, $serializer, $manager, $detailMode, $model);
        }
        if (!$request->input('id')) {
            throw ValidationException::withMessages([
                'id' => ['Record ID must be specified for DELETE'],
            ]);
        }
        /** @var Model $mdl */
        $mdl = ($model)::find($request->input("id"));
        if ($mdl === null) {
            throw ValidationException::withMessages([
                'id' => ['Record is already deleted'],
            ]);
        }
        $serializer->setPagination(1, 50, 'id', $mdl::count());
        /** @var int $id */
        $id = $mdl->getKey();
        $this->processAndRunDeleteTransformers($model, [$id]);
        $mdl->delete();
        $this->incrementTransactionRevision($model);
        return new Item($mdl->toArray(), new NullTransformer());
    }

    /**
     * @param class-string<Model> $model
     * @throws Exception
     */
    private function deleteMultiple(Request $request, PNPJSONAPISerializer $serializer, Manager $manager, string $detailMode, string $model): Item
    {
        $returned = [];
        $serializer->setPagination(1, 50, 'id', 1);
        $query = $model::query()->whereIn("id", $request->input("id"));
        $items = $query->get();
        $this->processAndRunDeleteTransformers($model, $items->modelKeys());
        $query->delete();
        $this->incrementTransactionRevision($model);
        return new Item($items->toArray(), new NullTransformer());
    }

    /**
     * If the user is currently rate limited throw an exception and emit the Lockout event.
     *
     * @param Request $request
     * @throws ValidationException if rate limited
     */
    private function ensureIsNotRateLimited(Request $request): void
    {
        $throttleKey = $this->throttleKey($request);

        if (!RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($throttleKey);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Generate unique key for the current user based on email address (if logged in) and IP
     *
     * @param Request $request
     * @return string throttle key
     */
    protected function throttleKey(Request $request): string
    {
        /** @var string $email */
        $email = $request->input('email');
        return Str::transliterate(Str::lower($email) . '|' . $request->ip());
    }

    /**
     * Do basic processing on API input, and run transformers if there are any against the data.
     *
     * @param class-string<Model> $model Fully qualified model name
     * @param Model $mdl Modal instance
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     * @throws ValidationException
     * @throws Exception
     */
    private function processAndRunTransformers(string $model, Model $mdl, array $fields): array
    {
        $casts = $mdl->getCasts();
        foreach ($fields as $field => &$value) {
            if (!empty($value) && is_string($value) && array_key_exists($field, $casts) && $casts[$field] === "hashed") {
                $value = Hash::make($value);
            }
        }
        if ($mdl instanceof ApiFilterInterface) {
            /** @var iterable<class-string> $transformers */
            $transformers = $model::API_TRANSFORMERS;
            foreach ($transformers as $transformClass) {
                $transformer = new $transformClass;
                if (!($transformer instanceof InputTransformerInterface)) {
                    throw ValidationException::withMessages(
                        ['error' => ['Transformer is not instance of InputTransformerInterface']]
                    );
                }
                $fields = $transformer->transform($fields);
            }
        }
        return $fields;
    }

    /**
     * @param class-string<Model> $model
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function processAndRunPostTransformers(string $model, Model $mdl, array $fields): array
    {
        if ($mdl instanceof ApiFilterInterface) {
            /** @var iterable<class-string> $transformers */
            $transformers = $model::API_TRANSFORMERS;
            foreach ($transformers as $transformClass) {
                $transformer = new $transformClass;
                if (!($transformer instanceof InputTransformerInterface)) {
                    throw ValidationException::withMessages(
                        ['error' => ['Transformer is not instance of InputTransformerInterface']]
                    );
                }
                $transformer->after($fields, $mdl);
            }
        }
        return $fields;

    }

    /**
     * Run delete authorisation through registered input transformers.
     *
     * @param class-string<Model> $model
     * @param array<int, int> $ids
     * @throws Exception
     */
    private function processAndRunDeleteTransformers(string $model, array $ids): void
    {
        $mdl = new $model;
        if (!($mdl instanceof ApiFilterInterface)) {
            return;
        }

        /** @var iterable<class-string> $transformers */
        $transformers = $model::API_TRANSFORMERS;

        foreach ($transformers as $transformClass) {
            /** @var InputTransformerInterface $transformer */
            $transformer = new $transformClass;
            $transformer->beforeDelete($ids, $model);
        }
    }

    /**
     * Using mysql relations and reflection, create automatic one-to-many and many-to-one
     * joins for a query using eloquent with(). This ensures we get as much useful data as
     * possible in each call to an API endpoint.
     *
     * @template TModel of Model
     * @param class-string<Model> $model Fully qualified model name
     * @param Model $mdl Model instance
     * @param Builder<TModel> $builder Builder for query to add with() to
     * @return Builder<TModel> Modified builder
     * @throws ReflectionException on failure to reflect the model
     */
    private function addAutoJoins(string $model, Model $mdl, Builder $builder): Builder
    {
        /**
         * First get raw MySQL relations from information schema for any int fields ending in _id
         */
        $casts = $mdl->getCasts();
        foreach ($casts as $field => $type) {
            if ($type === "int" && str_ends_with($field, '_id')) {
                $relations = $this->getRawRelation(Str::Plural(strtolower($mdl->getTable())), $field);
                if (!empty($relations)) {
                    /** @var string $relationTable */
                    $relationTable = $relations["table"];
                    $builder->with(Str::singular($relationTable));
                }
            }
        }
        /**
         * Now use reflection
         */
        $class = new ReflectionClass($model);
        $methods = $class->getMethods();
        /**
         * Iterate all traits looking for any methods returning HasMany or HasOne
         */
        $t = $class->getTraits();
        if (!empty($t)) {
            foreach ($t as $trait) {
                $traitMethods = $trait->getMethods();
                foreach ($traitMethods as $method) {
                    /** @var ReflectionIntersectionType|ReflectionNamedType|ReflectionUnionType|null $returnType */
                    $returnType = $method->getReturnType();
                    if ($this->isRelation($returnType)) {
                        $builder->with($method->getName());
                    }
                }
            }
        }
        /**
         * Look for direct methods in the model class returning HasMany type
         */
        foreach ($methods as $method) {
            /** @var ReflectionIntersectionType|ReflectionNamedType|ReflectionUnionType|null $returnType */
            $returnType = $method->getReturnType();
            if ($this->isRelation($returnType)) {
                $builder->with($method->getName());
            }
        }
        return $builder;
    }


    /**
     * Returns true if the return value type is a laravel eloquent relation
     * @param ReflectionIntersectionType|ReflectionNamedType|ReflectionUnionType|null $returnTypes
     * @return bool
     */
    private function isRelation(null|ReflectionIntersectionType|ReflectionNamedType|ReflectionUnionType $returnTypes): bool
    {
        return ($returnTypes instanceof ReflectionNamedType && (is_subclass_of($returnTypes->getName(), Relation::class)));
    }


    /**
     * Check if a model uses the SoftDeletes trait, if it does, filter the query to only
     * show rows where deleted_at is null.
     *
     * @param Model $mdl model instance
     * @param Builder<Model> $m builder instance
     * @return Builder<Model> modified builder
     */
    private function restrictSoftDeletes(Model $mdl, Builder $m): Builder
    {
        if (in_array(SoftDeletes::class, class_uses_recursive($mdl))) {
            $m->whereNull('deleted_at');
        }
        return $m;
    }
}
