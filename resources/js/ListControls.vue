<template>
    <div class="ops-filters list-controls">
        <label>
            СОРТИРОВКА
            <select class="brut-input-inline" :value="sort" @change="$emit('update:sort', $event.target.value)">
                <option v-for="field in fields" :key="field.value" :value="field.value">{{ field.label }}</option>
            </select>
        </label>
        <label>
            ПОРЯДОК
            <select
                class="brut-input-inline"
                :value="direction"
                @change="$emit('update:direction', $event.target.value)"
            >
                <option value="asc">По возрастанию</option>
                <option value="desc">По убыванию</option>
            </select>
        </label>
        <label>
            НА СТРАНИЦЕ
            <select class="brut-input-inline" :value="size" @change="$emit('update:size', Number($event.target.value))">
                <option v-for="count in sizes" :key="count" :value="count">{{ count }}</option>
            </select>
        </label>
        <button v-if="exportable" class="brut-btn-secondary" @click="$emit('export')">СКАЧАТЬ ОТЧЁТ ↓</button>
    </div>
</template>

<script>
export default {
    name: 'ListControls',
    props: {
        fields: Array,
        sort: String,
        direction: String,
        size: Number,
        sizes: { type: Array, default: () => [8, 16, 32, 64] },
        exportable: Boolean
    },
    emits: ['update:sort', 'update:direction', 'update:size', 'export']
};
</script>
