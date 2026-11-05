<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../../services/api';

const rows = ref([]);
const categories = ref([]);
const error = ref('');
const filters = reactive({ desde: '', hasta: '', categoria: '', limite: 10 });
const max = computed(() => Math.max(...rows.value.map((row) => Number(row.unidades_vendidas)), 1));

const load = async () => {
  try {
    rows.value = await api.get('/api/reportes/productos-mayor-movimiento', { params: filters }).then(({ data }) => data);
  } catch (exception) { error.value = exception.response?.data?.message || 'No fue posible cargar el reporte.'; }
};

onMounted(async () => {
  try { categories.value = await api.get('/api/categorias').then(({ data }) => data); } catch (exception) {
    error.value = exception.response?.data?.message || 'No fue posible cargar las categorías.';
  }
  await load();
});
</script>

<template>
  <section class="feature-page">
    <div class="page-heading"><div><h1>Productos con mayor movimiento</h1><p>Ventas ordenadas por unidades y monto recaudado</p></div></div>
    <div class="search-toolbar"><input v-model="filters.desde" type="date" aria-label="Desde"><input v-model="filters.hasta" type="date" aria-label="Hasta"><select v-model="filters.categoria"><option value="">Todas las categorías</option><option v-for="category in categories" :key="category.id_categoria" :value="category.id_categoria">{{ category.nombre }}</option></select><input v-model.number="filters.limite" type="number" min="1" max="50" aria-label="Límite"><button class="button button-primary" type="button" @click="load">Consultar</button></div>
    <p v-if="error" class="feature-alert">{{ error }}</p>
    <div class="search-results feature-table-wrap"><table class="feature-table"><thead><tr><th>#</th><th>Producto</th><th>Unidad</th><th class="align-right">Unidades vendidas</th><th class="align-right">Monto recaudado</th><th>Movimiento</th></tr></thead><tbody><tr v-for="(row, index) in rows" :key="row.id_producto"><td>{{ index + 1 }}</td><td class="product-name">{{ row.codigo }} - {{ row.nombre }}</td><td>{{ row.unidad_medida }}</td><td class="align-right">{{ row.unidades_vendidas }}</td><td class="align-right">${{ Number(row.monto_recaudado).toFixed(2) }}</td><td class="movement-bar"><span :style="{ width: `${Number(row.unidades_vendidas) / max * 100}%` }"></span></td></tr><tr v-if="!rows.length"><td colspan="6" class="feature-empty">No hay ventas para el período seleccionado.</td></tr></tbody></table></div>
  </section>
</template>

<style scoped>.movement-bar{width:180px}.movement-bar span{display:block;height:10px;border-radius:99px;background:#f28c28}</style>
