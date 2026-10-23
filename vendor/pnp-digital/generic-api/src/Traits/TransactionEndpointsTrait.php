<?php

namespace Pipeandpiper\API\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use Pipeandpiper\API\Serializers\PNPJSONAPISerializer;
use Pipeandpiper\API\Transformers\NullTransformer;
use Throwable;

/**
 * Provides support for staging multiple API mutation requests and committing them
 * together inside a single database transaction.
 *
 * Each authenticated user may have exactly one active API transaction. Requests
 * are staged in the Laravel cache until committed, rolled back, or expired.
 *
 * GET requests are not staged and continue to execute normally while a transaction
 * is active.
 *
 * @property bool $withTransactions Whether individual API operations should create their own database transactions.
 */
trait TransactionEndpointsTrait
{
    /**
     * Number of seconds an API transaction may remain staged before expiring.
     */
    private const int API_TRANSACTION_TTL = 10;

    /**
     * Indicates that staged API requests are currently being replayed during commit.
     *
     * This prevents replayed mutation requests from being added back into the
     * transaction queue.
     */
    protected bool $committingApiTransaction = false;

    /**
     * Get the cache key for the current authenticated user's staged API transaction.
     *
     * @return string Cache key unique to the authenticated user.
     */
    private function transactionCacheKey(): string
    {
        return 'generic-api:transaction:' . Auth::id();
    }

    /**
     * Get the cache lock key for the current authenticated user's staged API transaction.
     *
     * The lock prevents concurrent requests from overwriting changes to the staged
     * transaction queue.
     *
     * @return string Cache lock key unique to the authenticated user.
     */
    private function transactionLockKey(): string
    {
        return $this->transactionCacheKey() . ':lock';
    }

    /**
     * Start a staged API transaction for the current authenticated user.
     *
     * Only one transaction may be active for a user at any given time. The transaction
     * is automatically discarded by the cache after API_TRANSACTION_TTL seconds if it
     * has not been committed or rolled back.
     *
     * @param Request $request Current API request.
     * @param PNPJSONAPISerializer $serializer API response serializer.
     * @param Manager $manager Fractal resource manager.
     * @return Item Success response indicating that the transaction has started.
     * @throws ValidationException If the user already has an active transaction.
     */
    public function transactionStart(Request $request, PNPJSONAPISerializer $serializer, Manager $manager): Item
    {
        $expires = now()->addSeconds(self::API_TRANSACTION_TTL);

        if (!Cache::add($this->transactionCacheKey(), ['expires' => $expires, 'requests' => []], $expires)) {
            throw ValidationException::withMessages(['transaction' => ['A transaction is already active']]);
        }

        return new Item(['response' => 'Transaction started'], new NullTransformer());
    }

    /**
     * Roll back the current authenticated user's staged API transaction.
     *
     * No database transaction exists until commit, so rollback simply discards all
     * staged requests from the cache.
     *
     * @param Request $request Current API request.
     * @param PNPJSONAPISerializer $serializer API response serializer.
     * @param Manager $manager Fractal resource manager.
     * @return Item Success response indicating that the transaction has been discarded.
     * @throws ValidationException If the user has no active transaction.
     */
    public function transactionRollback(Request $request, PNPJSONAPISerializer $serializer, Manager $manager): Item
    {
        if (!Cache::has($this->transactionCacheKey())) {
            throw ValidationException::withMessages(['transaction' => ['No transaction is active']]);
        }

        Cache::forget($this->transactionCacheKey());

        return new Item(['response' => 'Transaction rolled back'], new NullTransformer());
    }

    /**
     * Determine whether the current authenticated user has an active staged API transaction.
     *
     * @return bool True when an active transaction exists.
     */
    protected function hasApiTransaction(): bool
    {
        return Cache::has($this->transactionCacheKey());
    }

    /**
     * Add an API mutation request to the current authenticated user's staged transaction.
     *
     * The queue is updated under a cache lock so that concurrent requests cannot
     * overwrite one another. The original transaction expiry is retained when the
     * updated queue is written back to the cache.
     *
     * @param Request $request API mutation request to stage.
     * @param string $detailMode API detail mode used by the request.
     * @param string $route API resource or custom endpoint route.
     * @param string $model Laravel model for the endpoint route.
     * @return Item Success response indicating that the request has been queued.
     * @throws ValidationException If the transaction expires or otherwise ceases to
     *                             exist before the request can be queued.
     */
    protected function queueApiTransaction(Request $request, string $detailMode, string $route, string $model): Item
    {
        $key = $this->transactionCacheKey();

        /** @var Item $result */
        $result = Cache::lock($this->transactionLockKey(), 2)->block(2, function() use ($key, $request, $detailMode, $route, $model) {
            /**
             * @var array{expires: Carbon,requests: list<array{method: string,detail_mode: string,route: string,input: array<array-key, mixed>}>}|null $transaction
             */
            $transaction = Cache::get($key);

            if ($transaction === null) {
                throw ValidationException::withMessages(['transaction' => ['No transaction is active']]);
            }

            $transaction['requests'][] = [
                'method' => $request->method(),
                'detail_mode' => $detailMode,
                'route' => $route,
                'model' => $model,
                'input' => $request->all(),
                'revision' => $this->transactionRevision($model),
            ];

            Cache::put($key, $transaction, $transaction['expires']);
            return new Item(['response' => 'Queued, commit transaction to apply'], new NullTransformer());
        });

        return $result;
    }

