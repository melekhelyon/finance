import { reactive, shallowReactive, watch } from 'vue';
import axios from 'axios';

function localDate() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function storedUser() {
    try {
        return JSON.parse(localStorage.getItem('user') || '{}');
    } catch {
        return {};
    }
}

export const session = reactive({ token: localStorage.getItem('token'), user: storedUser(), version: 0 });
export const calendar = reactive({ date: localDate() });
export const appearance = reactive({ theme: document.documentElement.dataset.theme || 'light' });
const resources = new Map();
const channel = typeof BroadcastChannel === 'function' ? new BroadcastChannel('finance-updates') : null;
let timer;

export function setSession(token, user) {
    for (const entry of resources.values()) {
        entry.controller?.abort();
        entry.state.data = entry.initial;
    }
    resources.clear();
    session.token = token || null;
    session.user = user || {};
    session.version++;
    if (token) localStorage.setItem('token', token);
    else localStorage.removeItem('token');
    if (user) localStorage.setItem('user', JSON.stringify(user));
    else localStorage.removeItem('user');
}

watch(
    () => appearance.theme,
    theme => {
        document.documentElement.dataset.theme = theme;
        localStorage.setItem('theme', theme);
    }
);

function resource(definition) {
    const key = JSON.stringify([session.token, definition.url, definition.params || {}]);
    if (!resources.has(key)) {
        resources.set(key, {
            ...definition,
            initial: 'initial' in definition ? definition.initial : [],
            interval: definition.interval || 5000,
            state: shallowReactive({
                data: 'initial' in definition ? definition.initial : [],
                error: '',
                loaded: false,
                pending: false
            }),
            users: 0,
            dirty: true,
            checked: 0,
            promise: null
        });
    }
    return resources.get(key);
}

async function fetchResource(entry) {
    if (!session.token || !navigator.onLine) return;
    if (entry.promise) return entry.promise;
    const token = session.token;
    entry.state.pending = true;
    entry.promise = (async () => {
        do {
            entry.dirty = false;
            entry.controller = new AbortController();
            try {
                const response = await axios.get(entry.url, {
                    params: entry.params,
                    signal: entry.controller.signal,
                    timeout: 20000
                });
                if (session.token !== token) return;
                if (JSON.stringify(entry.state.data) !== JSON.stringify(response.data))
                    entry.state.data = response.data;
                entry.state.loaded = true;
                entry.state.error = '';
                if (entry.url === '/api/profile') {
                    session.user = response.data;
                    localStorage.setItem('user', JSON.stringify(response.data));
                }
            } catch (error) {
                if (axios.isCancel(error) || session.token !== token) return;
                entry.state.error =
                    error.response?.data?.message || 'Нет связи с сервером. Обновление продолжится автоматически.';
            } finally {
                entry.checked = Date.now();
            }
        } while (entry.dirty && entry.users && session.token === token);
    })().finally(() => {
        entry.promise = null;
        entry.state.pending = false;
    });
    return entry.promise;
}

async function refreshActive(force = false) {
    if (document.hidden || !navigator.onLine || !session.token) return;
    await Promise.all(
        [...resources.values()]
            .filter(
                entry =>
                    entry.users &&
                    (entry.dirty ||
                        Date.now() - entry.checked >=
                            (entry.state.error || entry.state.data?.stale
                                ? Math.min(60000, entry.interval)
                                : entry.interval) ||
                        (force && (entry.interval < 300000 || entry.state.error)))
            )
            .map(fetchResource)
    );
}

export async function invalidateData(broadcast = true) {
    for (const entry of resources.values()) {
        if (entry.interval < 300000) entry.dirty = true;
    }
    if (broadcast) channel?.postMessage({ type: 'updated' });
    await refreshActive();
}

export function startLiveUpdates() {
    if (timer) return;
    timer = window.setInterval(() => {
        calendar.date = localDate();
        refreshActive();
    }, 1000);
    const resume = () => refreshActive(true);
    document.addEventListener('visibilitychange', resume);
    window.addEventListener('focus', resume);
    window.addEventListener('online', resume);
    window.addEventListener('offline', () => {
        for (const entry of resources.values()) {
            if (entry.users)
                entry.state.error = 'Нет соединения. Данные обновятся автоматически после восстановления связи.';
        }
    });
    window.addEventListener('storage', event => {
        if (event.key === 'theme') appearance.theme = event.newValue === 'dark' ? 'dark' : 'light';
        if (event.key === 'token' || event.key === null) setSession(localStorage.getItem('token'));
    });
    if (channel)
        channel.onmessage = event => {
            if (event.data?.type === 'updated') invalidateData(false);
        };
}

export function liveData(definitions, enabled = () => true) {
    const bindings = new WeakMap();
    return {
        data() {
            return { liveResources: {} };
        },
        computed: {
            ...Object.fromEntries(
                Object.entries(definitions).map(([name, definition]) => [
                    name,
                    function () {
                        const config = typeof definition === 'string' ? { url: definition } : definition;
                        const data = this.liveResources[name]
                            ? this.liveResources[name].data
                            : 'initial' in config
                              ? config.initial
                              : [];
                        return config.select ? config.select(data) : data;
                    }
                ])
            ),
            dataLoading() {
                return Object.values(this.liveResources).some(state => !state.loaded && state.pending);
            },
            dataError() {
                return Object.values(this.liveResources).find(state => state.error)?.error || '';
            }
        },
        created() {
            const current = new Map();
            bindings.set(this, current);
            this.$watch(
                () => {
                    if (!session.token || !enabled.call(this)) return [];
                    return Object.entries(definitions).map(([name, definition]) => {
                        const config = typeof definition === 'string' ? { url: definition } : definition;
                        return [name, resource({ ...config, params: config.params?.call(this) })];
                    });
                },
                next => {
                    for (const entry of current.values()) entry.users--;
                    current.clear();
                    this.liveResources = {};
                    for (const [name, entry] of next) {
                        current.set(name, entry);
                        entry.users++;
                        this.liveResources[name] = entry.state;
                        if (!entry.state.loaded || entry.dirty || Date.now() - entry.checked >= entry.interval)
                            fetchResource(entry);
                    }
                    for (const [key, entry] of resources) {
                        if (!entry.users && resources.size > 40) {
                            entry.controller?.abort();
                            resources.delete(key);
                        }
                    }
                },
                { immediate: true }
            );
        },
        beforeUnmount() {
            for (const entry of bindings.get(this).values()) entry.users--;
            bindings.delete(this);
        },
        methods: {
            async refresh() {
                await Promise.all([...bindings.get(this).values()].map(fetchResource));
            }
        }
    };
}
