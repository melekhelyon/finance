<template>
    <div class="top-command-bar">
        <div>
            <div class="cmd-crumb">РАЗДЕЛ 07 // ПАСПОРТ ПОЛЬЗОВАТЕЛЯ И БЕЗОПАСНОСТЬ</div>
            <h1 class="cmd-title">ЛИЧНЫЙ КАБИНЕТ</h1>
        </div>
        <button class="brut-btn-secondary" :disabled="busy" @click="logout">ВЫЙТИ ИЗ АККАУНТА →</button>
    </div>
    <Feedback :error="error || dataError" :notice="notice" :loading="loading || dataLoading" />
    <div class="profile-brut-3">
        <section class="brut-panel inv">
            <div class="panel-head">
                <span>УДОСТОВЕРЕНИЕ УЧАСТНИКА</span>
                <span>finance</span>
            </div>
            <div class="form-stack">
                <div class="giant-avatar-box">{{ user.first_name?.[0] }}{{ user.last_name?.[0] }}</div>
                <h2>{{ user.last_name }} {{ user.first_name }}</h2>
                <div>ДАТА РЕГИСТРАЦИИ: {{ date(user.created_at) }}</div>
                <div>РОЛЬ: {{ user.role?.name === 'admin' ? 'АДМИНИСТРАТОР' : 'ПОЛЬЗОВАТЕЛЬ' }}</div>
                <div>ВАЛЮТА УЧЁТА: {{ currencies[user.default_currency] }}</div>
                <div>АКТИВНЫХ СЧЕТОВ: {{ accounts.length }}</div>
                <button class="brut-btn-secondary full-w" :disabled="busy" @click="logout">
                    ЗАВЕРШИТЬ СЕССИЮ И ВЫЙТИ
                </button>
            </div>
        </section>
        <section class="brut-panel">
            <div class="panel-head">
                <span>01 // ЛИЧНЫЕ ДАННЫЕ ВЛАДЕЛЬЦА</span>
                <span>РЕДАКТИРОВАНИЕ</span>
            </div>
            <form class="form-stack" @submit.prevent="save">
                <label class="brut-field">
                    <span class="bf-label">ИМЯ</span>
                    <input
                        class="bf-input"
                        v-model.trim="form.first_name"
                        :disabled="loading || dataLoading"
                        autocomplete="given-name"
                        required
                        minlength="2"
                        maxlength="100"
                    />
                </label>
                <label class="brut-field">
                    <span class="bf-label">ФАМИЛИЯ</span>
                    <input
                        class="bf-input"
                        v-model.trim="form.last_name"
                        :disabled="loading || dataLoading"
                        autocomplete="family-name"
                        required
                        minlength="2"
                        maxlength="100"
                    />
                </label>
                <label class="brut-field">
                    <span class="bf-label">ЭЛЕКТРОННАЯ ПОЧТА (НЕИЗМЕНЯЕМАЯ)</span>
                    <input class="bf-input" :value="user.email" readonly />
                </label>
                <label class="brut-field">
                    <span class="bf-label">НОМЕР ТЕЛЕФОНА</span>
                    <input
                        class="bf-input"
                        type="tel"
                        v-model.trim="form.phone"
                        :disabled="loading || dataLoading"
                        autocomplete="tel"
                        maxlength="20"
                    />
                </label>
                <label class="brut-field">
                    <span class="bf-label">ВАЛЮТА ПО УМОЛЧАНИЮ</span>
                    <select class="bf-input" v-model="form.default_currency" :disabled="loading || dataLoading">
                        <option v-for="(label, key) in currencies" :key="key" :value="key">{{ label }}</option>
                    </select>
                </label>
                <button class="brut-btn-primary full-w" :disabled="busy || loading || dataLoading">
                    СОХРАНИТЬ ИЗМЕНЕНИЯ →
                </button>
            </form>
        </section>
        <section class="brut-panel">
            <div class="panel-head">
                <span>02 // СМЕНА ПАРОЛЯ БЕЗОПАСНОСТИ</span>
                <span>ЗАЩИТА</span>
            </div>
            <form class="form-stack" @submit.prevent="changePassword">
                <label class="brut-field">
                    <span class="bf-label">ТЕКУЩИЙ ПАРОЛЬ</span>
                    <input
                        class="bf-input"
                        type="password"
                        v-model="passwords.current_password"
                        autocomplete="current-password"
                        required
                    />
                </label>
                <label class="brut-field">
                    <span class="bf-label">НОВЫЙ ПАРОЛЬ (ОТ 8 СИМВОЛОВ)</span>
                    <input
                        class="bf-input"
                        type="password"
                        v-model="passwords.new_password"
                        autocomplete="new-password"
                        required
                        minlength="8"
                    />
                </label>
                <label class="brut-field">
                    <span class="bf-label">ПОДТВЕРЖДЕНИЕ НОВОГО ПАРОЛЯ</span>
                    <input
                        class="bf-input"
                        type="password"
                        v-model="passwords.new_password_confirmation"
                        autocomplete="new-password"
                        required
                        minlength="8"
                    />
                </label>
                <button class="brut-btn-primary full-w" :disabled="busy">ОБНОВИТЬ ПАРОЛЬ →</button>
            </form>
        </section>
    </div>
</template>

<script>
import { liveData, setSession } from '../live';
import finance, { axios } from '../finance';
import Feedback from '../Feedback.vue';

export default {
    name: 'Profile',
    mixins: [finance, liveData({ user: { url: '/api/profile', initial: {} }, accounts: '/api/account' })],
    components: { Feedback },
    data() {
        return {
            form: {},
            savedForm: {},
            passwords: { current_password: '', new_password: '', new_password_confirmation: '' }
        };
    },
    watch: {
        user: {
            immediate: true,
            handler(value) {
                const fields = ['first_name', 'last_name', 'phone', 'default_currency'];
                const next = Object.fromEntries(fields.map(key => [key, value[key] ?? '']));
                for (const key of fields) {
                    if (!(key in this.form) || this.form[key] === this.savedForm[key]) this.form[key] = next[key];
                }
                this.savedForm = next;
            }
        }
    },
    methods: {
        async save() {
            await this.perform(async () => {
                await axios.put('/api/profile', this.form);
                this.form = { ...this.savedForm };
            }, 'Личные данные сохранены.');
        },
        async changePassword() {
            if (this.passwords.new_password !== this.passwords.new_password_confirmation) {
                this.error = 'Пароли не совпадают.';
                return;
            }
            await this.perform(async () => {
                await axios.post('/api/change-password', this.passwords);
                this.passwords = { current_password: '', new_password: '', new_password_confirmation: '' };
            }, 'Пароль обновлён.');
        },
        async logout() {
            await this.perform(async () => {
                await axios.post('/api/logout');
                setSession(null);
                await this.$router.push('/login');
            });
        }
    }
};
</script>
