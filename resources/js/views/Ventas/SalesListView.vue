<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../services/api';

const sales = ref([]);
const loading = ref(false);
const error = ref('');
const selectedSale = ref(null);
const detailLoading = ref(false);
const props = defineProps({ user: Object });
const filters = ref({ fecha: '', numero: '' });

const isSeller = computed(() => props.user?.rol === 'VENDEDOR');

const loadSales = async () => {
  loading.value = true;
  error.value = '';

  try {
    const response = await api.get('/api/ventas', {
      params: {
        fecha: filters.value.fecha || undefined,
        numero: filters.value.numero.trim() || undefined,
        per_page: 100,
      },
    });
    sales.value = response.data.data ?? response.data;
  } catch (exception) {
    error.value = exception.response?.data?.message || 'No fue posible cargar las ventas.';
  } finally {
    loading.value = false;
  }
};

const formatDate = (date) => new Intl.DateTimeFormat('es-SV', {
  dateStyle: 'short',
  timeStyle: 'short',
}).format(new Date(date));

const formatMoney = (value) => `$${Number(value || 0).toFixed(2)}`;

const viewSale = async (id) => {
  detailLoading.value = true;
  try {
    selectedSale.value = (await api.get(`/api/ventas/${id}`)).data;
  } catch (exception) {
    error.value = exception.response?.data?.message || 'No fue posible cargar el detalle.';
  } finally {
    detailLoading.value = false;
  }
};

onMounted(loadSales);
</script>

<template>
  <section class="feature-page">
    <div class="page-heading">
      <div>
        <h1>Consultar Ventas Anteriores</h1>
        <p>Histórico de transacciones de venta de la ferretería (Sólo lectura)</p>
      </div>
    </div>
    <div class="search-toolbar sales-filters">
      <label>RANGO DE FECHAS<input v-model="filters.fecha" type="date"></label>
      <label>VENDEDOR
        <input v-if="isSeller" :value="props.user?.name || 'Vendedor'" disabled>
        <select v-else disabled>
          <option>Todos los vendedores</option>
        </select>
      </label>
      <label>N° VENTA / CORRELATIVO<input v-model="filters.numero" placeholder="Ej: VTA-00001"></label>
      <button type="button" @click="loadSales" :disabled="loading">{{ loading ? 'Filtrando...' : 'Filtrar' }}</button>
    </div>
    <p v-if="error" class="feature-alert">{{ error }}</p>
    <div v-else class="search-results feature-table-wrap">
      <table class="feature-table">
        <thead>
          <tr>
            <th>NÚMERO VENTA</th>
            <th>FECHA Y HORA</th>
            <th>VENDEDOR</th>
            <th class="align-right">MONTO TOTAL</th>
            <th>DETALLE</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sale in sales" :key="sale.id_venta">
            <td class="product-code">{{ sale.numero }}</td>
            <td>{{ formatDate(sale.fecha) }}</td>
            <td>{{ sale.usuario?.name || '—' }}</td>
            <td class="align-right product-price">{{ formatMoney(sale.total) }}</td>
            <td><button class="table-action" type="button" :disabled="detailLoading"
                @click="viewSale(sale.id_venta)">Ver</button></td>
          </tr>
          <tr v-if="!sales.length">
            <td colspan="5" class="empty-state">No hay ventas registradas.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="selectedSale" class="sale-detail-modal" role="dialog" aria-modal="true">
      <div class="sale-detail-box">
        <header class="sale-detail-header">
          <h2>DETALLE DE VENTA: {{ selectedSale.numero }}</h2>
          <button type="button" class="modal-close" aria-label="Cerrar" @click="selectedSale = null">&times;</button>
        </header>
        <div class="sale-detail-content">
          <div class="sale-detail-summary">
            <div><strong>Fecha:</strong> {{ formatDate(selectedSale.fecha) }}</div>
            <div><strong>Vendedor:</strong> {{ selectedSale.usuario?.name || '—' }}</div>
            <span class="sale-status"><i class="fa-solid fa-circle-check"></i> REGISTRO CONFIRMADO (SOLO LECTURA)</span>
          </div>
          <div class="sale-items-table">
            <div class="sale-items-heading">
              <strong>Producto</strong><strong>Cant</strong><strong>Precio</strong><strong>Subtotal</strong></div>
            <div v-for="detail in selectedSale.detalles" :key="detail.id_detalle_venta" class="sale-detail-row">
              <span>{{ detail.producto?.nombre || 'Producto' }}</span>
              <span>{{ detail.cantidad }}</span>
              <span>{{ formatMoney(detail.precio_unitario) }}</span>
              <strong>{{ formatMoney(detail.subtotal) }}</strong>
            </div>
          </div>
          <div class="sale-detail-total"><span>Total:</span><strong>{{ formatMoney(selectedSale.total) }}</strong></div>
          <button type="button" class="modal-close-action" @click="selectedSale = null">Cerrar</button>
        </div>
      </div>
    </div>
  </section>
</template>
