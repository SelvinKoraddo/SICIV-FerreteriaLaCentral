<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../services/api';

const today = new Date().toLocaleDateString('sv-SE', { timeZone: 'America/El_Salvador' });
const filters = ref({ fecha_desde: `${today.slice(0, 7)}-01`, fecha_hasta: today, categoria: '', limite: 10 });
const categories = ref([]);
const rows = ref([]);
const loading = ref(false);
const error = ref('');
const quantity = value => Number(value || 0).toLocaleString('es-SV', { maximumFractionDigits: 2 });
const money = value => `$${Number(value || 0).toFixed(2)}`;
const maximum = computed(() => Math.max(...rows.value.map(row => Number(row.cantidad_vendida)), 1));

// La tabla y las barras usan los mismos resultados para mostrar las mismas cantidades.
async function load() {
  if (loading.value) return;
  error.value = '';
  if (filters.value.fecha_desde && filters.value.fecha_hasta && filters.value.fecha_desde > filters.value.fecha_hasta) {
    error.value = 'La fecha inicial no puede ser posterior a la final.';
    return;
  }
  loading.value = true;
  rows.value = [];
  try {
    const { data } = await api.get('/api/reportes/mayor-movimiento', { params: filters.value });
    rows.value = data.data;
  } catch (exception) {
    error.value = Object.values(exception.response?.data?.errors || {}).flat()[0]
      || exception.response?.data?.message || 'No fue posible consultar los productos más vendidos.';
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  try {
    const response = await api.get('/api/categorias');
    categories.value = response.data;
    await load();
  } catch {
    error.value = 'No fue posible cargar las categorías. Vuelve a abrir el reporte.';
  }
});
</script>

<template>
  <section class="feature-page">
    <div class="page-heading">
      <div><h1>Productos con mayor movimiento</h1><p>Productos más vendidos en el período seleccionado</p></div>
    </div>
    <form class="search-toolbar report-filters" @submit.prevent="load">
      <label>Fecha inicial<input v-model="filters.fecha_desde" type="date"></label>
      <label>Fecha final<input v-model="filters.fecha_hasta" type="date"></label>
      <label>Categoría<select v-model="filters.categoria"><option value="">Todas las categorías</option><option v-for="category in categories" :key="category.id_categoria" :value="category.id_categoria">{{ category.nombre }}</option></select></label>
      <label>Límite de productos<input v-model.number="filters.limite" type="number" min="1" max="100" required></label>
      <button type="submit" class="button button-primary" :disabled="loading">{{ loading ? 'Consultando...' : 'Consultar' }}</button>
    </form>
    <p v-if="error" class="feature-alert" role="alert">{{ error }}</p>
    <div v-else-if="loading" class="search-results feature-empty"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i><p>Consultando ventas...</p></div>
    <div v-else-if="!rows.length" class="search-results feature-empty"><i class="fa-solid fa-chart-bar" aria-hidden="true"></i><p>No hay ventas que coincidan con los filtros.</p></div>
    <template v-else>
      <div class="search-results feature-table-wrap">
        <table class="feature-table">
          <caption class="sr-only">Productos ordenados por cantidad vendida, de mayor a menor</caption>
          <thead><tr><th>Código</th><th>Producto</th><th>Categoría</th><th>Unidad</th><th class="align-right">Cantidad vendida</th><th class="align-right">Monto vendido</th><th class="align-right">Existencia actual</th></tr></thead>
          <tbody><tr v-for="row in rows" :key="row.id_producto"><td class="product-code">{{ row.codigo }}</td><td class="product-name">{{ row.nombre }}</td><td>{{ row.categoria }}</td><td>{{ row.unidad_medida }}</td><td class="align-right">{{ quantity(row.cantidad_vendida) }}</td><td class="align-right">{{ money(row.monto_vendido) }}</td><td class="align-right">{{ quantity(row.existencia_actual) }}</td></tr></tbody>
        </table>
      </div>
      <div class="search-results report-chart">
        <h2>Cantidad vendida por producto</h2>
        <p class="muted">Cada barra muestra la cantidad en la unidad indicada para el producto.</p>
        <div v-for="row in rows" :key="row.id_producto" class="bar-row">
          <span class="bar-label">{{ row.codigo }} · {{ row.nombre }}</span>
          <div class="bar-track"><div class="bar-fill" :style="{ width: `${Number(row.cantidad_vendida) / maximum * 100}%` }"></div></div>
          <strong>{{ quantity(row.cantidad_vendida) }} {{ row.unidad_medida }}</strong>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.report-filters { flex-wrap: wrap; align-items: end; }
.report-filters label { flex: 1; min-width: 140px; font-size: 11px; }
.report-filters select { width: 100%; }
.report-filters button:disabled { opacity: .6; cursor: not-allowed; }
.report-chart { min-height: 0; padding: 22px; }
.report-chart h2 { font-size: 14px; color: #203d62; }
.report-chart p { font-size: 12px; }
.bar-row { display: grid; grid-template-columns: minmax(130px, 1fr) minmax(80px, 2fr) minmax(90px, 1fr); align-items: center; gap: 14px; margin-top: 16px; font-size: 12px; }
.bar-label { overflow-wrap: anywhere; }
.bar-row strong { text-align: right; }
.bar-track { height: 18px; border-radius: 5px; background: #eef2f7; overflow: hidden; }
.bar-fill { height: 100%; background: #f28c28; border-radius: 5px; }
@media (max-width: 600px) { .bar-row { grid-template-columns: 1fr; gap: 6px; } .bar-row strong { text-align: left; } }
</style>
