<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->header('X-Company', $request->query('company', 'top'));
        $state = $request->query('state');
        if ($request->is('auth/google/callback')) {
            if (! $request->user()?->canEdit()) {
                return $next($request);
            }
            abort_unless(is_string($state) && preg_match('/^[A-Za-z0-9]{40}$/', $state), 403);
            $oauth = $request->session()->pull('oauth_company.'.$state);
            abort_unless($oauth && $oauth['user_id'] === $request->user()?->id && $oauth['expires'] >= now()->timestamp, 403);
            $slug = $oauth['slug'];
        }
        abort_unless(is_string($slug), 422, 'Perusahaan tidak valid.');
        $company = Company::query()->where('slug', $slug)->firstOrFail();
        $public = ($request->isMethod('GET') && $request->is('api/brands', 'api/dealers/lookup', 'api/dealers/suggestions', 'api/program-submissions/form-options'))
            || $request->is('api/program-submissions/pre-validate',
                'api/program-submissions/submit-form', 'api/webhooks/*', 'p/confirm/*', 'dp/confirm/*',
                'invoices/*/pdf', 'invoices/*/preview');
        $identityRoute = $request->is('api/login', 'api/logout', 'api/user', 'api/auth/*', 'api/companies', 'api/profile', 'api/profile/*');
        if (! $public && ! $identityRoute && ($request->user() !== null || $company->id !== 1)) {
            abort_unless($company->canBeAccessedBy($request->user()), 403, 'Anda tidak memiliki akses ke perusahaan ini.');
        }

        return app(CompanyContext::class)->run($company->id, fn (): Response => $next($request));
    }
}
