<template>
    <div class="top-command-bar">
        <div>
            <div class="cmd-crumb">РАЗДЕЛ 04 // ДВИЖЕНИЕ ДЕНЕЖНЫХ СРЕДСТВ</div>
            <h1 class="cmd-title">ЖУРНАЛ ОПЕРАЦИЙ</h1>
        </div>
        <div class="cmd-actions">
            <button class="brut-btn-secondary" @click="reset">+ НОВАЯ ОПЕРАЦИЯ</button>
            <button class="brut-btn-primary" @click="exportCsv">СКАЧАТЬ ОТЧЁТ ↓</button>
        </div>
    </div>
    <Feedback :error="error || dataError" :notice="notice" :loading="loading || dataLoading" />
    <ListControls :fields="sortFields" v-model:sort="sort" v-model:direction="direction" v-model:size="pageSize" />
    <div class="ops-filters">
        <input
            class="brut-input-inline search-input"
            type="search"
            v-model="search"
            placeholder="Поиск по описанию…"
            aria-label="Поиск операций"
        />
        <select class="brut-input-inline" v-model="type" aria-label="Тип операции">
            <option value="">Все типы</option>
            <option value="income">Доходы</option>
            <option value="expense">Расходы</option>
            <option value="transfer">Переводы</option>
        </select>
        <select class="brut-input-inline" v-model="account" aria-label="Счёт">
            <option value="">Все счета</option>
            <option v-for="item in allAccounts" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <select class="brut-input-inline" v-model="category" aria-label="Категория">
            <option value="">Все категории</option>
            <option v-for="item in categories" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <DateRange v-model:from="from" v-model:to="to" />
    </div>
    <div class="ops-split-layout">
        <div class="ops-left-col">
            <section class="brut-panel">
                <div class="panel-head">
                    <span>ДИНАМИКА ДОХОДОВ И РАСХОДОВ</span>
                    <select v-model="currency" aria-label="Валюта статистики">
                        <option v-for="(label, code) in currencies" :key="code" :value="code">
                            {{ currencyName(code) }}
                        </option>
                    </select>
                    <div class="chart-controls">
                        <button
                            v-for="(label, key) in { line: 'ЛИНИЯ', bar: 'СТОЛБЦЫ', donut: 'КРУГ' }"
                            :key="key"
                            :class="{ active: chartType === key }"
                            @click="chartType = key"
                        >
                            {{ label }}
                        </button>
                    </div>
                </div>
                <div class="chart-padding">
                    <Chart
                        :type="chartType"
                        :labels="chartType === 'donut' ? ['Доходы', 'Расходы'] : dates"
                        :series="chartType === 'donut' ? totals : series"
                    />
                </div>
            </section>
            <section class="brut-panel">
                <div class="panel-head">
                    <span>РЕЕСТР ТРАНЗАКЦИЙ ({{ filtered.length }})</span>
                    <span>СТРАНИЦА {{ page }} ИЗ {{ pages }}</span>
                </div>
                <div class="table-wrap">
                    <table class="brut-table">
                        <thead>
                            <tr>
                                <th>ДАТА</th>
                                <th>ТИП</th>
                                <th>СЧЁТ</th>
                                <th>КАТЕГОРИЯ / ПОЛУЧАТЕЛЬ</th>
                                <th>СУММА</th>
                                <th>ДЕЙСТВИЯ</th>
                            </tr>
                        </thead>
                        <TransitionGroup name="list" tag="tbody">
                            <tr v-for="op in paginated" :key="op.id">
                                <td>{{ date(op.operation_date) }}</td>
                                <td>
                                    <span class="brut-tag-outline">
                                        {{
                                            op.transfer_account_id
                                                ? '⇄ ПЕРЕВОД'
                                                : op.category?.type === 'income'
                                                  ? '↑ ДОХОД'
                                                  : '↓ РАСХОД'
                                        }}
                                    </span>
                                </td>
                                <td>
                                    {{ op.account?.name }}
                                    <small v-if="op.account?.status === 'deleted'">(архив)</small>
                                </td>
                                <td>
                                    {{ op.transfer_account_id ? op.transfer_account?.name : op.category?.name }}
                                    <small class="op-description">{{ op.description }}</small>
                                </td>
                                <td>
                                    <strong>{{ money(op.amount, op.account?.currency) }}</strong>
                                    <small v-if="op.transfer_amount" class="op-description">
                                        → {{ money(op.transfer_amount, op.transfer_account?.currency) }}
                                    </small>
                                    <small v-if="op.exchange_rate_date" class="op-description">
                                        Курс ЦБ {{ date(op.exchange_rate_date) }}
                                    </small>
                                </td>
                                <td>
                                    <div class="vc-btns">
                                        <button class="mini-act" @click="edit(op)">ИЗМ.</button>
                                        <button class="mini-act" @click="remove(op)" :disabled="busy">УДАЛ.</button>
                                    </div>
                                </td>
                            </tr>
                            <tr key="static-6408" v-if="!paginated.length">
                                <td colspan="6" class="empty-state">ОПЕРАЦИИ НЕ НАЙДЕНЫ</td>
                            </tr>
                        </TransitionGroup>
                    </table>
                </div>
            </section>
            <ListPager v-model:page="page" :pages="pages" :total="filtered.length" :size="pageSize" />
        </div>
        <section class="brut-panel ops-right-col">
            <div class="panel-head inv">
                <span>{{ form.id ? 'ИЗМЕНИТЬ ОПЕРАЦИЮ' : 'НОВАЯ ОПЕРАЦИЯ' }}</span>
                <span>КАССОВЫЙ ТЕРМИНАЛ</span>
            </div>
            <form class="form-stack" @submit.prevent="save">
                <label class="brut-field">
                    <span class="bf-label">ТИП ОПЕРАЦИИ</span>
                    <select
                        class="bf-input"
                        v-model="form.kind"
                        :disabled="!!form.id"
                        @change="form.transfer_account_id = null"
                    >
                        <option value="regular">Обычная операция</option>
                        <option value="transfer">Перевод средств</option>
                    </select>
                </label>
                <label class="brut-field">
                    <span class="bf-label">{{ form.kind === 'transfer' ? 'СЧЁТ СПИСАНИЯ' : 'СЧЁТ ОПЕРАЦИИ' }}</span>
                    <select class="bf-input" v-model="form.account_id" required>
                        <option value="" disabled>Выберите счёт</option>
                        <option v-for="item in formAccounts" :key="item.id" :value="item.id">
                            {{ item.name }} ({{ money(item.balance, item.currency) }})
                        </option>
                    </select>
                </label>
                <label v-if="form.kind === 'regular'" class="brut-field">
                    <span class="bf-label">КАТЕГОРИЯ</span>
                    <select class="bf-input" v-model="form.category_id" required>
                        <option value="" disabled>Выберите категорию</option>
                        <option v-for="item in categories" :key="item.id" :value="item.id">
                            {{ item.name }} ({{ item.type === 'income' ? 'Доход' : 'Расход' }})
                        </option>
                    </select>
                </label>
                <label v-else class="brut-field">
                    <span class="bf-label">СЧЁТ ЗАЧИСЛЕНИЯ</span>
                    <select class="bf-input" v-model="form.transfer_account_id" required>
                        <option :value="null" disabled>Выберите счёт</option>
                        <option v-for="item in destinations" :key="item.id" :value="item.id">
                            {{ item.name }} ({{ currencyName(item.currency) }})
                        </option>
                    </select>
                </label>
                <label class="brut-field">
                    <span class="bf-label">СУММА СПИСАНИЯ</span>
                    <input class="bf-input" type="number" v-model="form.amount" min="0.01" step="0.01" required />
                </label>
                <label class="brut-field">
                    <span class="bf-label">ДАТА</span>
                    <input class="bf-input" type="date" v-model="form.operation_date" required />
                </label>
                <label class="brut-field">
                    <span class="bf-label">КОММЕНТАРИЙ</span>
                    <input class="bf-input" v-model.trim="form.description" maxlength="255" />
                </label>
                <div v-if="foreignTransfer" class="transfer-quote">
                    <p>
                        Перевод {{ currencyName(sourceAccount.currency) }} →
                        {{ currencyName(destinationAccount.currency) }} по официальному курсу Банка России на дату
                        операции.
                    </p>
                    <p role="status">
                        {{
                            quoteLoading ? 'РАССЧИТЫВАЕМ ЗАЧИСЛЕНИЕ…' : 'Сумма зачисления рассчитывается автоматически.'
                        }}
                    </p>
                    <template v-if="quote">
                        <p>
                            КУРС: 1 {{ currencyName(sourceAccount.currency) }} =
                            {{ Number(quote.rate).toLocaleString('ru-RU', { maximumFractionDigits: 8 }) }}
                            {{ currencyName(destinationAccount.currency) }} · {{ date(quote.rate_date) }}
                        </p>
                        <strong>К ЗАЧИСЛЕНИЮ: {{ money(quote.amount, destinationAccount.currency) }}</strong>
                        <p v-if="quote.saved">Сохранённый курс исходного перевода.</p>
                    </template>
                </div>
                <button
                    class="brut-btn-primary full-w"
                    :disabled="busy || quoteLoading || !formAccounts.length || (foreignTransfer && !quote)"
                >
                    {{ form.id ? 'СОХРАНИТЬ ИЗМЕНЕНИЯ →' : 'ПРОВЕСТИ ОПЕРАЦИЮ →' }}
                </button>
                <button v-if="form.id" class="brut-btn-secondary" type="button" @click="reset">
                    ОТМЕНИТЬ РЕДАКТИРОВАНИЕ
                </button>
                <p v-if="!accounts.length">Сначала создайте активный счёт в разделе «Счета».</p>
            </form>
        </section>
    </div>
