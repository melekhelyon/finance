<template>
    <div :class="isPublic ? 'pub-layout' : 'app-shell'">
        <NavBar />
        <main :class="isPublic ? 'public-main' : 'app-main'">
            <RouterView v-slot="{ Component }">
                <Transition name="page" mode="out-in">
                    <div :key="$route.path + ':' + sessionVersion" class="route-page">
                        <component :is="Component" />
                    </div>
                </Transition>
            </RouterView>
        </main>
    </div>
</template>

<script>
import NavBar from './NavBar.vue';
import { session } from './live';

export default {
    name: 'App',
    components: { NavBar },
    computed: {
        token() {
            return session.token;
        },
        sessionVersion() {
            return session.version;
        },
        isPublic() {
            return ['app.main', 'app.login', 'app.register'].includes(this.$route.name);
        }
    },
    watch: {
        token(value) {
            if (!value && !this.isPublic) this.$router.replace('/login');
            else if (value && ['app.login', 'app.register'].includes(this.$route.name))
                this.$router.replace('/usermain');
        }
    }
};
</script>
