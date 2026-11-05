<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../services/api';

const props = defineProps({
  role: String,
  canManageCatalog: Boolean,
  canSell: Boolean,
});
const emit = defineEmits(['navigate']);
const summary = ref({
  sales_today: { total: 0, count: 0 },
  out_of_stock: 0,
  critical_stock: 0,
  latest_movements: 0,
});
const loading = ref(true);
const error = ref('');

const navigate = (screen) => {
  emit('navigate', screen);
};

const today = computed(() => new Intl.DateTimeFormat('es-SV', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
}).format(new Date()));

const formatMoney = (value) => `$${Number(value || 0).toFixed(2)}`;

const loadSummary = async () => {
  try {
    summary.value = (await api.get('/api/dashboard/summary')).data;
  } catch (exception) {
    error.value = exception.response?.data?.message || 'No fue posible cargar el resumen.';
  } finally {
    loading.value = false;
  }
};

onMounted(loadSummary);
</script>

<template>
  <section class="dashboard-view">
    <div class="page-title-row">
      <div>
        <h1>Panel de Control</h1>
        <p>Resumen operativo para el rol de <strong>{{ role }}</strong></p>
      </div>
      <span class="dashboard-date"><i class="fa-regular fa-calendar"></i>{{ today }}</span>
    </div>

    <button type="button" class="stock-alert-card" @click="navigate('stocks')">
      <span class="alert-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
      <div><strong>Alertas de reabastecimiento</strong>
        <p>{{ summary.critical_stock }} productos requieren revisión de existencias.</p>
      </div>
      <span class="alert-action">Ver Alertas de Stock</span>
    </button>
    <p v-if="error" class="feature-alert">{{ error }}</p>

    <div class="dashboard-stats">
      <article class="dashboard-stat">
        <div><span>VENTAS DEL DÍA</span><strong>{{ loading ? '—' : formatMoney(summary.sales_today.total)
            }}</strong><small>{{ summary.sales_today.count }} transacciones</small></div><i
          class="fa-solid fa-cash-register stat-icon blue"></i>
      </article>
      <article class="dashboard-stat">
        <div><span>PRODUCTOS AGOTADOS</span><strong>{{ loading ? '—' : summary.out_of_stock }}</strong><small>Requieren
            atención</small></div><i class="fa-solid fa-box stat-icon red"></i>
      </article>
      <article class="dashboard-stat">
        <div><span>ÚLTIMOS MOVIMIENTOS</span><strong>{{ loading ? '—' : summary.latest_movements
            }}</strong><small>Registrados hoy</small></div><i
          class="fa-solid fa-arrow-right-arrow-left stat-icon green"></i>
      </article>
    </div>

    <article class="quick-panel">
      <h2>ACCESOS RÁPIDOS</h2>
      <div class="dashboard-actions">
        <button v-if="props.canSell" type="button" class="dashboard-action" @click="navigate('pos')">
          <i class="fa-solid fa-cart-shopping orange"></i><strong>Registrar Venta</strong><span>Facturación a
            cliente</span>
        </button>
        <button type="button" class="dashboard-action" @click="navigate('product_search')">
          <i class="fa-solid fa-magnifying-glass navy"></i><strong>Buscar Producto</strong><span>Consultar
            catálogo</span>
        </button>
        <button v-if="props.canManageCatalog" type="button" class="dashboard-action"
          @click="navigate('inventory_entry')">
          <i class="fa-solid fa-file-circle-plus green-text"></i><strong>Registrar Entrada</strong><span>Carga de
            mercadería</span>
        </button>
        <button v-if="props.canManageCatalog" type="button" class="dashboard-action" @click="navigate('alerts')">
          <i class="fa-solid fa-triangle-exclamation amber"></i><strong>Alertas Mínimas</strong><span>Inventario
            crítico</span>
        </button>
      </div>
    </article>
  </section>
</template>