</template>

<script>
import DateRange from '../DateRange.vue';
import { liveData } from '../live';
import listing from '../listing';
import finance, { axios, today } from '../finance';
import Feedback from '../Feedback.vue';
import Chart from '../Chart.vue';

export default {
    name: 'Operations',
    mixins: [
        finance,
        listing,
        liveData({
            operations: '/api/operations',
            categories: '/api/category',
            allAccounts: '/api/account?include_archived=1',
            accounts: '/api/account',
            rates: { url: '/api/exchange-rates', initial: null, interval: 300000 }
        })
    ],
    components: { DateRange, Feedback, Chart },
    data() {
        return {
            sortFields: [
                { value: 'description', label: 'Описание' },
                { value: 'amount', label: 'Сумма', type: 'number' },
                { value: 'operation_date', label: 'Дата операции' }
            ],
            sort: 'operation_date',
            search: '',
            type: '',
            account: '',
            category: '',
            from: '',
            to: '',
            currency: 'RUB',
            chartType: 'line',
            page: 1,
            form: {},
            quote: null,
            quoteLoading: false,
            quoteTimer: null,
            quoteRequest: 0
        };
    },
    computed: {
        sourceAccount() {
            return this.allAccounts.find(a => a.id === this.form.account_id);
        },
        destinationAccount() {
            return this.allAccounts.find(a => a.id === this.form.transfer_account_id);
        },
        foreignTransfer() {
            return (
                this.form.kind === 'transfer' &&
                this.sourceAccount &&
                this.destinationAccount &&
                this.sourceAccount.currency !== this.destinationAccount.currency
            );
        },
        quoteKey() {
            return [
                this.form.id,
                this.form.kind,
                this.form.account_id,
                this.form.transfer_account_id,
                this.form.amount,
                this.form.operation_date
            ].join(':');
        },
        filtered() {
            return this.sortItems(
                this.operations.filter(
                    op =>
                        (!op.transfer_account_id || Number(op.amount) < 0) &&
                        (op.description || '').toLowerCase().includes(this.search.toLowerCase()) &&
                        (!this.type ||
                            (op.transfer_account_id ? this.type === 'transfer' : op.category?.type === this.type)) &&
                        (!this.account || op.account_id === this.account || op.transfer_account_id === this.account) &&
                        (!this.category || op.category_id === this.category) &&
                        (!this.from || op.operation_date >= this.from) &&
                        (!this.to || op.operation_date <= this.to)
                )
            );
        },
        pages() {
            return Math.max(1, Math.ceil(this.filtered.length / this.pageSize));
        },
        paginated() {
            return this.pageItems;
        },
        chartOperations() {
            return this.filtered.filter(
                op => !op.transfer_account_id && op.account?.currency === this.currency && op.status === 'accepted'
            );
        },
        dates() {
            return [...new Set(this.chartOperations.map(op => op.operation_date))].sort();
        },
        series() {
            return ['income', 'expense'].map((kind, index) => ({
                name: index ? 'Расходы' : 'Доходы',
                data: this.dates.map(date =>
                    this.chartOperations
                        .filter(op => op.operation_date === date && op.category?.type === kind)
                        .reduce((sum, op) => sum + Math.abs(Number(op.amount)), 0)
                )
            }));
        },
        totals() {
            return this.series.map(item => item.data.reduce((sum, value) => sum + value, 0));
        },
        formAccounts() {
            return this.allAccounts.filter(
                a => a.status === 'active' || a.id === this.form.account_id || a.id === this.form.transfer_account_id
            );
        },
        destinations() {
            return this.formAccounts.filter(a => a.id !== this.form.account_id);
        }
    },
    watch: {
        quoteKey() {
            this.scheduleQuote();
        },
        foreignTransfer() {
            this.scheduleQuote();
        },
        rates() {
            this.scheduleQuote();
        },
        '$route.query.type'() {
            this.reset();
        },
        'form.account_id'() {
            if (!this.destinations.some(a => a.id === this.form.transfer_account_id))
                this.form.transfer_account_id = null;
        }
    },
    mounted() {
        this.reset();
        window.addEventListener('online', this.scheduleQuote);
    },
    beforeUnmount() {
        window.clearTimeout(this.quoteTimer);
        this.quoteRequest++;
        window.removeEventListener('online', this.scheduleQuote);
    },
    methods: {
        scheduleQuote() {
            window.clearTimeout(this.quoteTimer);
            this.quoteRequest++;
            this.quote = null;
            this.quoteLoading = false;
            if (!this.foreignTransfer || Number(this.form.amount) <= 0 || !this.form.operation_date) return;
            this.quoteLoading = true;
            this.quoteTimer = window.setTimeout(this.calculateTransfer, 400);
        },
        async calculateTransfer() {
            const key = this.quoteKey;
            const request = ++this.quoteRequest;
            this.quoteLoading = true;
            this.error = '';
            try {
                const response = await axios.get('/api/exchange-rates/quote', {
                    timeout: 20000,
                    params: {
                        from: this.sourceAccount.currency,
                        to: this.destinationAccount.currency,
                        amount: this.form.amount,
                        date: this.form.operation_date,
                        operation_id: this.form.id || undefined,
                        account_id: this.form.account_id,
                        transfer_account_id: this.form.transfer_account_id
                    }
                });
                if (request === this.quoteRequest && key === this.quoteKey) this.quote = response.data;
            } catch (error) {
                if (request !== this.quoteRequest) return;
                this.error = error.response?.data?.errors
                    ? Object.values(error.response.data.errors).flat().join(' ')
                    : 'Не удалось получить курс. Повторите попытку позже.';
                if (!error.response || error.response.status >= 500 || error.response.status === 429) {
                    this.quoteTimer = window.setTimeout(this.scheduleQuote, 10000);
                }
            } finally {
                if (request === this.quoteRequest) this.quoteLoading = false;
            }
        },
        reset() {
            this.form = {
                kind: this.$route.query.type === 'transfer' ? 'transfer' : 'regular',
                account_id: '',
                transfer_account_id: null,
                category_id: '',
                amount: '',
                operation_date: today(),
                description: ''
            };
            this.error = '';
        },
        edit(op) {
            this.error = '';
            this.form = {
                ...op,
                kind: op.transfer_account_id ? 'transfer' : 'regular',
                amount: Math.abs(Number(op.amount))
            };
        },
        async save() {
            if (this.foreignTransfer && !this.quote) {
                this.error = 'Дождитесь автоматического расчёта зачисления по курсу Банка России.';
                return;
            }
            const payload = {
                ...this.form,
                confirmed_rate: this.quote?.rate,
                confirmed_rate_date: this.quote?.rate_date
            };
            await this.perform(async () => {
                if (this.form.id) await axios.put(`/api/operations/update/${this.form.id}`, payload);
                else
                    await axios.post(
                        this.form.kind === 'transfer' ? '/api/operations/transfer' : '/api/operations/create',
                        payload
                    );
                this.reset();
            }, 'Операция сохранена. Балансы и лимиты пересчитаны.');
        },
        async remove(op) {
            if (
                confirm(
                    op.transfer_account_id
                        ? 'Удалить перевод и отменить изменения балансов обоих счетов?'
                        : 'Удалить операцию и пересчитать баланс?'
                )
            )
                await this.perform(async () => {
                    await axios.delete(`/api/operations/delete/${op.id}`);
                    if (this.form.id === op.id) this.reset();
                }, 'Операция удалена.');
        },
        exportCsv() {
            const rows = [
                [
                    'Дата',
                    'Счёт',
                    'Категория / получатель',
                    'Сумма',
                    'Валюта',
                    'Описание',
                    'Зачислено',
                    'Валюта зачисления',
                    'Курс',
                    'Дата курса'
                ],
                ...this.filtered.map(op => [
                    op.operation_date,
                    op.account?.name,
                    op.transfer_account_id ? op.transfer_account?.name : op.category?.name,
                    op.amount,
                    op.account?.currency,
                    op.description,
                    op.transfer_amount,
                    op.transfer_account?.currency,
                    op.exchange_rate,
                    op.exchange_rate_date
                ])
            ];
            const csv =
                '\uFEFF' +
                rows
                    .map(row =>
                        row
                            .map(
                                value =>
                                    '"' +
                                    String(value ?? '')
                                        .replace(/^[=+@-]/, "'$&")
                                        .replaceAll('"', '""') +
                                    '"'
                            )
                            .join(';')
                    )
                    .join('\r\n');
            const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
            const link = document.createElement('a');
            link.href = url;
            link.download = `operations_${today()}.csv`;
            link.click();
            URL.revokeObjectURL(url);
        }
    }
};
</script>
