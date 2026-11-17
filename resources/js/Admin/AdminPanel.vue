<template>
    <div class="top-command-bar">
        <div>
            <div class="cmd-crumb">РАЗДЕЛ 08 // СИСТЕМНОЕ АДМИНИСТРИРОВАНИЕ</div>
            <h1 class="cmd-title">АДМИН-ПУЛЬТ СИСТЕМЫ</h1>
        </div>
        <div class="cmd-actions">
            <button class="brut-btn-secondary" :disabled="busy" @click="backup">РЕЗЕРВНАЯ КОПИЯ БД ↓</button>
            <button class="brut-btn-primary" :disabled="busy" @click="exportCsv">СКАЧАТЬ ОТЧЁТ ↓</button>
        </div>
    </div>
    <Feedback :error="error || dataError" :notice="notice" :loading="loading || dataLoading" />
    <div class="ops-filters">
        <DateRange v-model:from="from" v-model:to="to" />
        <select class="brut-input-inline" v-model="currency" aria-label="Валюта графика">
            <option v-for="(label, key) in currencies" :key="key" :value="key">{{ currencyName(key) }}</option>
        </select>
    </div>
    <div class="admin-kpi-6">
        <div v-for="(label, key) in metrics" :key="key" class="kpi-brut">
            <div class="kb-top">{{ label }}</div>
            <div class="kb-val">{{ stats[key] || 0 }}</div>
        </div>
    </div>
    <div class="admin-charts-row">
        <section class="brut-panel">
            <div class="panel-head">РЕГИСТРАЦИИ ПОЛЬЗОВАТЕЛЕЙ</div>
            <div class="chart-padding">
                <Chart
                    type="line"
                    :labels="(stats.users_by_date || []).map(i => i.date)"
                    :series="[{ name: 'Пользователи', data: (stats.users_by_date || []).map(i => Number(i.count)) }]"
                />
            </div>
        </section>
        <section class="brut-panel">
            <div class="panel-head">
                <span>ДОХОДЫ И РАСХОДЫ СИСТЕМЫ</span>
                <span>{{ currencyName(currency) }}</span>
            </div>
            <div class="chart-padding">
                <Chart
                    :labels="(stats.operations_by_date || []).map(i => i.date)"
                    :series="
                        ['income', 'expense'].map((kind, index) => ({
                            name: index ? 'Расходы' : 'Доходы',
                            data: (stats.operations_by_date || []).map(i => Number(i[kind]))
                        }))
                    "
                />
            </div>
        </section>
    </div>
    <ListControls
        :fields="sortFields"
        v-model:sort="sort"
        v-model:direction="direction"
        v-model:size="pageSize"
        :sizes="[8, 16, 32, 64]"
    />
    <section class="brut-panel">
        <div class="panel-head">
            <span>РЕЕСТР ПОЛЬЗОВАТЕЛЕЙ СИСТЕМЫ</span>
            <input
                class="brut-input-inline search-input"
                v-model.trim="search"
                placeholder="Имя или почта…"
                aria-label="Поиск пользователей"
                type="search"
            />
        </div>
        <div class="table-wrap">
            <table class="brut-table">
                <thead>
                    <tr>
                        <th>НОМЕР</th>
                        <th>ИМЯ И ФАМИЛИЯ</th>
                        <th>ЭЛЕКТРОННАЯ ПОЧТА</th>
                        <th>ТЕЛЕФОН</th>
                        <th>РОЛЬ</th>
                        <th>РЕГИСТРАЦИЯ</th>
                        <th>ДЕЙСТВИЯ</th>
                    </tr>
                </thead>
                <TransitionGroup name="list" tag="tbody">
                    <tr v-for="user in userPage.data" :key="user.id">
                        <td>#{{ user.id }}</td>
                        <td>
                            <strong>{{ user.first_name }} {{ user.last_name }}</strong>
                            <div v-if="user.is_blocked" class="brut-tag-outline">ЗАБЛОКИРОВАН</div>
                        </td>
                        <td>{{ user.email }}</td>
                        <td>{{ user.phone || '—' }}</td>
                        <td>
                            <select
                                class="brut-input-inline"
                                :value="user.role_id"
                                :disabled="busy || user.id === currentId"
                                @change="setRole(user, $event)"
                            >
                                <option v-for="role in roles" :key="role.id" :value="role.id">
                                    {{ role.name === 'admin' ? 'Администратор' : 'Пользователь' }}
                                </option>
                            </select>
                        </td>
                        <td>{{ date(user.created_at) }}</td>
                        <td>
                            <span v-if="user.id === currentId" class="mini-act">ТЕКУЩИЙ ПРОФИЛЬ</span>
                            <div v-else class="vc-btns">
                                <button class="mini-act" :disabled="busy" @click="block(user)">
                                    {{ user.is_blocked ? 'РАЗБЛОКИРОВАТЬ' : 'БЛОКИРОВАТЬ' }}
                                </button>
                                <button class="mini-act" :disabled="busy" @click="remove(user)">УДАЛИТЬ</button>
                            </div>
                        </td>
                    </tr>
                    <tr key="static-5253" v-if="!userPage.data.length">
                        <td colspan="7" class="empty-state">ПОЛЬЗОВАТЕЛИ НЕ НАЙДЕНЫ</td>
                    </tr>
                </TransitionGroup>
            </table>
        </div>
    </section>
    <ListPager v-model:page="page" :pages="userPage.last_page" :total="userPage.total" :size="pageSize" />
