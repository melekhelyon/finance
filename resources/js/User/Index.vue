<template>
    <div class="top-command-bar">
        <div>
            <div class="cmd-crumb">РАЗДЕЛ 01 // ГЛАВНАЯ ПАНЕЛЬ УПРАВЛЕНИЯ</div>
            <h1 class="cmd-title">СВОДКА ФИНАНСОВ</h1>
        </div>
        <div class="cmd-actions">
            <router-link to="/operations" class="brut-btn-secondary">+ ЗАПИСАТЬ ДОХОД / РАСХОД</router-link>
            <router-link to="/operations?type=transfer" class="brut-btn-primary">⇄ ПЕРЕВЕСТИ СРЕДСТВА</router-link>
        </div>
    </div>
    <Feedback :error="error || dataError" :loading="loading || dataLoading" />
    <div class="ops-filters">
        <select class="brut-input-inline" v-model="currency" aria-label="Валюта сводки">
            <option v-for="(label, key) in currencies" :key="key" :value="key">{{ label }}</option>
        </select>
        <DateRange v-model:from="from" v-model:to="to" />
    </div>
    <ExchangeRates @loaded="rates = $event" />
    <div class="kpi-row-4">
        <div v-for="(item, index) in metrics" :key="item.label" class="kpi-brut" :class="{ inv: index === 0 }">
            <div class="kb-top">
                <span>{{ item.label }}</span>
                <span>0{{ index + 1 }}</span>
            </div>
            <div class="kb-val">{{ item.value }}</div>
            <div class="kb-foot">{{ item.foot }}</div>
        </div>
    </div>
    <div class="bento-dashboard">
        <div class="bento-left">
            <section class="brut-panel">
                <div class="panel-head">
                    <span>АКТИВНЫЕ СЧЕТА</span>
                    <router-link to="/accounts" class="brut-tag-sm">ВСЕ СЧЕТА →</router-link>
                </div>
                <div class="accounts-mini-grid">
                    <router-link
                        v-for="item in accounts.slice(0, 4)"
                        :key="item.id"
                        to="/accounts"
                        class="acc-tile"
                        :class="{ inv: item.type === 'savings' }"
                    >
                        <div class="at-type">{{ accountTypes[item.type] }}</div>
                        <div class="at-name">{{ item.name }}</div>
                        <div class="at-bal">{{ money(item.balance, item.currency) }}</div>
                    </router-link>
                </div>
                <div v-if="!accounts.length" class="empty-state">
                    <router-link to="/accounts">СОЗДАЙТЕ ПЕРВЫЙ СЧЁТ →</router-link>
                </div>
            </section>
            <section class="brut-panel">
                <div class="panel-head">МОДУЛИ УПРАВЛЕНИЯ ФИНАНСАМИ</div>
                <div class="modules-strip-5">
                    <router-link v-for="(item, index) in modules" :key="item.path" :to="item.path" class="mod-box">
                        <div class="mb-num">0{{ index + 1 }}</div>
                        <div class="mb-name">{{ item.name }}</div>
                        <div class="mb-desc">{{ item.description }}</div>
                    </router-link>
                </div>
            </section>
            <section class="brut-panel">
                <div class="panel-head">
                    <span>ДИНАМИКА ЗА ВЫБРАННЫЙ ПЕРИОД</span>
                    <span>{{ currencyName(currency) }}</span>
                </div>
                <div class="chart-padding"><Chart :labels="dates" :series="series" type="line" /></div>
            </section>
        </div>
        <div class="bento-right">
            <section class="brut-panel">
                <div class="panel-head">
                    <span>ПОСЛЕДНИЕ ОПЕРАЦИИ</span>
                    <router-link to="/operations" class="brut-tag-sm">ЖУРНАЛ →</router-link>
                </div>
                <div class="receipt-list">
                    <div v-for="item in recent" :key="item.id" class="receipt-row">
                        <div>
                            <strong>{{ item.description || item.category?.name || 'Перевод между счетами' }}</strong>
                            <small>{{ date(item.operation_date) }} • {{ item.account?.name }}</small>
                        </div>
                        <span class="brut-tag-outline">{{ money(item.amount, item.account?.currency) }}</span>
                    </div>
                    <div v-if="!recent.length" class="empty-state">ОПЕРАЦИЙ ПОКА НЕТ</div>
                </div>
            </section>
            <section class="brut-panel">
                <div class="panel-head">
                    <span>ПРОГРЕСС ЦЕЛЕЙ И ЛИМИТОВ</span>
                    <span class="brut-tag-sm">В РАБОТЕ</span>
                </div>
                <div class="form-stack">
                    <div v-for="goal in goals.slice(0, 2)" :key="'g' + goal.id">
                        <div class="kb-top">
                            <span>ЦЕЛЬ: {{ goal.name }}</span>
                            <span>{{ percent(goal.current_amount, goal.target_amount) }}%</span>
                        </div>
                        <div class="brut-meter">
                            <div
                                class="brut-meter-fill"
                                :style="{
                                    width: Math.min(100, percent(goal.current_amount, goal.target_amount)) + '%'
                                }"
                            ></div>
                        </div>
                    </div>
                    <div
                        v-for="budget in budgets
                            .filter(b => b.period_start <= currentDate && b.period_end >= currentDate)
                            .slice(0, 3)"
                        :key="'b' + budget.id"
                    >
                        <div class="kb-top">
                            <span>ЛИМИТ: {{ budget.category?.name }}</span>
                            <span>
                                {{ percent(budget.spent_amount, budget.planned_amount) }}%
                                {{
                                    Number(budget.spent_amount) > Number(budget.planned_amount)
                                        ? '[!] ПРЕВЫШЕН'
                                        : Number(budget.spent_amount) >= Number(budget.planned_amount) * 0.8
                                          ? '[!] БЛИЗКО'
                                          : ''
                                }}
                            </span>
                        </div>
                        <div class="brut-meter">
                            <div
                                class="brut-meter-fill"
                                :style="{
                                    width: Math.min(100, percent(budget.spent_amount, budget.planned_amount)) + '%'
                                }"
                            ></div>
                        </div>
                    </div>
                    <div v-if="!goals.length && !budgets.length" class="empty-state">СОЗДАЙТЕ ЦЕЛЬ ИЛИ ЛИМИТ</div>
                </div>
            </section>
            <section class="brut-panel">
                <div class="panel-head">СООТНОШЕНИЕ ДОХОДОВ И РАСХОДОВ</div>
                <div class="chart-padding"><Chart type="donut" :labels="['Доходы', 'Расходы']" :series="totals" /></div>
            </section>
        </div>
    </div>
