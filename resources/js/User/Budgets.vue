<template>
    <div class="top-command-bar">
        <div>
            <div class="cmd-crumb">РАЗДЕЛ 06 // ФИНАНСОВАЯ ДИСЦИПЛИНА</div>
            <h1 class="cmd-title">МЕСЯЧНЫЕ ЛИМИТЫ РАСХОДОВ</h1>
        </div>
        <div class="cmd-actions">
            <input
                class="brut-input-inline search-input"
                type="search"
                v-model="search"
                placeholder="ПОИСК: Категория расходов…"
                aria-label="Поиск лимитов"
            />
            <button class="brut-btn-primary" @click="edit()">+ УСТАНОВИТЬ НОВЫЙ ЛИМИТ</button>
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
    <div class="alert-discipline-bar">
        <span>
            <strong>СТАТУС ДИСЦИПЛИНЫ:</strong>
            {{ exceeded ? `ПЕРЕРАСХОД ПО ${exceeded} ЛИМИТАМ` : 'ПРЕВЫШЕНИЙ ЛИМИТОВ НЕТ' }}
        </span>
        <span class="brut-tag-sm">{{ exceeded ? 'ТРЕБУЕТ ВНИМАНИЯ [!]' : 'ПОД КОНТРОЛЕМ [✓]' }}</span>
    </div>
    <TransitionGroup name="list" tag="div" class="goals-grid-3">
        <article
            v-for="budget in pageItems"
            :key="budget.id"
            class="goal-brut-card"
            :class="{ inv: Number(budget.spent_amount) > Number(budget.planned_amount) }"
        >
            <div class="gbc-top">
                <span class="brut-tag-outline">{{ budgetStatus(budget) }}</span>
                <span class="gbc-date">{{ date(budget.period_start) }} — {{ date(budget.period_end) }}</span>
            </div>
            <div class="gbc-title">{{ budget.category?.name }}</div>
            <div class="gbc-numbers">
                <div>
                    <small>ПОТРАЧЕНО</small>
                    <strong>{{ money(budget.spent_amount, budget.currency) }}</strong>
                </div>
                <div>
                    <small>ЛИМИТ</small>
                    <strong>{{ money(budget.planned_amount, budget.currency) }}</strong>
                </div>
                <div class="gbc-pct">{{ percent(budget.spent_amount, budget.planned_amount) }}%</div>
            </div>
            <div class="brut-meter">
                <div
                    class="brut-meter-fill"
                    :style="{ width: Math.min(100, percent(budget.spent_amount, budget.planned_amount)) + '%' }"
                ></div>
            </div>
            <div class="gbc-controls">
                <span>
                    ОСТАТОК:
                    <strong>
                        {{ money(Number(budget.planned_amount) - Number(budget.spent_amount), budget.currency) }}
                    </strong>
                </span>
                <div class="vc-btns">
                    <button class="mini-act" @click="edit(budget)">ИЗМ.</button>
                    <button class="mini-act" :disabled="busy" @click="remove(budget)">УДАЛ.</button>
                </div>
            </div>
        </article>
    </TransitionGroup>
    <div v-if="!loading && !filtered.length" class="empty-state">ЛИМИТЫ НЕ НАЙДЕНЫ</div>
    <section class="brut-panel">
        <div class="panel-head">СВОДНАЯ ТАБЛИЦА КОНТРОЛЯ БЮДЖЕТА (ПЛАН ПРОТИВ ФАКТА)</div>
        <div class="table-wrap">
            <table class="brut-table">
                <thead>
                    <tr>
                        <th>КАТЕГОРИЯ</th>
                        <th>ПЛАНОВЫЙ ЛИМИТ</th>
                        <th>ПОТРАЧЕНО</th>
                        <th>ПЕРИОД</th>
                        <th>ОСТАТОК</th>
                    </tr>
                </thead>
                <TransitionGroup name="list" tag="tbody">
                    <tr v-for="budget in pageItems" :key="budget.id">
                        <td>{{ budget.category?.name }}</td>
                        <td>{{ money(budget.planned_amount, budget.currency) }}</td>
                        <td>{{ money(budget.spent_amount, budget.currency) }}</td>
                        <td>{{ date(budget.period_start) }} — {{ date(budget.period_end) }}</td>
                        <td>
                            {{ money(Number(budget.planned_amount) - Number(budget.spent_amount), budget.currency) }}
                        </td>
                    </tr>
                </TransitionGroup>
            </table>
        </div>
    </section>
    <ListPager v-model:page="page" :pages="pageCount" :total="filtered.length" :size="pageSize" />
    <Modal :visible="visible" :title="form.id ? 'ИЗМЕНИТЬ ЛИМИТ' : 'НОВЫЙ ЛИМИТ'" @close="visible = false">
        <form @submit.prevent="save">
            <Feedback :error="error || dataError" />
            <label class="brut-field">
                <span class="bf-label">КАТЕГОРИЯ РАСХОДОВ</span>
                <select class="bf-input" v-model="form.category_id" required>
                    <option value="" disabled>Выберите категорию</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">
                        {{ category.name }}
                    </option>
                </select>
            </label>
            <label class="brut-field">
                <span class="bf-label">ВАЛЮТА ЛИМИТА</span>
                <select class="bf-input" v-model="form.currency">
                    <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                </select>
            </label>
            <label class="brut-field">
                <span class="bf-label">ПЛАНИРУЕМАЯ СУММА</span>
                <input class="bf-input" type="number" min="0.01" step="0.01" v-model="form.planned_amount" required />
            </label>
            <label class="brut-field">
                <span class="bf-label">НАЧАЛО ПЕРИОДА</span>
                <input class="bf-input" type="date" v-model="form.period_start" required />
            </label>
            <label class="brut-field">
                <span class="bf-label">КОНЕЦ ПЕРИОДА</span>
                <input class="bf-input" type="date" v-model="form.period_end" :min="form.period_start" required />
            </label>
            <p>Расходы за выбранный период и в выбранной валюте рассчитываются автоматически.</p>
            <button class="brut-btn-primary full-w" :disabled="busy || !categories.length">СОХРАНИТЬ ЛИМИТ →</button>
            <p v-if="!categories.length">Сначала создайте категорию расходов.</p>
        </form>
    </Modal>
