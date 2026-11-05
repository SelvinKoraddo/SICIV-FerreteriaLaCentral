<script setup>
/**
 * Alertas de existencia baja y agotados.
 *
 * Lista los productos activos agotados o con existencia igual o inferior a su
 * mínimo. Los agotados aparecen primero y con un estilo diferenciado (CA-2).
 * Las alertas se calculan en el servidor a partir del saldo vigente, por lo que
 * reflejan de inmediato las ventas (CA-1) y las entradas (CA-4).
 *
 * @see HU-08 Alertas de existencia baja y agotados
 * @see CU-08 Consultar alertas de existencia baja
 * @emits counts-changed  Totales actualizados, para sincronizar el contador del menú.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import api from '../../services/api';

const emit = defineEmits(['counts-changed']);

const SEARCH_DEBOUNCE_MS = 350;
const PAGE_SIZE = 200;

/** Niveles que generan alerta (RN-04). */
const LEVELS = Object.freeze({
  agotado: { label: 'Agotados', icon: 'fa-circle-xmark', css: 'level-out' },
  bajo_minimo: { label: 'Bajo mínimo', icon: 'fa-triangle-exclamation', css: 'level-low' },
});

const rows = ref([]);
const total = ref(0);
const counts = ref({ total: 0, agotado: 0, bajo_minimo: 0 });
const categories = ref([]);
const search = ref('');
const category = ref('');
const level = ref('');
const loading = ref(false);
const error = ref('');

let searchTimer = null;
let requestSequence = 0;

const hasFilters = computed(() => Boolean(search.value.trim() || category.value || level.value));
const truncated = computed(() => total.value > rows.value.length);

const formatQuantity = (value) => {
  const number = Number(value ?? 0);
  return Number.isInteger(number) ? String(number) : number.toFixed(2);
};

const filterParams = () => ({
  search: search.value.trim() || undefined,
  categoria: category.value || undefined,
});

const loadCategories = async () => {
  const { data } = await api.get('/api/categorias');
  categories.value = data.filter((item) => Boolean(item.activa));
};

/**
 * Carga el listado y los totales en paralelo. El número de secuencia descarta
 * respuestas de peticiones anteriores que lleguen fuera de orden.
 */
const loadAlerts = async () => {
  const sequence = ++requestSequence;
  loading.value = true;
  error.value = '';

  try {
    const [listResponse, countResponse] = await Promise.all([
      api.get('/api/alertas', { params: { ...filterParams(), nivel: level.value || undefined, per_page: PAGE_SIZE } }),
      api.get('/api/alertas/conteo', { params: filterParams() }),
    ]);

    if (sequence !== requestSequence) return;
    rows.value = listResponse.data.data;
    total.value = listResponse.data.total ?? rows.value.length;
    counts.value = countResponse.data;

    // El contador del menú refleja el total global, no el filtrado.
    if (!filterParams().search && !filterParams().categoria) emit('counts-changed', countResponse.data);
  } catch (exception) {
    if (sequence !== requestSequence) return;
    rows.value = [];
    total.value = 0;
    error.value = exception.response?.data?.message || 'No fue posible cargar las alertas.';
  } finally {
    if (sequence === requestSequence) loading.value = false;
  }
};

/** Las tarjetas funcionan como filtro rápido; volver a pulsar la activa lo quita. */
const toggleLevel = (value) => {
  level.value = level.value === value ? '' : value;
};

const clearFilters = () => {
  search.value = '';
  category.value = '';
  level.value = '';
};

watch([search, category, level], () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(loadAlerts, SEARCH_DEBOUNCE_MS);
});

onMounted(async () => {
  try {
    await Promise.all([loadCategories(), loadAlerts()]);
  } catch (exception) {
    error.value = exception.response?.data?.message || 'No fue posible cargar las alertas.';
  }
});

onBeforeUnmount(() => clearTimeout(searchTimer));
</script>

