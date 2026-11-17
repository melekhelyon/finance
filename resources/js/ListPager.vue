<template>
    <div class="pager-box">
        <button class="mini-act" :disabled="page <= 1" @click="$emit('update:page', page - 1)">◄ НАЗАД</button>
        <span>
            {{ page }} / {{ pages }} · {{ total ? (page - 1) * size + 1 : 0 }}–{{ Math.min(page * size, total) }} ИЗ
            {{ total }}
        </span>
        <button class="mini-act" :disabled="page >= pages" @click="$emit('update:page', page + 1)">ВПЕРЁД ►</button>
    </div>
</template>

<script>
export default {
    name: 'ListPager',
    props: { page: Number, pages: Number, total: Number, size: { type: Number, default: 8 } },
    emits: ['update:page']
};
</script>
