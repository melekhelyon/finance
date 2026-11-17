<template>
    <header v-if="isPublic" class="pub-header">
        <router-link to="/" class="pub-brand"><span class="brand-logo">finance</span></router-link>
        <div class="pub-ticker">
            <span class="ticker-label">УПРАВЛЕНИЕ КАПИТАЛОМ /// ЛИМИТЫ /// ФИНАНСОВЫЕ ЦЕЛИ /// КОНТРОЛЬ СЧЕТОВ</span>
        </div>
        <nav class="pub-actions" aria-label="Главное меню">
            <router-link to="/" class="pub-btn" exact-active-class="active">ГЛАВНАЯ</router-link>
            <router-link to="/login" class="pub-btn" active-class="active">ВХОД</router-link>
            <router-link to="/register" class="pub-btn pub-btn-cta">РЕГИСТРАЦИЯ →</router-link>
        </nav>
        <button
            class="theme-toggle public-theme"
            @click="toggleTheme"
            :aria-label="theme === 'dark' ? 'Включить светлую тему' : 'Включить тёмную тему'"
        >
            {{ theme === 'dark' ? '☀ СВЕТЛАЯ' : '◐ ТЁМНАЯ' }}
        </button>
    </header>
    <aside v-else class="sidebar">
        <router-link to="/usermain" class="brand-cell">
            <div class="brand-logo">finance</div>
            <div class="brand-tag">СИСТЕМА УПРАВЛЕНИЯ КАПИТАЛОМ</div>
        </router-link>
        <nav class="nav-list" aria-label="Разделы приложения">
            <router-link
                v-for="(item, index) in menu"
                :key="item.path"
                :to="item.path"
                class="nav-row"
                active-class="active"
            >
                <span class="nav-num">{{ String(index + 1).padStart(2, '0') }}</span>
                <span class="nav-lbl">{{ item.label }}</span>
                <span class="nav-arr">{{ $route.path === item.path ? '■' : '→' }}</span>
            </router-link>
        </nav>
        <button class="theme-toggle" @click="toggleTheme">
            {{ theme === 'dark' ? '☀ СВЕТЛАЯ ТЕМА' : '◐ ТЁМНАЯ ТЕМА' }}
        </button>
        <Notifications />
        <div class="sidebar-capital">
            <div class="cap-lbl">КАПИТАЛ ПО ВАЛЮТАМ</div>
            <div v-for="(value, currency) in balances" :key="currency" class="cap-val">
                {{ money(value, currency) }}
            </div>
            <div v-if="!accounts.length" class="cap-val">0 ₽</div>
            <div class="cap-sub">АКТИВНЫХ СЧЕТОВ: {{ accounts.length }}</div>
        </div>
        <router-link to="/profile" class="sidebar-user">
            <div class="usr-avatar">{{ initials }}</div>
            <div class="usr-info">
                <div class="usr-name">{{ user.first_name }} {{ user.last_name }}</div>
                <div class="usr-role">{{ user.role?.name === 'admin' ? 'АДМИНИСТРАТОР' : 'ВЛАДЕЛЕЦ СЧЁТА' }}</div>
            </div>
        </router-link>
    </aside>
</template>

<script>
import { liveData, appearance } from './live';
import finance from './finance';
import Notifications from './Notifications.vue';

export default {
    name: 'NavBar',
    components: { Notifications },
    mixins: [
        finance,
        liveData({ user: { url: '/api/profile', initial: {} }, accounts: '/api/account' }, function () {
            return !this.isPublic;
        })
    ],
    computed: {
        theme() {
            return appearance.theme;
        },
        isPublic() {
            return ['app.main', 'app.login', 'app.register'].includes(this.$route.name);
        },
        menu() {
            const items = [
                { path: '/usermain', label: 'СВОДКА' },
                { path: '/accounts', label: 'СЧЕТА' },
                { path: '/categories', label: 'КАТЕГОРИИ' },
                { path: '/operations', label: 'ОПЕРАЦИИ' },
                { path: '/goals', label: 'ЦЕЛИ' },
                { path: '/budgets', label: 'ЛИМИТЫ' },
                { path: '/profile', label: 'ПРОФИЛЬ' }
            ];
            if (this.user.role?.name === 'admin') items.push({ path: '/admin', label: 'АДМИН-ПУЛЬТ' });
            return items;
        },
        initials() {
            return (this.user.first_name?.[0] || '') + (this.user.last_name?.[0] || '');
        },
        balances() {
            return this.accounts.reduce((result, account) => {
                result[account.currency] = (result[account.currency] || 0) + Number(account.balance);
                return result;
            }, {});
        }
    },
    watch: {
        'user.role.name'(role) {
            if (this.user.id && role !== 'admin' && this.$route.meta.admin) this.$router.replace('/usermain');
        }
    },
    methods: {
        toggleTheme() {
            appearance.theme = this.theme === 'dark' ? 'light' : 'dark';
        }
    }
};
</script>
