<?php

namespace EmirKefi\TopologyMapper\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeTopology
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isAuthorized($request)) {
            return $next($request);
        }

        abort(403, 'Unauthorized access to Laravel Topology Mapper.');
    }

    protected function isAuthorized(Request $request): bool
    {
        // Allow in local and testing environments by default
        if (app()->environment('local', 'testing')) {
            return true;
        }

        // Check if custom authorization gate is defined in user app
        if (Gate::has('viewTopology')) {
            return Gate::check('viewTopology', [$request->user()]);
        }

        // Default open in non-production, restricted in production unless gate is provided
        return ! app()->environment('production');
    }
}