</template>

<script>
import DateRange from '../DateRange.vue';
import { liveData } from '../live';
import finance, { axios, today } from '../finance';
import Feedback from '../Feedback.vue';
import Chart from '../Chart.vue';
import ExchangeRates from '../ExchangeRates.vue';

export default {
    name: 'UserMenu',
    mixins: [
        finance,
        liveData({
            accounts: '/api/account',
            operations: '/api/operations',
            goals: '/api/goals',
            budgets: '/api/budgets'
        })
    ],
    components: { DateRange, Feedback, Chart, ExchangeRates },
    data() {
        return {
            rates: null,
            currency: 'RUB',
            from: today().slice(0, 7) + '-01',
            to: today(),
            modules: [
                { path: '/accounts', name: 'СЧЕТА', description: 'Кошельки и банковские карты' },
                { path: '/categories', name: 'КАТЕГОРИИ', description: 'Классификатор доходов и трат' },
                { path: '/operations', name: 'ОПЕРАЦИИ', description: 'Журнал транзакций и переводов' },
                { path: '/goals', name: 'ЦЕЛИ', description: 'Копилки и этапы накоплений' },
                { path: '/budgets', name: 'ЛИМИТЫ', description: 'Контроль месячных бюджетов' }
            ]
        };
    },
    computed: {
        currentDate() {
            return today();
        },
        capital() {
            if (!this.rates && this.accounts.some(a => a.currency !== this.currency)) return null;
            return this.accounts.reduce(
                (sum, a) =>
                    sum +
                    Number(a.balance) *
                        (a.currency === this.currency
                            ? 1
                            : Number(this.rates.rates[a.currency]) / Number(this.rates.rates[this.currency])),
                0
            );
        },
        filtered() {
            return this.operations.filter(
                op =>
                    !op.transfer_account_id &&
                    op.status === 'accepted' &&
                    op.account?.currency === this.currency &&
                    (!this.from || op.operation_date >= this.from) &&
                    (!this.to || op.operation_date <= this.to)
            );
        },
        dates() {
            return [...new Set(this.filtered.map(op => op.operation_date))].sort();
        },
        series() {
            return ['income', 'expense'].map((kind, index) => ({
                name: index ? 'Расходы' : 'Доходы',
                data: this.dates.map(date =>
                    this.filtered
                        .filter(op => op.operation_date === date && op.category?.type === kind)
                        .reduce((sum, op) => sum + Math.abs(Number(op.amount)), 0)
                )
            }));
        },
        totals() {
            return this.series.map(s => s.data.reduce((sum, value) => sum + value, 0));
        },
        recent() {
            return this.operations.filter(op => !op.transfer_account_id || Number(op.amount) < 0).slice(0, 4);
        },
        metrics() {
            return [
                {
                    label: 'СОВОКУПНЫЙ КАПИТАЛ',
                    value: this.capital === null ? '—' : this.money(this.capital, this.currency),
                    foot: this.rates
                        ? (this.rates.stale ? 'СОХРАНЁННЫЙ КУРС • ' : 'КУРС ЦБ • ') + this.date(this.rates.rate_date)
                        : 'ДЛЯ ПЕРЕСЧЁТА НУЖЕН КУРС ЦБ'
                },
                {
                    label: 'ДОХОДЫ ЗА ПЕРИОД',
                    value: this.money(this.totals[0], this.currency),
                    foot: 'ПОСТУПЛЕНИЯ БЕЗ ПЕРЕВОДОВ'
                },
                {
                    label: 'РАСХОДЫ ЗА ПЕРИОД',
                    value: this.money(this.totals[1], this.currency),
                    foot: 'СПИСАНИЯ БЕЗ ПЕРЕВОДОВ'
                },
                {
                    label: 'ФИНАНСОВЫЕ ЦЕЛИ',
                    value: this.goals.filter(g => g.status === 'in_progress').length,
                    foot: 'АКТИВНЫХ ЦЕЛЕЙ'
                }
            ];
        }
    },
    mounted() {
        this.currency = this.currentUser.default_currency || 'RUB';
    }
};
</script>
