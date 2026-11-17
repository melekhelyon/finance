<template>
    <div class="top-command-bar">
        <div>
            <div class="cmd-crumb">РАЗДЕЛ 05 // СТРАТЕГИЧЕСКИЕ НАКОПЛЕНИЯ</div>
            <h1 class="cmd-title">ФИНАНСОВЫЕ ЦЕЛИ</h1>
        </div>
        <div class="cmd-actions">
            <input
                class="brut-input-inline search-input"
                type="search"
                v-model="search"
                placeholder="ПОИСК: Название цели…"
                aria-label="Поиск целей"
            />
            <select class="brut-input-inline" v-model="status" aria-label="Статус цели">
                <option value="">Все статусы</option>
                <option v-for="(label, key) in goalStatuses" :key="key" :value="key">{{ label }}</option>
            </select>
            <button class="brut-btn-primary" @click="edit()">+ СОЗДАТЬ НОВУЮ ЦЕЛЬ</button>
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
    <TransitionGroup name="list" tag="div" class="goals-grid-3">
        <article
            v-for="goal in pageItems"
            :key="goal.id"
            class="goal-brut-card"
            :class="{ inv: goal.status === 'achieved' }"
        >
            <div class="gbc-top">
                <span class="brut-tag-outline">{{ goalStatuses[goal.status] }}</span>
                <span class="gbc-date">СРОК: {{ date(goal.target_date) }}</span>
            </div>
            <div class="gbc-title">{{ goal.name }}</div>
            <div>{{ goal.description }}</div>
            <div class="gbc-numbers">
                <div>
                    <small>НАКОПЛЕНО</small>
                    <strong>{{ money(goal.current_amount, goal.currency) }}</strong>
                </div>
                <div>
                    <small>ЦЕЛЬ</small>
                    <strong>{{ money(goal.target_amount, goal.currency) }}</strong>
                </div>
                <div class="gbc-pct">{{ percent(goal.current_amount, goal.target_amount) }}%</div>
            </div>
            <div class="brut-meter">
                <div
                    class="brut-meter-fill"
                    :style="{ width: Math.min(100, percent(goal.current_amount, goal.target_amount)) + '%' }"
                ></div>
            </div>
            <div class="gbc-controls">
                <button class="mini-act" :disabled="busy || goal.status === 'failed'" @click="progress(goal, -1)">
                    − УБАВИТЬ
                </button>
                <button class="mini-act" :disabled="busy || goal.status !== 'in_progress'" @click="progress(goal, 1)">
                    + ПОПОЛНИТЬ
                </button>
                <button class="mini-act" @click="edit(goal)">ИЗМ.</button>
                <button class="mini-act" :disabled="busy" @click="remove(goal)">УДАЛ.</button>
            </div>
        </article>
    </TransitionGroup>
    <div v-if="!loading && !filtered.length" class="empty-state">ЦЕЛИ НЕ НАЙДЕНЫ. СОЗДАЙТЕ ПЕРВУЮ ЦЕЛЬ.</div>
    <section class="brut-panel">
        <div class="panel-head">
            <span>ГРАФИК ВЫПОЛНЕНИЯ ЦЕЛЕЙ</span>
            <span>
                НАКОПЛЕНО:
                {{
                    money(
                        goals
                            .filter(g => g.currency === currency)
                            .reduce((sum, g) => sum + Number(g.current_amount), 0),
                        currency
                    )
                }}
            </span>
        </div>
        <div class="horiz-bars-wrap">
            <div v-for="goal in pageItems" :key="goal.id" class="hb-row">
                <div class="hb-lbl">{{ goal.name }}</div>
                <div class="hb-track">
                    <div
                        class="hb-fill"
                        :style="{ width: Math.min(100, percent(goal.current_amount, goal.target_amount)) + '%' }"
                    ></div>
                </div>
                <div class="hb-val">
                    ОСТАЛОСЬ: {{ money(Number(goal.target_amount) - Number(goal.current_amount), goal.currency) }}
                </div>
            </div>
        </div>
    </section>
    <ListPager v-model:page="page" :pages="pageCount" :total="filtered.length" :size="pageSize" />
    <Modal
        :visible="visible"
        :title="progressGoal ? 'ИЗМЕНИТЬ НАКОПЛЕНИЯ' : form.id ? 'ИЗМЕНИТЬ ЦЕЛЬ' : 'НОВАЯ ЦЕЛЬ'"
        @close="visible = false"
    >
        <Feedback :error="error || dataError" />
        <form v-if="progressGoal" @submit.prevent="saveProgress">
            <p>{{ progressGoal.name }}</p>
            <label class="brut-field">
                <span class="bf-label">{{ progressDirection > 0 ? 'СУММА ПОПОЛНЕНИЯ' : 'СУММА УМЕНЬШЕНИЯ' }}</span>
                <input class="bf-input" type="number" min="0.01" step="0.01" v-model="progressAmount" required />
            </label>
            <button class="brut-btn-primary full-w" :disabled="busy">СОХРАНИТЬ →</button>
        </form>
        <form v-else @submit.prevent="save">
            <label class="brut-field">
                <span class="bf-label">НАЗВАНИЕ</span>
                <input class="bf-input" v-model.trim="form.name" maxlength="150" required />
            </label>
            <label class="brut-field">
                <span class="bf-label">ОПИСАНИЕ</span>
                <textarea class="bf-input" v-model.trim="form.description" required maxlength="5000"></textarea>
            </label>
            <label class="brut-field">
                <span class="bf-label">ВАЛЮТА ЦЕЛИ</span>
                <select
                    class="bf-input"
                    v-model="form.currency"
                    :disabled="!!form.id && Number(form.current_amount) > 0"
                >
                    <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                </select>
            </label>
            <label class="brut-field">
                <span class="bf-label">ЦЕЛЕВАЯ СУММА</span>
                <input class="bf-input" type="number" v-model="form.target_amount" min="0.01" step="0.01" required />
            </label>
            <label class="brut-field">
                <span class="bf-label">НАКОПЛЕНО</span>
                <input
                    class="bf-input"
                    type="number"
                    v-model="form.current_amount"
                    min="0"
                    :max="form.target_amount"
                    step="0.01"
                    required
                />
            </label>
            <label class="brut-field">
                <span class="bf-label">ДАТА НАЧАЛА</span>
                <input class="bf-input" type="date" v-model="form.start_date" required />
            </label>
            <label class="brut-field">
                <span class="bf-label">ПЛАНИРУЕМАЯ ДАТА</span>
                <input class="bf-input" type="date" v-model="form.target_date" :min="form.start_date" />
            </label>
            <label v-if="form.id" class="brut-field">
                <span class="bf-label">СТАТУС</span>
                <select class="bf-input" v-model="form.status">
                    <option value="in_progress">В процессе</option>
                    <option value="failed">Не достигнута</option>
                    <option value="achieved">Достигнута</option>
                </select>
            </label>
            <button class="brut-btn-primary full-w" :disabled="busy">СОХРАНИТЬ ЦЕЛЬ →</button>
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
    name: 'Goals',
    mixins: [finance, listing, liveData({ goals: '/api/goals' })],
    components: { Feedback, Modal },
    data() {
        return {
            sortFields: [
                { value: 'name', label: 'Название' },
                { value: 'target_amount', label: 'Целевая сумма', type: 'number' },
                { value: 'current_amount', label: 'Накоплено', type: 'number' },
                { value: 'target_date', label: 'Планируемая дата' },
                { value: 'created_at', label: 'Дата создания' }
            ],
            search: '',
            status: '',
            visible: false,
            form: {},
            progressGoal: null,
            progressDirection: 1,
            progressAmount: '',
            currency: 'RUB'
        };
    },
    computed: {
        filtered() {
            return this.sortItems(
                this.goals.filter(
                    g =>
                        g.name.toLowerCase().includes(this.search.toLowerCase()) &&
                        (!this.status || g.status === this.status)
                )
            );
        }
    },
    mounted() {
        this.currency = this.currentUser.default_currency || 'RUB';
    },
    methods: {
        exportReport() {
            this.downloadCsv('goals', [
                ['Цель', g => g.name],
                ['Накоплено', g => g.current_amount],
                ['Целевая сумма', g => g.target_amount],
                ['Валюта', g => g.currency],
                ['Планируемая дата', g => g.target_date],
                ['Статус', g => this.goalStatuses[g.status]]
            ]);
        },
        edit(goal) {
            this.error = '';
            this.progressGoal = null;
            this.form = goal
                ? { ...goal }
                : {
                      name: '',
                      description: '',
                      currency: this.currentUser.default_currency || 'RUB',
                      target_amount: '',
                      current_amount: 0,
                      start_date: today(),
                      target_date: '',
                      status: 'in_progress'
                  };
            this.visible = true;
        },
        progress(goal, direction) {
            this.error = '';
            this.progressGoal = goal;
            this.progressDirection = direction;
            this.progressAmount = '';
            this.visible = true;
        },
        async saveProgress() {
            await this.perform(async () => {
                await axios.post(`/api/goals/${this.progressGoal.id}/progress`, {
                    amount: Number(this.progressAmount) * this.progressDirection
                });
                this.visible = false;
            }, 'Накопления обновлены.');
        },
        async save() {
            await this.perform(async () => {
                if (this.form.id) await axios.put(`/api/goals/update/${this.form.id}`, this.form);
                else await axios.post('/api/goals/create', this.form);
                this.visible = false;
            }, 'Цель сохранена.');
        },
        async remove(goal) {
            if (confirm(`Удалить цель «${goal.name}»?`))
                await this.perform(async () => {
                    await axios.delete(`/api/goals/delete/${goal.id}`);
                }, 'Цель удалена.');
        }
    }
};
</script>
