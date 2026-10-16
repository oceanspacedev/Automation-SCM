<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use App\Services\BrandContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetBrandContext
{
    /**
     * Select the brand from the X-Brand header or "brand" parameter.
     *
     * Without a brand, reads are not limited (so detail pages & links work for every brand),
     * while new records, settings, and spreadsheet syncs fall back to Realme
     * so existing links and Apps Script webhooks keep working.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $brand = Brand::slugFromInput($request->header('X-Brand'))
            ?? Brand::slugFromInput($request->query('brand'))
            ?? Brand::slugFromInput($request->input('brand'));

        app(BrandContext::class)->set($brand);

        return $next($request);
    }
}
