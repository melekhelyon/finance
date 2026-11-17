<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="visible" class="modal-backdrop" @click.self="$emit('close')" @keydown.esc="$emit('close')">
                <section
                    ref="dialog"
                    class="brut-panel modal-panel"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title"
                    tabindex="-1"
                >
                    <div class="panel-head inv">
                        <span>{{ title }}</span>
                        <button type="button" class="mini-act" aria-label="Закрыть" @click="$emit('close')">×</button>
                    </div>
                    <div class="form-box-body"><slot /></div>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>

<script>
export default {
    name: 'Modal',
    props: { visible: Boolean, title: { default: 'РЕДАКТИРОВАНИЕ' } },
    emits: ['close'],
    data() {
        return { previousFocus: null };
    },
    watch: {
        async visible(value) {
            if (value) {
                this.previousFocus = document.activeElement;
                await this.$nextTick();
                this.$refs.dialog?.focus();
                document.addEventListener('keydown', this.trapFocus);
            } else {
                document.removeEventListener('keydown', this.trapFocus);
                this.previousFocus?.focus();
            }
        }
    },
    beforeUnmount() {
        document.removeEventListener('keydown', this.trapFocus);
    },
    methods: {
        trapFocus(event) {
            if (event.key !== 'Tab') return;
            const items = this.$refs.dialog?.querySelectorAll(
                'button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), a[href]'
            );
            if (!items?.length) return;
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && [first, this.$refs.dialog].includes(document.activeElement)) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    }
};
</script>