<template>
  <section class="feature-page">
    <div class="alerts-heading">
      <div>
        <h1>Alertas de existencia</h1>
        <p>Productos activos agotados o con existencia igual o inferior a su mínimo</p>
      </div>
      <button type="button" class="button button-light heading-button" :disabled="loading" @click="loadAlerts">
        <i class="fa-solid fa-rotate" :class="{ 'fa-spin': loading }" aria-hidden="true"></i> Actualizar
      </button>
    </div>

    <div class="level-cards" role="group" aria-label="Alertas por nivel">
      <button type="button" class="level-card level-all" :class="{ active: level === '' }" @click="level = ''">
        <i class="fa-solid fa-bell" aria-hidden="true"></i>
        <strong>{{ counts.total }}</strong>
        <span>Alertas activas</span>
      </button>
      <button v-for="(meta, key) in LEVELS" :key="key" type="button" class="level-card" :class="[meta.css, { active: level === key }]"
        :aria-pressed="level === key" @click="toggleLevel(key)">
        <i class="fa-solid" :class="meta.icon" aria-hidden="true"></i>
        <strong>{{ counts[key] }}</strong>
        <span>{{ meta.label }}</span>
      </button>
    </div>

    <div class="search-toolbar alerts-toolbar">
      <div class="search-input-wrap">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input v-model="search" type="search" placeholder="Buscar por código o nombre..." aria-label="Buscar producto">
      </div>
      <select v-model="category" aria-label="Filtrar por categoría">
        <option value="">Todas las categorías</option>
        <option v-for="item in categories" :key="item.id_categoria" :value="item.id_categoria">{{ item.nombre }}</option>
      </select>
      <button v-if="hasFilters" type="button" class="table-action clear-filters" @click="clearFilters">
        <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i> Limpiar
      </button>
    </div>

    <div class="search-results">
      <p v-if="error" class="feature-alert" role="alert">{{ error }}</p>
      <div v-else-if="loading && !rows.length" class="feature-empty">
        <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
        <p>Consultando alertas...</p>
      </div>
      <div v-else-if="!rows.length" class="feature-empty" :class="{ 'empty-ok': !hasFilters }">
        <i class="fa-solid" :class="hasFilters ? 'fa-filter-circle-xmark' : 'fa-circle-check'" aria-hidden="true"></i>
        <p class="feature-empty-title">
          {{ hasFilters ? 'No hay alertas que coincidan con los filtros' : 'No hay productos con existencia baja' }}
        </p>
        <p v-if="!hasFilters">Todos los productos activos están por encima de su existencia mínima.</p>
      </div>
      <div v-else class="feature-table-wrap">
        <table class="feature-table alerts-table">
          <caption class="sr-only">Productos en alerta de existencia, agotados primero</caption>
          <thead>
            <tr>
              <th scope="col">Código</th>
              <th scope="col">Producto</th>
              <th scope="col">Categoría</th>
              <th scope="col" class="align-right">Existencia</th>
              <th scope="col" class="align-right">Mínimo</th>
              <th scope="col" class="align-right" title="Cantidad a reponer para alcanzar el mínimo">Faltante</th>
              <th scope="col" class="align-center">Alerta</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id_producto" :class="`row-${row.nivel}`">
              <td class="product-code">{{ row.codigo }}</td>
              <td class="product-name">{{ row.nombre }}</td>
              <td>{{ row.categoria || '—' }}</td>
              <td class="align-right quantity">{{ formatQuantity(row.existencia_actual) }} <small>{{ row.unidad_medida }}</small></td>
              <td class="align-right">{{ formatQuantity(row.existencia_minima) }}</td>
              <td class="align-right shortage">{{ row.faltante > 0 ? formatQuantity(row.faltante) : '—' }}</td>
              <td class="align-center">
                <span class="stock-badge" :class="row.nivel === 'agotado' ? 'stock-badge-out' : 'stock-badge-low'">
                  <i class="fa-solid" :class="LEVELS[row.nivel].icon" aria-hidden="true"></i> {{ row.nivel_etiqueta }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="truncated" class="table-footnote">
          Mostrando {{ rows.length }} de {{ total }} alertas. Usa los filtros para acotar la consulta.
        </p>
      </div>
    </div>
  </section>
</template>

<style scoped>
.feature-page { display: flex; flex-direction: column; gap: 20px; }
.alerts-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.alerts-heading h1 { margin: 0; color: #172033; font-size: 24px; }
.alerts-heading p { margin: 4px 0 0; color: #64748b; font-size: 13px; }
.heading-button { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; white-space: nowrap; }
.heading-button:disabled { cursor: not-allowed; opacity: .6; }

.level-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
.level-card { display: grid; justify-items: start; gap: 4px; padding: 16px 18px; border: 1px solid #dce4ee; border-radius: 12px; background: #fff; box-shadow: 0 2px 3px #20324d08; text-align: left; transition: transform .15s ease; }
.level-card:hover { transform: translateY(-1px); }
.level-card i { font-size: 18px; }
.level-card strong { color: inherit; font-size: 26px; line-height: 1.1; }
.level-card span { color: #64748b; font-size: 12px; font-weight: 600; }
.level-card.active { border-color: currentColor; box-shadow: 0 0 0 3px #f28c2822; }
.level-all { color: #203d62; }
.level-out { color: #a42828; }
.level-low { color: #a85a08; }

.alerts-toolbar { flex-wrap: wrap; align-items: center; }
.clear-filters { display: inline-flex; align-items: center; gap: 6px; }

.alerts-table .quantity { color: #172033; font-weight: 700; }
.alerts-table .quantity small { color: #94a3b8; font-weight: 500; }
.shortage { color: #a42828; font-variant-numeric: tabular-nums; font-weight: 700; }
.stock-badge { display: inline-flex; align-items: center; gap: 6px; }
tr.row-agotado td { background: #fff5f5; }
tr.row-agotado .product-name { color: #a42828; }
tr.row-bajo_minimo td { background: #fff9ef; }
tr.row-bajo_minimo .product-name { color: #a85a08; }
.empty-ok i { color: #34d399; }
.table-footnote { margin: 0; padding: 12px 14px; border-top: 1px solid #e2e8f0; color: #718096; font-size: 12px; }
.sr-only { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0 0 0 0); border: 0; white-space: nowrap; }

@media (max-width: 720px) {
  .alerts-heading { flex-direction: column; }
  .level-cards { grid-template-columns: 1fr; }
  .search-toolbar select { width: 100%; }
}
</style>
