<template>
    <div class="auth-brut-split">
        <div class="auth-poster-side">
            <div>
                <div class="brut-badge">АВТОРИЗАЦИЯ В СИСТЕМЕ</div>
                <div class="giant-vertical-brand">finance</div>
                <h2>СИСТЕМА ТОЧНОГО УЧЁТА КАПИТАЛА</h2>
                <p class="hero-lead">
                    Войдите в личный терминал управления финансами для работы со счетами, бюджетами и аналитикой.
                </p>
            </div>
            <div class="security-grid">
                <div class="sec-item">
                    <strong>[✓] ЗАЩИТА СЕССИИ</strong>
                    <span>Персональные токены доступа</span>
                </div>
                <div class="sec-item">
                    <strong>[✓] МГНОВЕННЫЙ ОТКЛИК</strong>
                    <span>Синхронизация балансов в реальном времени</span>
                </div>
                <div class="sec-item">
                    <strong>[✓] ПОЛНАЯ АНАЛИТИКА</strong>
                    <span>Графики по счетам, целям и лимитам</span>
                </div>
            </div>
        </div>
        <div class="auth-form-container">
            <div class="brut-form-box">
                <div class="form-box-header">
                    <span>ВХОД В АККАУНТ</span>
                    <span>ТЕРМИНАЛ ДОСТУПА</span>
                </div>
                <form class="form-box-body" @submit.prevent="login">
                    <h1 class="auth-title">С ВОЗВРАЩЕНИЕМ</h1>
                    <p>
                        Введите учётные данные вашего профиля
                        <strong>finance</strong>
                        для продолжения работы.
                    </p>
                    <Feedback :error="error" />
                    <label class="brut-field">
                        <span class="bf-label">01 // ЭЛЕКТРОННАЯ ПОЧТА</span>
                        <input
                            class="bf-input"
                            type="email"
                            v-model.trim="email"
                            autocomplete="email"
                            required
                            maxlength="255"
                            placeholder="Электронная почта"
                        />
                    </label>
                    <label class="brut-field">
                        <span class="bf-label">02 // ПАРОЛЬ ДОСТУПА</span>
                        <input
                            class="bf-input"
                            type="password"
                            v-model="password"
                            autocomplete="current-password"
                            required
                            placeholder="••••••••"
                        />
                    </label>
                    <button class="brut-btn-primary full-w" :disabled="busy">
                        {{ busy ? 'ВХОД…' : 'ВОЙТИ В СИСТЕМУ →' }}
                    </button>
                    <div class="form-switch-box">
                        <span>ЕЩЁ НЕТ ЛИЧНОГО ПРОФИЛЯ?</span>
                        <router-link to="/register" class="brut-tag-sm">ПЕРЕЙТИ К РЕГИСТРАЦИИ →</router-link>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { setSession } from '../live';
import finance, { axios } from '../finance';
import Feedback from '../Feedback.vue';

export default {
    name: 'Login',
    mixins: [finance],
    components: { Feedback },
    data() {
        return { email: '', password: '' };
    },
    methods: {
        async login() {
            await this.perform(async () => {
                const response = await axios.post('/api/login', { email: this.email, password: this.password });
                setSession(response.data.token, response.data.user);
                await this.$router.push({ name: 'user.main' });
            });
        }
    }
};
</script>
