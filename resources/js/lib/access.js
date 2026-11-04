/**
 * Hak akses menu pengguna: daftar akses khusus bila ada, selain itu bawaan perannya.
 * Dipakai menu di App.vue dan router, supaya halaman yang tidak tampil di menu juga tidak bisa dibuka.
 */
export function canAccess(user, feature) {
  if (!user) return true;

  // 1. Jika user memiliki daftar custom permissions
  if (Array.isArray(user.permissions) && user.permissions.length > 0) {
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

// Halaman pertama setelah masuk: Dashboard, terbuka untuk semua pengguna (bukan hak akses)
export const HOME_PATH = '/dashboard';

/**
 * Whether the user may open a route: its meta.permission (a menu feature) and meta.adminOnly.
 */
export function canOpenRoute(user, meta) {
  if (meta.adminOnly && user?.role !== 'admin') return false;

  return !meta.permission || canAccess(user, meta.permission);
}
