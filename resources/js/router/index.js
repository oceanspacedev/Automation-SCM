import { createRouter, createWebHistory } from 'vue-router';
import Login from '../Pages/Auth/Login.vue';
import Register from '../Pages/Auth/Register.vue';
import Dashboard from '../Pages/Dashboard/Index.vue';
import DraftsIndex from '../Pages/Drafts/Index.vue';
import DraftDetail from '../Pages/Drafts/Detail.vue';
import InvoicesIndex from '../Pages/Invoices/Index.vue';
import InvoiceDetail from '../Pages/Invoices/Detail.vue';
import EmailLogsIndex from '../Pages/EmailLogs/Index.vue';
import ProgramSubmissionsIndex from '../Pages/ProgramSubmissions/Index.vue';
import ProgramPublicForm from '../Pages/ProgramSubmissions/PublicForm.vue';
import DataProgramIndex from '../Pages/DataProgram/Index.vue';
import ProgramHistoryIndex from '../Pages/ProgramHistory/Index.vue';
import ApprovalHistoryIndex from '../Pages/ApprovalHistory/Index.vue';
import TestProgramIndex from '../Pages/TestProgram/Index.vue';
import UsersIndex from '../Pages/Users/Index.vue';
import CustomersIndex from '../Pages/Customers/Index.vue';
import DealersIndex from '../Pages/Dealers/Index.vue';
import ProgramsIndex from '../Pages/Programs/Index.vue';
import RegionsIndex from '../Pages/Regions/Index.vue';
import ArContactsIndex from '../Pages/ArContacts/Index.vue';
import TeamContactsIndex from '../Pages/TeamContacts/Index.vue';
import ProfileIndex from '../Pages/Profile/Index.vue';
import BrandsIndex from '../Pages/Brands/Index.vue';
import SchedulesIndex from '../Pages/Schedules/Index.vue';
import DataResetIndex from '../Pages/DataReset/Index.vue';
import { useAuth } from '../composables/useAuth';
import { canOpenRoute, HOME_PATH } from '../lib/access';
import { AREA_ROUTE_PATTERN, DEFAULT_AREA } from '../composables/useArea';
import { BRAND_ROUTE_PATTERN, DEFAULT_BRAND } from '../composables/useBrands';

/**
 * Route halaman yang datanya terpisah per area (/path/jabar, /path/jateng).
 * Link lama tanpa area (/path) diarahkan ke Jawa Barat dengan query yang sama.
 */
function areaRoutes(path, name, component, permission) {
  return [
    { path, redirect: to => ({ path: `${path}/${DEFAULT_AREA}`, query: to.query }) },
    { path: `${path}/:area(${AREA_ROUTE_PATTERN})`, name, component, meta: { requiresAuth: true, permission } },
  ];
}

/**
 * Route halaman yang datanya terpisah per brand & area (/path/realme/jabar).
 * Link lama tanpa brand (/path/jabar) atau tanpa area (/path) diarahkan ke Realme dengan query yang sama.
 */
function brandAreaRoutes(path, name, component, permission) {
  return [
    { path, redirect: to => ({ path: `${path}/${DEFAULT_BRAND}/${DEFAULT_AREA}`, query: to.query }) },
    { path: `${path}/:area(${AREA_ROUTE_PATTERN})`, redirect: to => ({ path: `${path}/${DEFAULT_BRAND}/${to.params.area}`, query: to.query }) },
    { path: `${path}/:brand(${BRAND_ROUTE_PATTERN})/:area(${AREA_ROUTE_PATTERN})`, name, component, meta: { requiresAuth: true, permission } },
  ];
}

