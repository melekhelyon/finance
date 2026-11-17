<template>
    <div class="date-range" role="group" aria-label="Период">
        <label class="filter-field">
            <span>С</span>
            <input class="brut-input-inline" type="date" :value="from" @input="updateFrom($event.target.value)" />
        </label>
        <label class="filter-field">
            <span>ПО</span>
            <input
                class="brut-input-inline"
                type="date"
                :value="to"
                :min="from"
                @input="updateTo($event.target.value)"
            />
        </label>
    </div>
</template>

<script>
export default {
    props: { from: String, to: String },
    emits: ['update:from', 'update:to'],
    methods: {
        updateFrom(value) {
            this.$emit('update:from', value);
            if (value && this.to && value > this.to) this.$emit('update:to', value);
        },
        updateTo(value) {
            this.$emit('update:to', value);
            if (value && this.from && value < this.from) this.$emit('update:from', value);
        }
    }
};
</script>
