<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanEdit
{
    /**
     * Let only Admin and SCM change data: reads (GET, HEAD, OPTIONS) pass for everyone,
     * any other request from AR or Telemarketing is refused with 403.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true) || $request->user()?->canEdit()) {
            return $next($request);
        }

        abort(403, 'Hanya Admin dan SCM yang bisa mengubah data.');
    }
}