    /**
     * Commit the current authenticated user's staged API transaction.
     *
     * All queued requests are replayed through the normal API controller inside one
     * database transaction. Individual API transaction wrappers are disabled while
     * replaying the queue so that the complete set of mutations forms a single
     * transaction.
     *
     * Results are returned in the same order in which the corresponding API requests
     * were queued.
     *
     * If any queued request throws an exception, the database transaction is rolled
     * back and the exception is allowed to propagate. The staged transaction remains
     * in the cache until explicitly rolled back or until its original expiry time.
     *
     * @param Request $request Current API request, including the authenticated user.
     * @param PNPJSONAPISerializer $serializer API response serializer.
     * @param Manager $manager Fractal resource manager.
     * @return Item Results from the committed requests, in queue order.
     * @throws ValidationException If the user has no active transaction.
     * @throws Throwable If execution of a queued API request fails.
     */
    public function transactionCommit(Request $request, PNPJSONAPISerializer $serializer, Manager $manager): Item
    {
        $key = $this->transactionCacheKey();

        /**
         * @var array{expires: Carbon, requests: list<array{method: string,detail_mode: string,route: string,model: string,revision: int,input: array<array-key, mixed>}>}|null $transaction
         */
        $transaction = Cache::get($key);

        if ($transaction === null) {
            throw ValidationException::withMessages(['transaction' => ['No transaction is active']]);
        }

        foreach ($transaction['requests'] as $queued) {
            if ($this->transactionRevision($queued['model']) !== $queued['revision']) {
                throw ValidationException::withMessages([
                    'transaction' => [
                        'A resource used by this transaction has changed since it was staged'
                    ],
                ]);
            }
        }

        /** @var list<mixed> $results */
        $results = [];
        $originalWithTransactions = $this->withTransactions;

        try {
            $this->committingApiTransaction = true;
            $this->withTransactions = false;

            DB::transaction(function () use (&$results, $transaction, $request) {
                /**
                 * @var array{method: string, detail_mode: string, route: string, input: array<array-key, mixed>} $queued
                 */
                foreach ($transaction['requests'] as $queued) {
                    $queuedRequest = Request::create('/api/v' . GENERIC_API_VERSION . '/' . $queued['route'], $queued['method'], $queued['input']);
                    $queuedRequest->setUserResolver(fn() => $request->user());
                    $route = $queued['detail_mode'] === 'basic' ? $queued['route'] : $queued['detail_mode'] . '/' . $queued['route'];
                    $response = $this->__invoke($queuedRequest, new PNPJSONAPISerializer(), $route);
                    /** @var array{data: mixed} $responseData */
                    $responseData = $response->getData(true);
                    $results[] = $responseData['data'];
                }
            });
        } finally {
            $this->committingApiTransaction = false;
            $this->withTransactions = $originalWithTransactions;
        }

        Cache::forget($key);
        return new Item($results, new NullTransformer());
    }

    /**
     * Get the cache key used to track the current revision of an API model.
     *
     * @param string $model Fully qualified model class name.
     * @return string Cache key for the model revision.
     */
    private function transactionRevisionKey(string $model): string
    {
        return 'generic-api:revision:' . $model;
    }

    /**
     * Get the current revision of an API model.
     *
     * @param string $model Fully qualified model class name.
     * @return int Current model revision, or zero if the model has not yet been modified.
     */
    private function transactionRevision(string $model): int
    {
        /** @var int|float|string|bool|null $revision */
        $revision = Cache::get($this->transactionRevisionKey($model), 0);
        return (int) $revision;
    }

    /**
     * Increment the revision of an API model following a successful mutation.
     *
     * @param string $model Fully qualified model class name.
     * @return void
     */
    protected function incrementTransactionRevision(string $model): void
    {
        $key = $this->transactionRevisionKey($model);
        if (!Cache::add($key, 1)) {
            Cache::increment($key);
        }
    }
}