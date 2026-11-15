<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanEdit
{
    /**
     * Check module permissions for transactions; other writes require Admin or SCM.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $module = match ($request->segment(2)) {
            'drafts' => 'drafts',
            'invoices' => 'invoices',
            'data-program' => $request->segment(3) === 'approval-history' ? 'riwayat_program' : 'data_program',
            'program-submissions' => 'form_program',
            'brands', 'programs', 'regions', 'ar-contacts', 'team-contacts' => 'master_program',
            'dealers' => 'master_dealer',
            'customers' => 'master_customer',
            'users', 'automation-schedules' => 'master_user',
            default => null,
        };
        if ($module !== null) {
            $action = $request->isMethod('DELETE') ? 'delete' : (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) ? 'view' : 'edit');
            $controllerAction = $request->route()->getActionMethod();
            if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS', 'DELETE'], true)) {
                $action = in_array($controllerAction, ['store', 'generate', 'generateAll'], true) ? 'create' : 'edit';
                if (in_array($controllerAction, ['import', 'sync', 'resumeImport'], true)) {
                    abort_unless($request->user()?->canPerform($module, 'create'), 403, 'Anda tidak memiliki izin tambah data.');
                }
            }
            if ($action === 'view' && in_array($controllerAction, ['options'], true)) {
                return $next($request);
            }
            abort_unless($request->user()?->canPerform($module, $action), 403, 'Anda tidak memiliki izin untuk tindakan ini.');

            return $next($request);
        }

        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true) || $request->user()?->canEdit()) {
            return $next($request);
        }

        abort(403, 'Hanya Admin dan SCM yang bisa mengubah data.');
    }
}
