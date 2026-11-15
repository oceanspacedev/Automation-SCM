/**
 * Hak akses menu pengguna: daftar akses khusus bila ada, selain itu bawaan perannya.
 * Dipakai menu di App.vue dan router, supaya halaman yang tidak tampil di menu juga tidak bisa dibuka.
 */
export function canAccess(user, feature) {
  if (!user) return true;
  user = { ...user, permissions: user.effective_permissions ?? user.permissions };

  // 1. Jika user memiliki daftar custom permissions
  if (Array.isArray(user.permissions)) {
    if (feature === 'riwayat') {
      return ['riwayat_program', 'email_logs'].some((permission) => user.permissions.includes(permission));
    }
    if (feature === 'master') {
      return ['master_program', 'master_dealer', 'master_customer', 'master_user'].some((permission) => user.permissions.includes(permission));
    }
    return user.permissions.includes(feature);
  }

  // 2. Fallback ke default role
  const role = user.role || 'scm';
  // Admin & SCM default bisa melihat semuanya
  if (role === 'admin' || role === 'scm') {
    return true;
  }

  if (feature === 'drafts') return role === 'ar';
  if (feature === 'invoices') return role === 'ar';
  if (feature === 'form_program') return role === 'telemarketing';
  if (feature === 'riwayat') return true;
  if (feature === 'riwayat_program') return true;
  if (feature === 'email_logs') return role === 'ar';

  return false;
}

/**
 * Whether the user may change data on the web (add, edit, delete, import, send, generate): only Admin and SCM.
 * AR and Telemarketing only view the menus they can open. Same rule as User::canEdit() on the server.
 */
export function canEdit(user) {
  if (!user) return false;

  const role = user.role || 'scm';
  return role === 'admin' || role === 'scm';
}

// Halaman pertama setelah masuk: Dashboard, terbuka untuk semua pengguna (bukan hak akses)
export const HOME_PATH = '/dashboard';

/**
 * Whether the user may open a route: its meta.permission (a menu feature) and meta.adminOnly.
 */
export function canOpenRoute(user, meta) {
  if (meta.adminOnly && user?.role !== 'admin') return false;

  return !meta.permission || canAccess(user, meta.permission);
}

export function canPerform(user, module, action) {
  if (!user || user.is_active === false || !canAccess(user, module)) return false;
  user = { ...user, permissions: user.effective_permissions ?? user.permissions };
  if (action === 'view') return true;
  if (Array.isArray(user.permissions)) return user.permissions.includes(module + '_' + action);
  return ['admin', 'scm'].includes(user.role || 'scm');
}
