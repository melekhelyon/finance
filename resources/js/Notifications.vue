<template>
    <div class="notifications-block">
        <button class="theme-toggle full-w" @click="open = true">
            УВЕДОМЛЕНИЯ
            <span v-if="unread">[{{ unread }}]</span>
        </button>
        <Modal :visible="open" title="ФИНАНСОВЫЕ УВЕДОМЛЕНИЯ" @close="open = false">
            <Feedback :error="error || dataError" />
            <button v-if="unread" class="brut-btn-secondary" :disabled="busy" @click="read('all')">
                ПРОЧИТАТЬ ВСЕ
            </button>
            <div v-for="item in items" :key="item.id" class="notification-item">
                <strong>{{ item.read_at ? '' : '● ' }}{{ item.title }}</strong>
                <p>{{ localizeText(item.message) }}</p>
                <small>{{ new Date(item.created_at.replace(' ', 'T') + 'Z').toLocaleString('ru-RU') }}</small>
                <button class="mini-act" @click="visit(item)">ОТКРЫТЬ →</button>
            </div>
            <p v-if="!items.length">
                Уведомлений пока нет. Здесь появятся результаты операций, предупреждения о лимитах и изменения целей.
            </p>
        </Modal>
    </div>
</template>

<script>
import { liveData } from './live';
import finance, { axios } from './finance';
import Feedback from './Feedback.vue';
import Modal from './User/Modal.vue';

export default {
    name: 'Notifications',
    mixins: [finance, liveData({ notificationData: { url: '/api/notifications', initial: { items: [], unread: 0 } } })],
    components: { Feedback, Modal },
    data() {
        return { open: false };
    },
    computed: {
        items() {
            return this.notificationData.items;
        },
        unread() {
            return this.notificationData.unread;
        }
    },
    methods: {
        async read(id) {
            await this.perform(async () => {
                await axios.put(`/api/notifications/${id}/read`);
            });
        },
        async visit(item) {
            if (!item.read_at) await this.read(item.id);
            this.open = false;
            await this.$router.push(item.path);
        }
    }
};
</script>