</template>

<script>
import { liveData } from '../live';
import listing from '../listing';
import finance, { axios, today } from '../finance';
import Feedback from '../Feedback.vue';
import Modal from './Modal.vue';

export default {
    name: 'Budgets',
    mixins: [
        finance,
        listing,
        liveData({
            budgets: '/api/budgets',
            categories: { url: '/api/category', select: items => items.filter(c => c.type === 'expense') }
        })
    ],
    components: { Feedback, Modal },
    data() {
        return {
            sortFields: [
                { value: 'category.name', label: 'Категория' },
                { value: 'planned_amount', label: 'Лимит', type: 'number' },
                { value: 'spent_amount', label: 'Потрачено', type: 'number' },
                { value: 'period_start', label: 'Начало периода' },
                { value: 'created_at', label: 'Дата создания' }
            ],
            search: '',
            visible: false,
            form: {}
        };
    },
    computed: {
        filtered() {
            return this.sortItems(
                this.budgets.filter(b => (b.category?.name || '').toLowerCase().includes(this.search.toLowerCase()))
            );
        },
        exceeded() {
            return this.budgets.filter(
                b =>
                    b.period_start <= today() &&
                    b.period_end >= today() &&
                    Number(b.spent_amount) > Number(b.planned_amount)
            ).length;
        }
    },
    methods: {
        exportReport() {
            this.downloadCsv('budgets', [
                ['Категория', b => b.category?.name],
                ['Лимит', b => b.planned_amount],
                ['Потрачено', b => b.spent_amount],
                ['Остаток', b => (Number(b.planned_amount) - Number(b.spent_amount)).toFixed(2)],
                ['Валюта', b => b.currency],
                ['Начало', b => b.period_start],
                ['Окончание', b => b.period_end]
            ]);
        },
        budgetStatus(budget) {
            const value = this.percent(budget.spent_amount, budget.planned_amount);
            return value > 100 ? '[!] ПЕРЕРАСХОД ЛИМИТА' : value >= 80 ? '[!] ЛИМИТ БЛИЗКО' : 'В ПРЕДЕЛАХ НОРМЫ';
        },
        edit(budget) {
            this.error = '';
            const now = new Date();
            const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            this.form = budget
                ? { ...budget }
                : {
                      category_id: '',
                      currency: this.currentUser.default_currency || 'RUB',
                      planned_amount: '',
                      period_start: today().slice(0, 7) + '-01',
                      period_end: `${today().slice(0, 7)}-${end.getDate()}`
                  };
            this.visible = true;
        },
        async save() {
            await this.perform(async () => {
                if (this.form.id) await axios.put(`/api/budgets/update/${this.form.id}`, this.form);
                else await axios.post('/api/budgets/create', this.form);
                this.visible = false;
            }, 'Лимит сохранён. Расходы пересчитаны.');
        },
        async remove(budget) {
            if (confirm('Удалить лимит для категории «' + budget.category?.name + '»?'))
                await this.perform(async () => {
                    await axios.delete(`/api/budgets/delete/${budget.id}`);
                }, 'Лимит удалён.');
        }
    }
};
</script>
