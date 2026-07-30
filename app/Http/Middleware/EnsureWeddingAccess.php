<?php

namespace App\Http\Middleware;

use App\Models\Wedding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWeddingAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $wedding = $request->route('wedding');
        $user = $request->user();

        if ($wedding instanceof Wedding) {
            if (! $user->canAccessWedding($wedding)) {
                abort(403);
            }

            // Couples have read-only access.
            if (! $request->isMethodSafe() && ! $user->canEditWedding($wedding)) {
                abort(403, 'Your account has view-only access to this wedding.');
            }
        }

        return $next($request);
    }
}
