<?php

namespace LaraImporter\Middleware;

use Closure;
use Illuminate\Http\Request;

class LaraImporterMiddleware
{
    public function handle(Request $request, Closure $next, ?string $ability = null)
    {
        $permissions = config('laraimporter.permissions', []);

        if ($ability && !empty($permissions[$ability])) {
            $permissionName = $permissions[$ability];
            if (!$request->user()?->can($permissionName)) {
                abort(403, 'Unauthorized.');
            }
        }

        return $next($request);
    }
}
