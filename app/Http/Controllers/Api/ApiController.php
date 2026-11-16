<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Base for the mobile API.
 *
 * A Wedding is the tenant boundary: every record belongs to exactly one, and
 * access is decided by the same rules the web dashboard uses
 * (User::canAccessWedding / canEditWedding), so the two can never disagree.
 */
abstract class ApiController extends Controller
{
    /** Cached per request once resolved. */
    protected ?Wedding $wedding = null;

    /**
     * The request the cached wedding belongs to. Laravel reuses controller
     * instances across requests in long-lived processes (tests, Octane), so
     * the cache must never outlive the request that filled it.
     */
    private ?Request $weddingRequest = null;

    protected function user(Request $request): User
    {
        return $request->user();
    }

    /**
     * The wedding this request is operating on.
     *
     * Taken from the `X-Wedding-Id` header or a `wedding_id` parameter, and
     * falling back to the user's only wedding when there is no ambiguity.
     */
    protected function wedding(Request $request): Wedding
    {
        if ($this->wedding && $this->weddingRequest === $request) {
            return $this->wedding;
        }
        $this->wedding = null;
        $this->weddingRequest = $request;

        $user = $this->user($request);
        $id = $request->header('X-Wedding-Id') ?: $request->input('wedding_id');

        if ($id) {
            $wedding = Wedding::find((int) $id);

            if (! $wedding) {
                abort(404, 'Wedding not found.');
            }

            if (! $user->canAccessWedding($wedding)) {
                abort(403, 'You do not have access to this wedding.');
            }

            return $this->wedding = $wedding;
        }

        $accessible = $user->accessibleWeddings();

        if ($accessible->isEmpty()) {
            abort(409, 'Your account is not linked to a wedding yet.');
        }

        // With several weddings the client must say which one it means.
        if ($accessible->count() > 1) {
            abort(400, 'Specify which wedding with the X-Wedding-Id header.');
        }

        return $this->wedding = $accessible->first();
    }

    /**
     * Guard a write. Couples have view-only access, matching the web's
     * EnsureWeddingAccess middleware.
     */
    protected function authorizeEdit(Request $request): Wedding
    {
        $wedding = $this->wedding($request);

        if (! $this->user($request)->canEditWedding($wedding)) {
            abort(403, 'Your account has view-only access to this wedding.');
        }

        return $wedding;
    }

    /**
     * Constrain a query to the active wedding. Every listing goes through
     * here so a record from another couple can never be returned.
     */
    protected function scoped(Request $request, Builder $query, string $column = 'wedding_id'): Builder
    {
        return $query->where($column, $this->wedding($request)->id);
    }

    /**
     * Find a record inside the active wedding, or 404.
     */
    protected function findScoped(Request $request, string $modelClass, int $id)
    {
        $record = $modelClass::where('wedding_id', $this->wedding($request)->id)
            ->whereKey($id)
            ->first();

        if (! $record) {
            abort(404, class_basename($modelClass).' not found.');
        }

        return $record;
    }

    protected function ok($data = null, array $meta = []): JsonResponse
    {
        $payload = ['success' => true, 'data' => $data];

        if ($meta) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload);
    }

    protected function fail(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    protected function paginated($paginator, ?callable $transform = null): JsonResponse
    {
        $items = collect($paginator->items());

        if ($transform) {
            $items = $items->map($transform);
        }

        return $this->ok($items->values(), [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    protected function perPage(Request $request, int $default = 25): int
    {
        return min((int) $request->query('per_page', $default) ?: $default, 100);
    }
}
