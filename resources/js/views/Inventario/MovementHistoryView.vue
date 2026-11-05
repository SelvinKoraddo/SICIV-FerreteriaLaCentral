<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../../services/api';

const rows = ref([]);
const products = ref([]);
const loading = ref(false);
const error = ref('');
const filters = reactive({ producto: '', tipo: '', desde: '', hasta: '' });

const load = async () => {
  loading.value = true;
  try {
    const { data } = await api.get('/api/reportes/movimientos', { params: { ...filters, per_page: 500 } });
    rows.value = data.data || [];
  } catch (exception) {
    error.value = exception.response?.data?.message || 'No fue posible cargar el historial.';
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  try { products.value = (await api.get('/api/productos', { params: { activos: true, per_page: 500 } })).data.data || []; } catch (exception) {
    error.value = exception.response?.data?.message || 'No fue posible cargar el catálogo de productos.';
  }
  await load();
});
</script>

<template>
  <section class="feature-page">
    <div class="page-heading"><div><h1>Historial de movimientos</h1><p>Kardex cronológico de entradas, ventas y aperturas</p></div></div>
    <div class="search-toolbar movement-filters">
      <select v-model="filters.producto" aria-label="Producto"><option value="">Todos los productos</option><option v-for="product in products" :key="product.id_producto" :value="product.id_producto">{{ product.codigo }} - {{ product.nombre }}</option></select>
      <select v-model="filters.tipo" aria-label="Tipo"><option value="">Todos los tipos</option><option value="RECEPCION">Recepción</option><option value="VENTA">Venta</option><option value="APERTURA">Apertura</option></select>
      <input v-model="filters.desde" type="date" aria-label="Desde"><input v-model="filters.hasta" type="date" aria-label="Hasta">
      <button class="button button-primary" type="button" :disabled="loading" @click="load">Filtrar</button>
    </div>
    <p v-if="error" class="feature-alert">{{ error }}</p>
    <div class="search-results feature-table-wrap"><table class="feature-table">
      <thead><tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th>Referencia</th><th class="align-right">Cantidad</th><th class="align-right">Saldo anterior</th><th class="align-right">Saldo resultante</th></tr></thead>
      <tbody><tr v-for="row in rows" :key="row.id_movimiento"><td>{{ new Date(row.fecha).toLocaleString('es-MX') }}</td><td>{{ row.producto?.codigo }} - {{ row.producto?.nombre }}</td><td>{{ row.tipo }}</td><td>{{ row.referencia || '—' }}</td><td class="align-right">{{ row.cantidad }}</td><td class="align-right">{{ row.saldo_anterior }}</td><td class="align-right">{{ row.saldo_resultante }}</td></tr><tr v-if="!loading && !rows.length"><td colspan="7" class="feature-empty">No hay movimientos para los filtros seleccionados.</td></tr></tbody>
    </table></div>
  </section>
</template>