</template>

<script>
import DateRange from '../DateRange.vue';
import { liveData } from '../live';
import ListPager from '../ListPager.vue';
import finance, { axios, today } from '../finance';
import Feedback from '../Feedback.vue';
import Chart from '../Chart.vue';
import ListControls from '../ListControls.vue';

export default {
    name: 'AdminPanel',
    mixins: [
        finance,
        liveData({
            stats: {
                url: '/api/admin/stats',
                initial: {},
                params() {
                    return {
                        from_date: this.from || undefined,
                        to_date: this.to || undefined,
                        currency: this.currency
                    };
                }
            },
            roles: '/api/admin/roles',
            profile: { url: '/api/profile', initial: {} },
            userPage: {
                url: '/api/admin/users',
                initial: { data: [], last_page: 1, total: 0 },
                params() {
                    return {
                        search: this.searchQuery,
                        page: this.page,
                        sort: this.sort,
                        direction: this.direction,
                        per_page: this.pageSize
                    };
                }
            }
        })
    ],
    components: { DateRange, Feedback, Chart, ListControls, ListPager },
    data() {
        return {
            sort: 'created_at',
            direction: 'desc',
            pageSize: 8,
            sortFields: [
                { value: 'first_name', label: 'Имя' },
                { value: 'id', label: 'Номер' },
                { value: 'email', label: 'Почта' },
                { value: 'created_at', label: 'Регистрация' }
            ],
            search: '',
            searchQuery: '',
            searchTimer: null,
            page: 1,
            from: today().slice(0, 7) + '-01',
            to: today(),
            currency: 'RUB',
            metrics: {
                users_count: 'ПОЛЬЗОВАТЕЛЕЙ',
                accounts_count: 'СЧЕТОВ',
                operations_count: 'ОПЕРАЦИЙ',
                categories_count: 'КАТЕГОРИЙ',
                goals_count: 'ЦЕЛЕЙ',
                budgets_count: 'ЛИМИТОВ'
            }
        };
    },
    computed: {
        currentId() {
            return this.profile.id;
        }
    },
    watch: {
        search() {
            window.clearTimeout(this.searchTimer);
            this.searchTimer = window.setTimeout(() => {
                this.page = 1;
                this.searchQuery = this.search;
            }, 250);
        },
        sort() {
            this.page = 1;
        },
        direction() {
            this.page = 1;
        },
        pageSize() {
            this.page = 1;
        },
        userPage(value) {
            if (value.current_page === this.page) this.page = Math.min(this.page, value.last_page);
        }
    },
    beforeUnmount() {
        window.clearTimeout(this.searchTimer);
    },
    methods: {
        async setRole(user, event) {
            const role = Number(event.target.value);
            const result = await this.perform(async () => {
                await axios.put(`/api/admin/users/${user.id}/role`, { role_id: role });
            }, 'Роль изменена.');
            if (!result) event.target.value = user.role_id;
        },
        async block(user) {
            if (
                confirm(
                    `${user.is_blocked ? 'Разблокировать' : 'Заблокировать'} пользователя ${user.first_name} ${user.last_name}?`
                )
            )
                await this.perform(async () => {
                    await axios.put(`/api/admin/users/${user.id}/block`, { is_blocked: !user.is_blocked });
                }, 'Статус пользователя обновлён.');
        },
        async remove(user) {
            if (
                confirm(
                    `Удалить пользователя ${user.first_name} ${user.last_name} вместе со всеми его финансовыми данными?`
                )
            )
                await this.perform(async () => {
                    await axios.delete(`/api/admin/users/${user.id}`);
                }, 'Пользователь удалён.');
        },
        async backup() {
            await this.perform(async () => {
                const response = await axios.post('/api/admin/backup', {}, { responseType: 'blob' });
                const url = URL.createObjectURL(response.data);
                const link = document.createElement('a');
                link.href = url;
                link.download = `finance_резервная_копия_${today()}.sql`;
                link.click();
                setTimeout(() => URL.revokeObjectURL(url), 1000);
            }, 'Резервная копия создана и передана для скачивания.');
        },
        async exportCsv() {
            await this.perform(async () => {
                const response = await axios.get('/api/admin/export-csv', {
                    params: { from_date: this.from, to_date: this.to },
                    responseType: 'blob'
                });
                const url = URL.createObjectURL(response.data);
                const link = document.createElement('a');
                link.href = url;
                link.download = `finance_report_${today()}.csv`;
                link.click();
                URL.revokeObjectURL(url);
            });
        }
    }
};
</script>
