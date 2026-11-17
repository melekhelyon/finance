<template>
    <TransitionGroup name="feedback">
        <div v-if="error" key="error" class="alert-discipline-bar" role="alert">[!] {{ error }}</div>
        <div v-if="notice" key="notice" class="alert-discipline-bar" role="status">[✓] {{ notice }}</div>
        <div v-if="loading" key="loading" class="empty-state" role="status">ЗАГРУЗКА ДАННЫХ…</div>
    </TransitionGroup>
</template>

<script>
export default {
    props: ['error', 'notice', 'loading']
};
</script>
