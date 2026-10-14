<?php

namespace App\Http\Middleware;

use App\Area;
use App\Services\AreaContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAreaContext
{
    /**
     * Select the area from the X-Area header or "area" parameter.
     *
     * Without an area, reads are not limited (so detail pages & links work for every area),
     * while new records, settings, and spreadsheet syncs fall back to Jawa Barat
     * so existing links and Apps Script webhooks keep working.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $area = Area::fromInput($request->header('X-Area'))
            ?? Area::fromInput($request->query('area'))
            ?? Area::fromInput($request->input('area'));

        app(AreaContext::class)->set($area);

        return $next($request);
    }
}
