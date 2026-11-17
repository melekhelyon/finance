<template>
    <div class="reg-brut-layout">
        <div class="reg-steps-col">
            <div class="brut-badge">НОВЫЙ ПРОФИЛЬ</div>
            <h1 class="auth-title">АНКЕТА РЕГИСТРАЦИИ</h1>
            <p>
                Заполните обязательные поля для выпуска личного финансового паспорта в системе
                <strong>finance</strong>
                .
            </p>
            <div v-for="(step, index) in steps" :key="step" class="step-block active">
                <div class="sb-num">ШАГ 0{{ index + 1 }}</div>
                <div class="sb-title">{{ step }}</div>
            </div>
            <div class="id-card-preview">
                <div class="idp-top">
                    <span>КАРТА УЧАСТНИКА // finance</span>
                    <span>{{ currencyName(form.default_currency) }}</span>
                </div>
                <div class="idp-name">{{ form.last_name || 'ФАМИЛИЯ' }} {{ form.first_name || 'ИМЯ' }}</div>
                <div class="idp-meta">{{ form.phone || 'Телефон' }} • {{ form.email || 'Электронная почта' }}</div>
            </div>
        </div>
        <div class="reg-form-col">
            <div class="brut-form-box full-w">
                <div class="form-box-header">
                    <span>ФОРМА СОЗДАНИЯ ПРОФИЛЯ</span>
                    <span>НОВЫЙ УЧАСТНИК</span>
                </div>
                <form class="form-box-body" @submit.prevent="register">
                    <Feedback :error="error" />
                    <div class="reg-grid-2">
                        <label class="brut-field">
                            <span class="bf-label">01 // ВАШЕ ИМЯ</span>
                            <input
                                class="bf-input"
                                v-model.trim="form.first_name"
                                autocomplete="given-name"
                                required
                                minlength="2"
                                maxlength="100"
                            />
                        </label>
                        <label class="brut-field">
                            <span class="bf-label">02 // ВАША ФАМИЛИЯ</span>
                            <input
                                class="bf-input"
                                v-model.trim="form.last_name"
                                autocomplete="family-name"
                                required
                                minlength="2"
                                maxlength="100"
                            />
                        </label>
                        <label class="brut-field">
                            <span class="bf-label">03 // ЭЛЕКТРОННАЯ ПОЧТА</span>
                            <input
                                class="bf-input"
                                v-model.trim="form.email"
                                type="email"
                                autocomplete="email"
                                required
                                maxlength="255"
                            />
                        </label>
                        <label class="brut-field">
                            <span class="bf-label">04 // НОМЕР ТЕЛЕФОНА</span>
                            <input
                                class="bf-input"
                                v-model.trim="form.phone"
                                type="tel"
                                autocomplete="tel"
                                required
                                maxlength="20"
                                placeholder="+7 (999) 123-45-67"
                            />
                        </label>
                        <label class="brut-field">
                            <span class="bf-label">05 // ПАРОЛЬ ОТ 8 СИМВОЛОВ</span>
                            <input
                                class="bf-input"
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                required
                                minlength="8"
                            />
                        </label>
                        <label class="brut-field">
                            <span class="bf-label">06 // ПОДТВЕРЖДЕНИЕ ПАРОЛЯ</span>
                            <input
                                class="bf-input"
                                v-model="form.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                required
                                minlength="8"
                            />
                        </label>
                    </div>
                    <label class="brut-field">
                        <span class="bf-label">БАЗОВАЯ ВАЛЮТА</span>
                        <select class="bf-input" v-model="form.default_currency">
                            <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                        </select>
                    </label>
                    <button class="brut-btn-primary full-w" :disabled="busy">
                        {{ busy ? 'СОЗДАНИЕ…' : 'СОЗДАТЬ АККАУНТ В FINANCE →' }}
                    </button>
                    <div class="form-switch-box">
                        <span>УЖЕ ЕСТЬ ПРОФИЛЬ?</span>
                        <router-link to="/login" class="brut-tag-sm">ВОЙТИ →</router-link>
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
    name: 'Register',
    mixins: [finance],
    components: { Feedback },
    data() {
        return {
            steps: ['ИДЕНТИФИКАЦИЯ', 'КОНТАКТНЫЕ ДАННЫЕ', 'КЛЮЧ БЕЗОПАСНОСТИ'],
            form: {
                first_name: '',
                last_name: '',
                email: '',
                phone: '',
                password: '',
                password_confirmation: '',
                default_currency: 'RUB'
            }
        };
    },
    methods: {
        async register() {
            if (this.form.password !== this.form.password_confirmation) {
                this.error = 'Пароли не совпадают.';
                return;
            }
            await this.perform(async () => {
                const response = await axios.post('/api/register', this.form);
                setSession(response.data.token, response.data.user);
                await this.$router.push({ name: 'user.main' });
            });
        }
    }
};
</script>
