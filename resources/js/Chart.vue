<template>
    <apexchart v-if="hasData" :key="type" :type="type" :height="height" :options="options" :series="series" />
    <div v-else class="empty-state">НЕТ ДАННЫХ ДЛЯ ДИАГРАММЫ</div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import ru from 'apexcharts/dist/locales/ru.json';
import { appearance } from './live';

export default {
    components: { apexchart: VueApexCharts },
    props: {
        type: { default: 'bar' },
        height: { default: 230 },
        series: { default: () => [] },
        labels: { default: () => [] },
        horizontal: Boolean
    },
    computed: {
        hasData() {
            return (
                this.series.length &&
                (['donut', 'pie'].includes(this.type)
                    ? this.series.some(value => value > 0)
                    : this.series.some(item => item.data?.length))
            );
        },
        options() {
            const dark = appearance.theme === 'dark';
            const ink = dark ? '#D9E7E8' : '#25252B';
            return {
                chart: {
                    locales: [ru],
                    defaultLocale: 'ru',
                    background: 'transparent',
                    foreColor: ink,
                    toolbar: { show: false },
                    fontFamily: 'JetBrains Mono, monospace',
                    animations: {
                        enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
                        speed: 220,
                        animateGradually: { enabled: false },
                        dynamicAnimation: { enabled: true, speed: 220 }
                    }
                },
                colors: [dark ? '#245BFF' : '#528A9A', ink, '#8DB4BE', '#77777E'],
                theme: { mode: dark ? 'dark' : 'light' },
                tooltip: {
                    theme: dark ? 'dark' : 'light',
                    fillSeriesColor: false,
                    y: {
                        formatter: value => new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 }).format(value)
                    }
                },
                labels: this.labels,
                xaxis: { categories: this.labels },
                grid: { borderColor: ink, strokeDashArray: 4 },
                stroke: {
                    curve: 'straight',
                    width: this.type === 'line' ? 3 : 2,
                    colors: this.type === 'line' ? undefined : [ink]
                },
                plotOptions: {
                    bar: { horizontal: this.horizontal, borderRadius: 0, columnWidth: '55%' },
                    pie: { donut: { size: '65%' } }
                },
                dataLabels: {
                    enabled: ['donut', 'pie'].includes(this.type),
                    style: { colors: ['#FFFFFF'] },
                    dropShadow: { enabled: true, color: '#101A46', blur: 2, opacity: 1 }
                },
                legend: { position: 'bottom', labels: { colors: ink } },
                noData: { text: 'Нет данных' }
            };
        }
    }
};
</script>
