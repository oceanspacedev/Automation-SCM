import { createRouter, createWebHistory } from 'vue-router';
import Dashboard from '../Pages/Dashboard/Index.vue';
import DraftsIndex from '../Pages/Drafts/Index.vue';
import DraftDetail from '../Pages/Drafts/Detail.vue';
import InvoicesIndex from '../Pages/Invoices/Index.vue';
import InvoiceDetail from '../Pages/Invoices/Detail.vue';
import EmailLogsIndex from '../Pages/EmailLogs/Index.vue';

const routes = [
  { path: '/', redirect: '/dashboard' },
  { path: '/dashboard', name: 'dashboard', component: Dashboard },
  { path: '/drafts', name: 'drafts.index', component: DraftsIndex },
  { path: '/drafts/:id', name: 'drafts.detail', component: DraftDetail, props: true },
  { path: '/invoices', name: 'invoices.index', component: InvoicesIndex },
  { path: '/invoices/:id', name: 'invoices.detail', component: InvoiceDetail, props: true },
  { path: '/email-logs', name: 'email-logs.index', component: EmailLogsIndex },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  linkActiveClass: 'border-b-2 border-black font-semibold',
});

export default router;
