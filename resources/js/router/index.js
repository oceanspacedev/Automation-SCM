import { createRouter, createWebHistory } from 'vue-router';
import Login from '../Pages/Auth/Login.vue';
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
import { useAuth } from '../composables/useAuth';

const routes = [
  { path: '/login', name: 'login', component: Login, meta: { guestOnly: true } },
  { path: '/', redirect: '/dashboard' },
  { path: '/dashboard', name: 'dashboard', component: Dashboard, meta: { requiresAuth: true } },
  { path: '/drafts', name: 'drafts.index', component: DraftsIndex, meta: { requiresAuth: true } },
  { path: '/drafts/:id', name: 'drafts.detail', component: DraftDetail, props: true, meta: { requiresAuth: true } },
  { path: '/invoices', name: 'invoices.index', component: InvoicesIndex, meta: { requiresAuth: true } },
  { path: '/invoices/:id', name: 'invoices.detail', component: InvoiceDetail, props: true, meta: { requiresAuth: true } },
  { path: '/email-logs', name: 'email-logs.index', component: EmailLogsIndex, meta: { requiresAuth: true } },
  { path: '/form-program', redirect: '/test-program' },
  { path: '/form-submission', name: 'program-submissions.public-form', component: ProgramPublicForm, meta: { requiresAuth: false, isStandalone: true } },
  { path: '/form-realme', name: 'program-realme.public-form', component: ProgramPublicForm, meta: { requiresAuth: false, isStandalone: true } },
  { path: '/form', redirect: '/form-realme' },
  { path: '/data-program', name: 'data-program.index', component: DataProgramIndex, meta: { requiresAuth: true } },
  { path: '/test-program', name: 'test-program.index', component: TestProgramIndex, meta: { requiresAuth: true } },
  { path: '/riwayat-program', name: 'program-history.index', component: ProgramHistoryIndex, meta: { requiresAuth: true } },
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
