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
import TestProgramIndex from '../Pages/TestProgram/Index.vue';
import UsersIndex from '../Pages/Users/Index.vue';
import CustomersIndex from '../Pages/Customers/Index.vue';
import DealersIndex from '../Pages/Dealers/Index.vue';
import ProgramsIndex from '../Pages/Programs/Index.vue';
import RegionsIndex from '../Pages/Regions/Index.vue';
import ArContactsIndex from '../Pages/ArContacts/Index.vue';
import { useAuth } from '../composables/useAuth';
import { AREA_ROUTE_PATTERN, DEFAULT_AREA } from '../composables/useArea';

/**
 * Route halaman yang datanya terpisah per area (/path/jabar, /path/jateng).
 * Link lama tanpa area (/path) diarahkan ke Jawa Barat dengan query yang sama.
 */
function areaRoutes(path, name, component) {
  return [
    { path, redirect: to => ({ path: `${path}/${DEFAULT_AREA}`, query: to.query }) },
    { path: `${path}/:area(${AREA_ROUTE_PATTERN})`, name, component, meta: { requiresAuth: true } },
  ];
}

const routes = [
  { path: '/login', name: 'login', component: Login, meta: { guestOnly: true } },
  { path: '/register', name: 'register', component: Register, meta: { guestOnly: true } },
  { path: '/', redirect: '/dashboard' },
  { path: '/dashboard', name: 'dashboard', component: Dashboard, meta: { requiresAuth: true } },
  ...areaRoutes('/drafts', 'drafts.index', DraftsIndex),
  { path: '/drafts/:id', name: 'drafts.detail', component: DraftDetail, props: true, meta: { requiresAuth: true } },
  { path: '/invoices', name: 'invoices.index', component: InvoicesIndex, meta: { requiresAuth: true } },
  { path: '/invoices/:id', name: 'invoices.detail', component: InvoiceDetail, props: true, meta: { requiresAuth: true } },
  { path: '/email-logs', name: 'email-logs.index', component: EmailLogsIndex, meta: { requiresAuth: true } },
  { path: '/form-program', redirect: `/test-program/${DEFAULT_AREA}` },
  { path: '/form-submission', name: 'program-submissions.public-form', component: ProgramPublicForm, meta: { requiresAuth: false, isStandalone: true, defaultArea: DEFAULT_AREA } },
  { path: '/form-realme', name: 'program-realme.public-form', component: ProgramPublicForm, meta: { requiresAuth: false, isStandalone: true, defaultArea: DEFAULT_AREA } },
  { path: '/form', redirect: '/form-realme' },
  // Form publik per area, contoh: /form/jateng
  { path: `/form/:area(${AREA_ROUTE_PATTERN})`, name: 'program-area.public-form', component: ProgramPublicForm, meta: { requiresAuth: false, isStandalone: true } },
  // Halaman per area (Jabar/Jateng); link lama tanpa area diarahkan ke Jabar
  ...areaRoutes('/data-program', 'data-program.index', DataProgramIndex),
  ...areaRoutes('/test-program', 'test-program.index', TestProgramIndex),
  ...areaRoutes('/riwayat-program', 'program-history.index', ProgramHistoryIndex),
  // Master Program satu daftar untuk semua area; link lama /programs/jabar diarahkan ke /programs
  { path: '/programs', name: 'programs.index', component: ProgramsIndex, meta: { requiresAuth: true } },
  { path: `/programs/:area(${AREA_ROUTE_PATTERN})`, redirect: '/programs' },
  ...areaRoutes('/regions', 'regions.index', RegionsIndex),
  ...areaRoutes('/ar', 'ar-contacts.index', ArContactsIndex),
  { path: '/customers', name: 'customers.index', component: CustomersIndex, meta: { requiresAuth: true } },
  ...areaRoutes('/dealers', 'dealers.index', DealersIndex),
  { path: '/users', name: 'users.index', component: UsersIndex, meta: { requiresAuth: true } },
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

  const { isInitialized, checkAuth, isAuthenticated } = useAuth();

  if (!isInitialized.value) {
    await checkAuth();
  }

  if (to.meta.requiresAuth && !isAuthenticated.value) {
    return next({ name: 'login', query: { redirect: to.fullPath } });
  }

  if (to.meta.guestOnly && isAuthenticated.value) {
    return next({ name: 'dashboard' });
  }

  next();
});

export default router;
