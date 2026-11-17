import { createRouter, createWebHistory } from 'vue-router';
import axios from 'axios';

const routes = [
    { path: '/', component: () => import('./Main.vue'), name: 'app.main' },
    { path: '/register', component: () => import('./Auth/Register.vue'), name: 'app.register' },
    { path: '/login', component: () => import('./Auth/Login.vue'), name: 'app.login' },
    { path: '/usermain', component: () => import('./User/Index.vue'), name: 'user.main' },
    { path: '/accounts', component: () => import('./User/Accounts.vue'), name: 'user.accounts' },
    { path: '/categories', component: () => import('./User/Categories.vue'), name: 'user.categories' },
    { path: '/operations', component: () => import('./User/Operations.vue'), name: 'user.operations' },
    { path: '/goals', component: () => import('./User/Goals.vue'), name: 'user.goals' },
    { path: '/budgets', component: () => import('./User/Budgets.vue'), name: 'user.budgets' },
    { path: '/profile', component: () => import('./User/Profile.vue'), name: 'user.profile' },
    { path: '/admin', component: () => import('./Admin/AdminPanel.vue'), name: 'admin.panel', meta: { admin: true } },
    { path: '/:pathMatch(.*)*', redirect: '/' }
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 };
    }
});

router.beforeEach(async to => {
    const token = localStorage.getItem('token');
    const isPublic = ['app.main', 'app.register', 'app.login'].includes(to.name);
    if (!token && !isPublic) return { name: 'app.login' };
    if (token && ['app.register', 'app.login'].includes(to.name)) return { name: 'user.main' };
    if (to.meta.admin) {
        try {
            const response = await axios.get('/api/profile');
            if (response.data.role?.name !== 'admin') return { name: 'user.main' };
        } catch {
            return { name: 'app.login' };
        }
    }
    return true;
});

export default router;
