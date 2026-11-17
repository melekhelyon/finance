<template>
    <div class="top-command-bar">
        <div>
            <div class="cmd-crumb">РАЗДЕЛ 02 // УПРАВЛЕНИЕ АКТИВАМИ И КАРТАМИ</div>
            <h1 class="cmd-title">ФИНАНСОВЫЕ СЧЕТА</h1>
        </div>
        <div class="cmd-actions">
            <input
                class="brut-input-inline search-input"
                type="search"
                v-model="search"
                placeholder="ПОИСК: Название счёта…"
                aria-label="Поиск счетов"
            />
            <select class="brut-input-inline" v-model="type" aria-label="Тип счёта">
                <option value="">Все типы</option>
                <option v-for="(label, key) in accountTypes" :key="key" :value="key">{{ label }}</option>
            </select>
            <select class="brut-input-inline" v-model="currency" aria-label="Валюта">
                <option value="">Все валюты</option>
                <option v-for="(label, key) in currencies" :key="key" :value="key">{{ currencyName(key) }}</option>
            </select>
            <button class="brut-btn-primary" @click="edit()">+ ДОБАВИТЬ СЧЁТ</button>
        </div>
    </div>
    <Feedback :error="visible ? '' : error || dataError" :notice="notice" :loading="loading || dataLoading" />
    <ListControls
        :fields="sortFields"
        v-model:sort="sort"
        v-model:direction="direction"
        v-model:size="pageSize"
        exportable
        @export="exportReport"
    />
    <TransitionGroup name="list" tag="div" class="vault-cards-grid">
        <article
            v-for="account in pageItems"
            :key="account.id"
            class="vault-card"
            :class="{ inv: account.type === 'savings' }"
        >
            <div class="vc-header">
                <span class="brut-tag-outline">{{ accountTypes[account.type] }}</span>
                <span class="vc-curr">ВАЛЮТА: {{ currencyName(account.currency) }}</span>
            </div>
            <div class="vc-title">{{ account.name }}</div>
            <div class="vc-balance">{{ money(account.balance, account.currency) }}</div>
            <div class="vc-footer">
                <span>СТАТУС: АКТИВЕН</span>
                <div class="vc-btns">
                    <button class="mini-act" @click="edit(account)">ИЗМЕНИТЬ</button>
                    <button class="mini-act" @click="remove(account)" :disabled="busy">УДАЛИТЬ</button>
                </div>
            </div>
        </article>
        <button key="static-2502" class="vault-card add-card-slot" @click="edit()">
            <strong style="font-size: 36px">+</strong>
            <strong>ОТКРЫТЬ НОВЫЙ СЧЁТ</strong>
            <span>Дебетовый, кредитный, сберегательный или зарплатный</span>
        </button>
    </TransitionGroup>
    <div v-if="!loading && !filtered.length" class="empty-state">СЧЕТА НЕ НАЙДЕНЫ</div>
    <section class="brut-panel">
        <div class="panel-head">
            <span>СРАВНЕНИЕ ОБЪЁМА СРЕДСТВ ПО СЧЕТАМ</span>
            <select v-model="chartCurrency" aria-label="Валюта диаграммы">
                <option v-for="(label, key) in currencies" :key="key" :value="key">{{ currencyName(key) }}</option>
            </select>
        </div>
        <div class="chart-padding">
            <Chart
                horizontal
                :labels="chartAccounts.map(a => a.name)"
                :series="[{ name: currencyName(chartCurrency), data: chartAccounts.map(a => Number(a.balance)) }]"
            />
        </div>
    </section>
    <ListPager v-model:page="page" :pages="pageCount" :total="filtered.length" :size="pageSize" />
    <Modal :visible="visible" :title="form.id ? 'ИЗМЕНИТЬ СЧЁТ' : 'ОТКРЫТЬ СЧЁТ'" @close="visible = false">
        <form @submit.prevent="save">
            <Feedback :error="error || dataError" />
            <label class="brut-field">
                <span class="bf-label">НАЗВАНИЕ СЧЁТА</span>
                <input class="bf-input" v-model.trim="form.name" maxlength="100" required />
            </label>
            <label class="brut-field">
                <span class="bf-label">ТИП СЧЁТА</span>
                <select class="bf-input" v-model="form.type">
                    <option v-for="(label, key) in accountTypes" :key="key" :value="key">{{ label }}</option>
                </select>
            </label>
            <label class="brut-field">
                <span class="bf-label">ВАЛЮТА</span>
                <select class="bf-input" v-model="form.currency">
                    <option v-for="(label, key) in currencies" :key="key" :value="key">{{ label }}</option>
                </select>
            </label>
            <label class="brut-field">
                <span class="bf-label">{{ form.id ? 'ТЕКУЩИЙ БАЛАНС' : 'НАЧАЛЬНЫЙ БАЛАНС' }}</span>
                <input class="bf-input" type="number" step="0.01" v-model="form.balance" required />
            </label>
            <button class="brut-btn-primary full-w" :disabled="busy">СОХРАНИТЬ СЧЁТ →</button>
        </form>
    </Modal>
</template>

<script>
import { liveData } from '../live';
import listing from '../listing';
import finance, { axios } from '../finance';
import Feedback from '../Feedback.vue';
import Chart from '../Chart.vue';
import Modal from './Modal.vue';

export default {
    name: 'Accounts',
    mixins: [finance, listing, liveData({ accounts: '/api/account' })],
    components: { Feedback, Chart, Modal },
    data() {
        return {
            sortFields: [
                { value: 'name', label: 'Название' },
                { value: 'balance', label: 'Баланс', type: 'number' },
                { value: 'created_at', label: 'Дата создания' }
            ],
            search: '',
            type: '',
            currency: '',
            chartCurrency: 'RUB',
            visible: false,
            form: {}
        };
    },
    computed: {
        filtered() {
            return this.sortItems(
                this.accounts.filter(
                    a =>
                        a.name.toLowerCase().includes(this.search.toLowerCase()) &&
                        (!this.type || a.type === this.type) &&
                        (!this.currency || a.currency === this.currency)
                )
            );
        },
        chartAccounts() {
            return this.filtered.filter(a => a.currency === this.chartCurrency);
        }
    },
    methods: {
        exportReport() {
            this.downloadCsv('accounts', [
                ['Название', a => a.name],
                ['Тип', a => this.accountTypes[a.type]],
                ['Баланс', a => a.balance],
                ['Валюта', a => a.currency],
                ['Создан', a => a.created_at]
            ]);
        },
        edit(account) {
            this.error = '';
            this.form = account
                ? { ...account }
                : {
                      name: '',
                      type: 'debit',
                      currency: this.currentUser.default_currency || 'RUB',
                      balance: 0
                  };
            this.visible = true;
        },
        async save() {
            await this.perform(async () => {
                if (this.form.id) await axios.put(`/api/account/update/${this.form.id}`, this.form);
                else await axios.post('/api/account/create', this.form);
                this.visible = false;
            }, 'Счёт сохранён.');
        },
        async remove(account) {
            if (confirm(`Удалить счёт «${account.name}»? Счёт с операциями будет сохранён в истории.`))
                await this.perform(async () => {
                    await axios.delete(`/api/account/delete/${account.id}`);
                }, 'Счёт удалён из активных.');
        }
    }
};
</script>
