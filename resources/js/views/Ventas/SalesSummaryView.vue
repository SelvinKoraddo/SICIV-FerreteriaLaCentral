<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../services/api';

const sales = ref([]);
const loading = ref(false);
const error = ref('');

const loadSales = async () => {
  loading.value = true;
  try {
    const response = await api.get('/api/ventas', { params: { per_page: 100 } });
    sales.value = response.data.data ?? response.data;
  } catch (exception) {
    error.value = exception.response?.data?.message || 'No fue posible cargar el resumen.';
  } finally {
    loading.value = false;
  }
};

const total = computed(() => sales.value.reduce((sum, sale) => sum + Number(sale.total || 0), 0));
const average = computed(() => sales.value.length ? total.value / sales.value.length : 0);
const formatMoney = (value) => `$${Number(value || 0).toFixed(2)}`;

const days = computed(() => {
  const grouped = new Map();
  sales.value.forEach((sale) => {
    const date = new Date(sale.fecha);
    const key = date.toLocaleDateString('es-SV', { day: '2-digit', month: 'short' });
    grouped.set(key, (grouped.get(key) || 0) + Number(sale.total || 0));
  });
  return [...grouped.entries()].slice(-7);
});

const chart = computed(() => {
  const width = 900;
  const height = 260;
  const left = 48;
  const right = 12;
  const top = 18;
  const bottom = 42;
  const values = days.value.map(([, amount]) => amount);
  const max = Math.max(...values, 0);
  const chartHeight = height - top - bottom;
  const chartWidth = width - left - right;
  const points = days.value.map(([day, amount], index) => {
    const x = days.value.length === 1
      ? left + chartWidth / 2
      : left + (index * chartWidth) / (days.value.length - 1);
    const y = top + chartHeight - (max ? (amount / max) * chartHeight : 0);
    return { day, amount, x, y };
  });
  const line = points.map((point) => `${point.x},${point.y}`).join(' ');
  const area = points.length
    ? `${left},${top + chartHeight} ${line} ${points.at(-1).x},${top + chartHeight}`
    : '';

  return { width, height, left, right, top, bottom, chartHeight, points, line, area, max };
});

onMounted(loadSales);
</script>

<template>
  <section class="feature-page">
    <div class="page-heading">
      <div>
        <h1>Resumen de Ventas por Período</h1>
        <p>Consolidado diario y rendimiento por vendedor</p>
      </div>
      <button class="table-action refresh-action" type="button" :disabled="loading" @click="loadSales">
        {{ loading ? 'Cargando...' : 'Actualizar' }}
      </button>
    </div>
    <p v-if="error" class="feature-alert">{{ error }}</p>
    <template v-else>
      <div class="dashboard-stats summary-stats">
        <article class="dashboard-stat">
          <div><span>VENTAS REGISTRADAS</span><strong>{{ sales.length }}</strong><small>Transacciones cargadas</small>
          </div><i class="fa-solid fa-receipt stat-icon blue"></i>
        </article>
        <article class="dashboard-stat">
          <div><span>TOTAL VENDIDO</span><strong>{{ formatMoney(total) }}</strong><small>Acumulado disponible</small>
          </div><i class="fa-solid fa-sack-dollar stat-icon green"></i>
        </article>
        <article class="dashboard-stat">
          <div><span>PROMEDIO POR VENTA</span><strong>{{ formatMoney(average) }}</strong><small>Promedio de
              transacciones</small></div><i class="fa-solid fa-chart-line stat-icon orange"></i>
        </article>
      </div>
      <div class="search-results summary-panel">
        <h2>COMPORTAMIENTO DIARIO DE VENTAS</h2>
        <div v-if="!days.length" class="feature-empty"><i class="fa-solid fa-chart-line"></i>
          <p>No hay ventas registradas para mostrar.</p>
        </div>
        <div v-else class="summary-chart">
          <svg :viewBox="`0 0 ${chart.width} ${chart.height}`" class="summary-chart-svg" role="img"
            aria-label="Comportamiento diario de ventas">
            <g class="chart-grid">
              <line v-for="step in 5" :key="step" :x1="chart.left" :x2="chart.width - chart.right"
                :y1="chart.top + ((step - 1) * chart.chartHeight) / 4"
                :y2="chart.top + ((step - 1) * chart.chartHeight) / 4" />
              <line v-for="point in chart.points" :key="`vertical-${point.day}`" :x1="point.x" :x2="point.x"
                :y1="chart.top" :y2="chart.top + chart.chartHeight" />
            </g>
            <polygon :points="chart.area" class="chart-area" />
            <polyline :points="chart.line" class="chart-line" />
            <g v-for="point in chart.points" :key="point.day">
              <circle :cx="point.x" :cy="point.y" r="3.5" class="chart-point" />
              <text :x="point.x" :y="point.y - 10" text-anchor="middle" class="chart-value">{{ formatMoney(point.amount)
                }}</text>
              <text :x="point.x" :y="chart.height - 14" text-anchor="middle" class="chart-label">{{ point.day }}</text>
            </g>
          </svg>
        </div>
      </div>
    </template>
  </section>
</template>
