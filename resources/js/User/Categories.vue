<template>
    <div class="top-command-bar">
        <div>
            <div class="cmd-crumb">РАЗДЕЛ 03 // ИЕРАРХИЯ ДОХОДОВ И РАСХОДОВ</div>
            <h1 class="cmd-title">КАТЕГОРИИ ОПЕРАЦИЙ</h1>
        </div>
        <div class="cmd-actions">
            <input
                class="brut-input-inline search-input"
                type="search"
                v-model="search"
                placeholder="Название категории…"
                aria-label="Поиск категорий"
            />
            <select class="brut-input-inline" v-model="type" aria-label="Тип категории">
                <option value="">Все типы</option>
                <option value="income">Доходы</option>
                <option value="expense">Расходы</option>
            </select>
            <button class="brut-btn-primary" @click="edit()">+ ДОБАВИТЬ КАТЕГОРИЮ</button>
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
    <div class="cat-split-3">
        <section v-for="kind in categoryKinds" :key="kind" class="brut-panel category-panel">
            <div class="panel-head" :class="{ inv: kind === 'income' }">
                <span>
                    КАТЕГОРИИ {{ kind === 'income' ? 'ДОХОДОВ' : 'РАСХОДОВ' }} ({{ categoryLists[kind].length }})
                </span>
                <span>{{ kind === 'income' ? 'ПОСТУПЛЕНИЯ ↑' : 'СПИСАНИЯ ↓' }}</span>
            </div>
            <TransitionGroup name="list" tag="div" class="cat-list">
                <article v-for="category in categoryPageItems(kind)" :key="category.id" class="cat-item">
                    <div>
                        <div class="ci-name">{{ category.name }}</div>
                        <div class="ci-parent">
                            Родитель:
                            {{ categories.find(c => c.id === category.parent_id)?.name || '— (Корневая категория)' }}
                        </div>
                    </div>
                    <div class="vc-btns">
                        <button class="mini-act" @click="edit(category)">ИЗМ.</button>
                        <button class="mini-act" @click="remove(category)" :disabled="busy">УДАЛ.</button>
                    </div>
                </article>
                <div v-if="!categoryLists[kind].length" key="empty" class="empty-state">КАТЕГОРИИ НЕ НАЙДЕНЫ</div>
            </TransitionGroup>
            <ListPager
                v-model:page="categoryPages[kind]"
                :pages="categoryPageCount(kind)"
                :total="categoryLists[kind].length"
                :size="pageSize"
            />
        </section>
        <section class="brut-panel">
            <div class="panel-head">СООТНОШЕНИЕ КАТЕГОРИЙ</div>
            <div class="chart-padding">
                <Chart
                    type="donut"
                    :labels="['Доходы', 'Расходы']"
                    :series="[
                        categories.filter(c => c.type === 'income').length,
                        categories.filter(c => c.type === 'expense').length
                    ]"
                />
            </div>
            <div class="form-stack">
                <div class="kpi-brut">
                    <div class="kb-top">ВСЕГО КАТЕГОРИЙ</div>
                    <div class="kb-val">{{ categories.length }}</div>
                </div>
                <div class="kpi-brut inv">
                    <div class="kb-top">ПОДКАТЕГОРИЙ</div>
                    <div class="kb-val">{{ categories.filter(c => c.parent_id).length }}</div>
                </div>
            </div>
        </section>
    </div>
    <Modal :visible="visible" :title="form.id ? 'ИЗМЕНИТЬ КАТЕГОРИЮ' : 'НОВАЯ КАТЕГОРИЯ'" @close="visible = false">
        <form @submit.prevent="save">
            <Feedback :error="error || dataError" />
            <label class="brut-field">
                <span class="bf-label">НАЗВАНИЕ</span>
                <input class="bf-input" v-model.trim="form.name" required maxlength="100" />
            </label>
            <label class="brut-field">
                <span class="bf-label">ТИП</span>
                <select class="bf-input" v-model="form.type" @change="form.parent_id = null">
                    <option value="income">Доход</option>
                    <option value="expense">Расход</option>
                </select>
            </label>
            <label class="brut-field">
                <span class="bf-label">РОДИТЕЛЬСКАЯ КАТЕГОРИЯ</span>
                <select class="bf-input" v-model="form.parent_id">
                    <option :value="null">Корневая категория</option>
                    <option v-for="category in parents" :key="category.id" :value="category.id">
                        {{ category.name }}
                    </option>
                </select>
            </label>
            <button class="brut-btn-primary full-w" :disabled="busy">СОХРАНИТЬ →</button>
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
    name: 'Categories',
    mixins: [finance, listing, liveData({ categories: '/api/category' })],
    components: { Feedback, Chart, Modal },
    data() {
        return {
            sortFields: [
                { value: 'name', label: 'Название' },
                { value: 'id', label: 'Номер', type: 'number' },
                { value: 'created_at', label: 'Дата создания' }
            ],
            categoryPages: { income: 1, expense: 1 },
            search: '',
            type: '',
            visible: false,
            form: {}
        };
    },
    computed: {
        categoryKinds() {
            return this.type ? [this.type] : ['income', 'expense'];
        },
        categoryLists() {
            return {
                income: this.filtered.filter(c => c.type === 'income'),
                expense: this.filtered.filter(c => c.type === 'expense')
            };
        },
        filtered() {
            return this.sortItems(
                this.categories.filter(
                    c =>
                        c.name.toLowerCase().includes(this.search.toLowerCase()) && (!this.type || c.type === this.type)
                )
            );
        },
        parents() {
            const excluded = new Set([this.form.id]);
            let changed = true;
            while (changed) {
                changed = false;
                this.categories.forEach(c => {
                    if (excluded.has(c.parent_id) && !excluded.has(c.id)) {
                        excluded.add(c.id);
                        changed = true;
                    }
                });
            }
            return this.categories.filter(c => c.type === this.form.type && !excluded.has(c.id));
        }
    },
    watch: {
        categoryLists() {
            for (const kind of ['income', 'expense'])
                this.categoryPages[kind] = Math.min(this.categoryPages[kind], this.categoryPageCount(kind));
        }
    },
    created() {
        this.$watch(
            () => [this.search, this.type, this.sort, this.direction, this.pageSize],
            () => {
                this.categoryPages = { income: 1, expense: 1 };
            }
        );
    },
    methods: {
        categoryPageCount(kind) {
            return Math.max(1, Math.ceil(this.categoryLists[kind].length / this.pageSize));
        },
        categoryPageItems(kind) {
            const start = (this.categoryPages[kind] - 1) * this.pageSize;
            return this.categoryLists[kind].slice(start, start + this.pageSize);
        },
        exportReport() {
            this.downloadCsv('categories', [
                ['Номер', c => c.id],
                ['Название', c => c.name],
                ['Тип', c => (c.type === 'income' ? 'Доход' : 'Расход')],
                ['Родитель', c => this.categories.find(p => p.id === c.parent_id)?.name],
                ['Создана', c => c.created_at]
            ]);
        },
        edit(category) {
            this.error = '';
            this.form = category ? { ...category } : { name: '', type: 'expense', parent_id: null };
            this.visible = true;
        },
        async save() {
            await this.perform(async () => {
                if (this.form.id) await axios.put(`/api/category/update/${this.form.id}`, this.form);
                else await axios.post('/api/category/create', this.form);
                this.visible = false;
            }, 'Категория сохранена.');
        },
        async remove(category) {
            if (confirm(`Удалить категорию «${category.name}»?`))
                await this.perform(async () => {
                    await axios.delete(`/api/category/delete/${category.id}`);
                }, 'Категория удалена.');
        }
    }
};
</script>
