import axios from 'axios';
import { session, calendar } from './live';

export const currencies = { RUB: 'Российский рубль (₽)', USD: 'Доллар США ($)', EUR: 'Евро (€)' };
export const accountTypes = {
    debit: 'Дебетовый счёт',
    credit: 'Кредитный счёт',
    savings: 'Сберегательный счёт',
    salary: 'Зарплатный счёт'
};
export const goalStatuses = { in_progress: 'В процессе', achieved: 'Достигнута', failed: 'Не достигнута' };

export function today() {
    return calendar.date;
}

export default {
    data() {
        return { error: '', notice: '', busy: false, loading: false, currencies, accountTypes, goalStatuses };
    },
    computed: {
        currentUser() {
            return session.user;
        }
    },
    methods: {
        currencyName(code) {
            return { RUB: 'Рубль', USD: 'Доллар США', EUR: 'Евро' }[code] || code;
        },
        localizeText(text) {
            return String(text).replace(/\b(RUB|USD|EUR)\b/g, code => this.currencyName(code));
        },
        money(value, currency = 'RUB') {
            return new Intl.NumberFormat('ru-RU', { style: 'currency', currency, maximumFractionDigits: 2 }).format(
                Number(value) || 0
            );
        },
        date(value) {
            return value ? new Date(value.slice(0, 10) + 'T00:00:00').toLocaleDateString('ru-RU') : '—';
        },
        percent(current, target) {
            return Number(target) > 0 ? Math.round((Number(current) / Number(target)) * 100) : 0;
        },
        async perform(action, message = '') {
            if (this.busy) return;
            this.busy = true;
            this.error = '';
            this.notice = '';
            try {
                await action();
                this.notice = message;
                return true;
            } catch (error) {
                let data = error.response?.data;
                if (data instanceof Blob) {
                    try {
                        data = JSON.parse(await data.text());
                    } catch {}
                }
                this.error = data?.errors
                    ? Object.values(data.errors).flat().join(' ')
                    : data?.message || 'Не удалось выполнить запрос. Проверьте соединение и повторите попытку.';
                return false;
            } finally {
                this.busy = false;
            }
        },
        async loadData(action) {
            this.loading = true;
            this.error = '';
            try {
                await action();
            } catch (error) {
                this.error = error.response?.data?.message || 'Не удалось загрузить данные. Обновите страницу.';
            } finally {
                this.loading = false;
            }
        }
    }
};

export { axios };
