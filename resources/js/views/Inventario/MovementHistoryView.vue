<script setup>
import { onMounted, ref } from 'vue';
import api from '../../services/api';

const props = defineProps({ initialProduct: { type: [Number, String], default: '' } });
const filters = ref({ producto: props.initialProduct, fecha_desde: '', fecha_hasta: '', tipo: '' });
const appliedFilters = ref({ ...filters.value });
const products = ref([]);
const search = ref('');
const productTotal = ref(0);
const searching = ref(false);
const rows = ref([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const error = ref('');
const types = { APERTURA: 'Apertura', RECEPCION: 'Entrada por recepción', VENTA: 'Salida por venta' };
const quantity = value => Number(value || 0).toLocaleString('es-SV', { maximumFractionDigits: 2 });
const formatDate = value => value ? new Intl.DateTimeFormat('es-SV', { dateStyle: 'short', timeStyle: 'short', timeZone: 'America/El_Salvador' }).format(new Date(value)) : 'No registrada';

// También busca productos desactivados para que se pueda revisar su historia.
async function loadProducts() {
  if (searching.value) return;
  searching.value = true;
  try {
    const { data } = await api.get('/api/productos', { params: { search: search.value.trim(), per_page: 100 } });
    const options = data.data;
    if (filters.value.producto && !options.some(item => String(item.id_producto) === String(filters.value.producto))) {
      const selected = await api.get(`/api/productos/${filters.value.producto}`);
      options.unshift(selected.data);
    }
    products.value = options;
    productTotal.value = data.total;
  } catch {
    error.value = 'No fue posible cargar los productos. Intenta buscarlos nuevamente.';
  } finally {
    searching.value = false;
  }
}

// Al cambiar de página conserva los filtros de la última consulta.
async function load(targetPage = 1) {
  if (loading.value) return;
  loading.value = true;
  error.value = '';
  rows.value = [];
  try {
    const { data } = await api.get('/api/movimientos', { params: { ...appliedFilters.value, page: targetPage, per_page: 25 } });
    rows.value = data.data;
    page.value = data.current_page;
    lastPage.value = data.last_page;
    total.value = data.total;
  } catch (exception) {
    total.value = 0;
    lastPage.value = 1;
    error.value = Object.values(exception.response?.data?.errors || {}).flat()[0]
      || exception.response?.data?.message || 'No fue posible consultar los movimientos.';
  } finally {
    loading.value = false;
  }
}

function applyFilters() {
  if (loading.value) return;
  if (filters.value.fecha_desde && filters.value.fecha_hasta && filters.value.fecha_desde > filters.value.fecha_hasta) {
    error.value = 'La fecha inicial no puede ser posterior a la final.';
    return;
  }
  appliedFilters.value = { ...filters.value };
  load(1);
}

function clearFilters() {
  filters.value = { producto: '', fecha_desde: '', fecha_hasta: '', tipo: '' };
  applyFilters();
}

onMounted(async () => {
  await load();
  await loadProducts();
});
</script>

<template>
  <section class="feature-page">
    <div class="page-heading"><div><h1>Historial de movimientos de inventario</h1><p>Kardex de aperturas, entradas y salidas · Solo lectura</p></div></div>
    <form class="search-toolbar movement-filters" @submit.prevent="applyFilters">
      <div class="product-filter">
        <label>Buscar producto<input v-model="search" type="search" placeholder="Código o nombre" @keydown.enter.prevent="loadProducts"></label>
        <button type="button" class="table-action" :disabled="searching" @click="loadProducts">{{ searching ? 'Buscando...' : 'Buscar producto' }}</button>
        <label>Producto<select v-model="filters.producto"><option value="">Todos los productos</option><option v-for="product in products" :key="product.id_producto" :value="product.id_producto">{{ product.codigo }} - {{ product.nombre }}{{ product.activo ? '' : ' (inactivo)' }}</option></select></label>
        <small v-if="productTotal > 100" class="muted">Hay más de 100 coincidencias. Busca por código o nombre para encontrar el producto.</small>
      </div>
      <label>Fecha inicial<input v-model="filters.fecha_desde" type="date"></label>
      <label>Fecha final<input v-model="filters.fecha_hasta" type="date"></label>
      <label>Tipo<select v-model="filters.tipo"><option value="">Todos los movimientos</option><option v-for="(label, type) in types" :key="type" :value="type">{{ label }}</option></select></label>
      <div class="filter-actions"><button type="submit" class="button button-primary" :disabled="loading">Consultar</button><button type="button" class="button button-light" :disabled="loading" @click="clearFilters">Limpiar</button></div>
    </form>
    <p v-if="error" class="feature-alert" role="alert">{{ error }}</p>
    <div v-else-if="loading" class="search-results feature-empty"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i><p>Consultando movimientos...</p></div>
    <div v-else-if="!rows.length" class="search-results feature-empty"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><p>No hay movimientos que coincidan con los filtros.</p></div>
    <div v-else class="search-results feature-table-wrap">
      <table class="feature-table">
        <caption class="sr-only">Movimientos del más antiguo al más reciente, con los saldos registrados en cada operación</caption>
        <thead><tr><th>Fecha</th><th>Código</th><th>Producto</th><th>Tipo</th><th class="align-right">Cantidad</th><th class="align-right">Saldo anterior</th><th class="align-right">Saldo resultante</th><th>Referencia</th></tr></thead>
        <tbody><tr v-for="row in rows" :key="row.id_movimiento"><td>{{ formatDate(row.fecha) }}</td><td class="product-code">{{ row.codigo }}</td><td class="product-name">{{ row.producto }}</td><td><span class="stock-badge" :class="{ 'stock-badge-low': row.tipo === 'VENTA' }">{{ types[row.tipo] || row.tipo }}</span></td><td class="align-right">{{ row.tipo === 'VENTA' ? '−' : '+' }}{{ quantity(row.cantidad) }} {{ row.unidad_medida }}</td><td class="align-right">{{ quantity(row.saldo_anterior) }}</td><td class="align-right">{{ quantity(row.saldo_resultante) }}</td><td>{{ row.referencia || 'Sin referencia' }}</td></tr></tbody>
      </table>
    </div>
    <div v-if="!error && total" class="pagination">
      <span>{{ total }} movimientos · Página {{ page }} de {{ lastPage }}</span>
      <button type="button" class="table-action" :disabled="loading || page <= 1" @click="load(page - 1)">Anterior</button>
      <button type="button" class="table-action" :disabled="loading || page >= lastPage" @click="load(page + 1)">Siguiente</button>
    </div>
  </section>
</template>

<style scoped>
.movement-filters { flex-wrap: wrap; align-items: end; }
.movement-filters > label { flex: 1; min-width: 140px; }
.movement-filters label { font-size: 11px; }
.movement-filters select { width: 100%; }
.product-filter { flex: 2; min-width: 230px; display: grid; gap: 8px; }
.product-filter .table-action { justify-self: start; }
.filter-actions, .pagination { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
.pagination { justify-content: flex-end; font-size: 12px; color: #64748b; }
.filter-actions button:disabled { opacity: .6; cursor: not-allowed; }
.feature-table { min-width: 1000px; }
</style>
