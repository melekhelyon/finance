<template>
    <section class="brut-panel rates-panel" aria-label="Официальные курсы валют">
        <div class="panel-head">
            <a href="https://www.cbr.ru/currency_base/daily/" target="_blank" rel="noopener" class="panel-link">
                КУРСЫ БАНКА РОССИИ ↗
            </a>
            <span v-if="snapshot">НА {{ date(snapshot.rate_date) }}</span>
            <span v-else>ОФИЦИАЛЬНЫЙ ИСТОЧНИК</span>
        </div>
        <div class="rates-values">
            <template v-if="snapshot">
                <strong>1 доллар США = {{ rate(snapshot.rates.USD) }} ₽</strong>
                <strong>1 евро = {{ rate(snapshot.rates.EUR) }} ₽</strong>
                <span>Проверено: {{ new Date(snapshot.checked_at).toLocaleString('ru-RU') }}</span>
            </template>
            <span v-else>{{ dataLoading ? 'Получаем курсы…' : 'Курсы пока недоступны' }}</span>
        </div>
        <p v-if="dataError || snapshot?.stale" class="rates-notice" role="status">
            {{
                dataError ||
                'Не удалось обновить курсы. Показаны последние сохранённые значения; валютный перевод временно недоступен.'
            }}
        </p>
        <p class="rates-notice">
            Официальный курс на указанную дату. Проверка обновлений каждые 5 минут; курс банка при обмене может
            отличаться.
        </p>
    </section>
</template>

<script>
import finance from './finance';
import { liveData } from './live';

export default {
    name: 'ExchangeRates',
    mixins: [finance, liveData({ snapshot: { url: '/api/exchange-rates', initial: null, interval: 300000 } })],
    emits: ['loaded'],
    watch: {
        snapshot: {
            immediate: true,
            handler(value) {
                this.$emit('loaded', value);
            }
        },
        dataError(value) {
            if (this.snapshot) this.$emit('loaded', value ? { ...this.snapshot, stale: true } : this.snapshot);
        }
    },
    methods: {
        rate(value) {
            return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 6 }).format(Number(value));
        }
    }
};
</script>