const routes = [
  { path: '/login', name: 'login', component: Login, meta: { guestOnly: true } },
  { path: '/register', name: 'register', component: Register, meta: { guestOnly: true } },
  { path: '/', redirect: '/dashboard' },
  { path: '/dashboard', name: 'dashboard', component: Dashboard, meta: { requiresAuth: true } },
  ...brandAreaRoutes('/drafts', 'drafts.index', DraftsIndex, 'drafts'),
  { path: '/drafts/:id', name: 'drafts.detail', component: DraftDetail, props: true, meta: { requiresAuth: true, permission: 'drafts' } },
  ...brandAreaRoutes('/invoices', 'invoices.index', InvoicesIndex, 'invoices'),
  { path: '/invoices/:id', name: 'invoices.detail', component: InvoiceDetail, props: true, meta: { requiresAuth: true, permission: 'invoices' } },
  { path: '/email-logs', name: 'email-logs.index', component: EmailLogsIndex, meta: { requiresAuth: true, permission: 'email_logs' } },
  // Link form publik lama (sudah tersebar) diarahkan ke form Realme Jawa Barat / area yang sama
  { path: '/form-submission', redirect: to => ({ path: `/form/${DEFAULT_BRAND}/${DEFAULT_AREA}`, query: to.query }) },
  { path: '/form-realme', redirect: to => ({ path: `/form/${DEFAULT_BRAND}/${DEFAULT_AREA}`, query: to.query }) },
  { path: '/form', redirect: to => ({ path: `/form/${DEFAULT_BRAND}/${DEFAULT_AREA}`, query: to.query }) },
  { path: `/form/:area(${AREA_ROUTE_PATTERN})`, redirect: to => ({ path: `/form/${DEFAULT_BRAND}/${to.params.area}`, query: to.query }) },
  // Form publik per brand & area, contoh: /form/realme/jateng
  { path: `/form/:brand(${BRAND_ROUTE_PATTERN})/:area(${AREA_ROUTE_PATTERN})`, name: 'program-area.public-form', component: ProgramPublicForm, meta: { requiresAuth: false, isStandalone: true } },
  // Halaman per brand & area (mis. /data-program/realme/jabar); link lama diarahkan ke Realme
  ...brandAreaRoutes('/data-program', 'data-program.index', DataProgramIndex, 'data_program'),
  ...brandAreaRoutes('/form-program', 'form-program.index', TestProgramIndex, 'form_program'),
  // Link lama /test-program dan /test-program/:area diarahkan ke /form-program Realme
  { path: '/test-program', redirect: to => ({ path: `/form-program/${DEFAULT_BRAND}/${DEFAULT_AREA}`, query: to.query }) },
  { path: `/test-program/:area(${AREA_ROUTE_PATTERN})`, redirect: to => ({ path: `/form-program/${DEFAULT_BRAND}/${to.params.area}`, query: to.query }) },
  ...brandAreaRoutes('/riwayat-program', 'program-history.index', ProgramHistoryIndex, 'riwayat_program'),
  ...brandAreaRoutes('/riwayat-approval', 'approval-history.index', ApprovalHistoryIndex, 'riwayat_program'),
  // Master Program per brand (satu daftar untuk semua area); link lama /programs dan /programs/jabar diarahkan ke Realme
  { path: '/programs', redirect: `/programs/${DEFAULT_BRAND}` },
  { path: `/programs/:area(${AREA_ROUTE_PATTERN})`, redirect: `/programs/${DEFAULT_BRAND}` },
  { path: `/programs/:brand(${BRAND_ROUTE_PATTERN})`, name: 'programs.index', component: ProgramsIndex, meta: { requiresAuth: true, permission: 'master_program' } },
  { path: '/brands', name: 'brands.index', component: BrandsIndex, meta: { requiresAuth: true, permission: 'master_program' } },
  ...areaRoutes('/regions', 'regions.index', RegionsIndex, 'master_program'),
  // Master AR satu halaman untuk Jawa Barat & Jawa Tengah (satu AR per area); link lama per area diarahkan ke sini
  { path: '/ar', name: 'ar-contacts.index', component: ArContactsIndex, meta: { requiresAuth: true, permission: 'master_program' } },
  { path: `/ar/:area(${AREA_ROUTE_PATTERN})`, redirect: '/ar' },
  // Tim Pajak & SCM satu untuk semua area; link per area diarahkan ke halaman yang sama
  { path: '/tim-pajak-scm', name: 'team-contacts.index', component: TeamContactsIndex, meta: { requiresAuth: true, permission: 'master_program' } },
  { path: `/tim-pajak-scm/:area(${AREA_ROUTE_PATTERN})`, redirect: '/tim-pajak-scm' },
  // Master Badan Usaha (Bill To invoice); link lama /customers diarahkan ke sini
  { path: '/badan-usaha', name: 'customers.index', component: CustomersIndex, meta: { requiresAuth: true, permission: 'master_customer' } },
  { path: '/customers', redirect: '/badan-usaha' },
  ...areaRoutes('/dealers', 'dealers.index', DealersIndex, 'master_dealer'),
  { path: '/users', name: 'users.index', component: UsersIndex, meta: { requiresAuth: true, permission: 'master_user' } },
  { path: '/jadwal-otomatis', name: 'schedules.index', component: SchedulesIndex, meta: { requiresAuth: true, permission: 'master_user' } },
  { path: '/reset-data', name: 'data-reset.index', component: DataResetIndex, meta: { requiresAuth: true, adminOnly: true } },
  { path: '/profile', name: 'profile', component: ProfileIndex, meta: { requiresAuth: true } },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to, from, next) => {
  // Public standalone routes (like customer/sales form) must proceed immediately without auth checks
  if (to.meta.requiresAuth === false && !to.meta.guestOnly) {
    return next();
  }

  const { isInitialized, checkAuth, isAuthenticated, user } = useAuth();

  if (!isInitialized.value) {
    await checkAuth();
  }

  if (to.meta.requiresAuth && !isAuthenticated.value) {
    return next({ name: 'login', query: { redirect: to.fullPath } });
  }

  if (to.meta.guestOnly && isAuthenticated.value) {
    return next(HOME_PATH);
  }

  // Halaman di luar hak akses (mis. Draft untuk telemarketing) diarahkan ke Dashboard
  if (to.meta.requiresAuth && !canOpenRoute(user.value, to.meta)) {
    return next(HOME_PATH);
  }

  next();
});

export default router;
