import ListControls from './ListControls.vue';
import ListPager from './ListPager.vue';

export function sortedItems(items, field, direction, type = 'text') {
    const value = item => field.split('.').reduce((result, key) => result?.[key], item);
    return [...items].sort((left, right) => {
        const a = value(left),
            b = value(right);
        const result =
            type === 'number'
                ? Number(a || 0) - Number(b || 0)
                : String(a || '').localeCompare(String(b || ''), 'ru', { sensitivity: 'base', numeric: true });
        return (direction === 'desc' ? -result : result) || left.id - right.id;
    });
}

export function exportCsv(filename, rows) {
    const csv =
        '\uFEFF' +
        rows
            .map(row =>
                row
                    .map(
                        value =>
                            '"' +
                            String(value ?? '')
                                .replace(/^[=+@\-\t\r]/, "'$&")
                                .replaceAll('"', '""') +
                            '"'
                    )
                    .join(';')
            )
            .join('\r\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
}

export default {
    components: { ListControls, ListPager },
    data() {
        return { sort: 'created_at', direction: 'desc', pageSize: 8, page: 1, sortFields: [] };
    },
    computed: {
        pageCount() {
            return Math.max(1, Math.ceil(this.filtered.length / this.pageSize));
        },
        pageItems() {
            return this.filtered.slice((this.page - 1) * this.pageSize, this.page * this.pageSize);
        }
    },
    watch: {
        filtered() {
            this.page = Math.min(this.page, this.pageCount);
        },
        pageSize() {
            this.page = 1;
        }
    },
    created() {
        const filters = [
            'search',
            'type',
            'currency',
            'status',
            'account',
            'category',
            'from',
            'to',
            'sort',
            'direction'
        ];
        this.$watch(
            () => filters.filter(key => key in this.$data).map(key => this[key]),
            () => {
                this.page = 1;
            }
        );
    },
    methods: {
        sortItems(items) {
            return sortedItems(
                items,
                this.sort,
                this.direction,
                this.sortFields.find(field => field.value === this.sort)?.type
            );
        },
        downloadCsv(name, columns) {
            exportCsv(name + '.csv', [
                columns.map(column => column[0]),
                ...this.filtered.map(item => columns.map(column => column[1](item)))
            ]);
        }
    }
};
