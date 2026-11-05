<script setup>
/**
 * Consulta de existencias del inventario.
 *
 * - CA-1: filtra por producto (código o nombre), por categoría o muestra todos;
 *         además permite acotar por nivel de stock.
 * - CA-2: muestra existencia actual, existencia mínima y la diferencia.
 * - CA-3: resalta las filas agotadas y las que están en o bajo el mínimo (RN-04).
 *
 * El servidor aplica los filtros, el orden por criticidad y calcula los totales,
 * por lo que la vista es correcta aunque el catálogo supere una página.
 *
 * @see HU-07 Consulta de existencias
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import api from '../../services/api';

const props = defineProps({
  /** Permite descargar el CSV (solo roles que gestionan el catálogo). */
  canExport: { type: Boolean, default: false },
});

const SEARCH_DEBOUNCE_MS = 350;
const PAGE_SIZE = 200;

/** Niveles de stock (RN-04) con sus textos e iconos para las tarjetas y la tabla. */
const LEVELS = Object.freeze({
  agotado: { label: 'Agotados', badge: 'Agotado', icon: 'fa-circle-xmark', css: 'level-out' },
  bajo_minimo: { label: 'Bajo mínimo', badge: 'Reponer', icon: 'fa-triangle-exclamation', css: 'level-low' },
  normal: { label: 'Normales', badge: 'Normal', icon: 'fa-circle-check', css: 'level-ok' },
});

const rows = ref([]);
const total = ref(0);
const summary = ref({ total: 0, agotado: 0, bajo_minimo: 0, normal: 0, en_alerta: 0, consultado_en: null });
const categories = ref([]);
const search = ref('');
const category = ref('');
const level = ref('');
const loading = ref(false);
const exporting = ref(false);
const error = ref('');

let searchTimer = null;
let requestSequence = 0;

const hasFilters = computed(() => Boolean(search.value.trim() || category.value || level.value));
const truncated = computed(() => total.value > rows.value.length);
const lastUpdate = computed(() => {
  if (!summary.value.consultado_en) return '';
  return new Date(summary.value.consultado_en).toLocaleTimeString('es-SV', { hour: '2-digit', minute: '2-digit' });
});

const formatQuantity = (value) => {
  const number = Number(value ?? 0);
  return Number.isInteger(number) ? String(number) : number.toFixed(2);
};

/** Diferencia con signo explícito para leerla de un vistazo (+12, 0, -3). */
const formatDifference = (value) => {
  const number = Number(value ?? 0);
  const text = formatQuantity(Math.abs(number));
  if (number > 0) return `+${text}`;
  if (number < 0) return `-${text}`;
  return '0';
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
 * Carga el listado y el resumen en paralelo. El número de secuencia descarta
 * respuestas de peticiones anteriores que lleguen fuera de orden.
 */
const loadStock = async () => {
  const sequence = ++requestSequence;
  loading.value = true;
  error.value = '';

  try {
    const [listResponse, summaryResponse] = await Promise.all([
      api.get('/api/existencias', { params: { ...filterParams(), nivel: level.value || undefined, per_page: PAGE_SIZE } }),
      api.get('/api/existencias/resumen', { params: filterParams() }),
    ]);

    if (sequence !== requestSequence) return;
    rows.value = listResponse.data.data;
    total.value = listResponse.data.total ?? rows.value.length;
    summary.value = summaryResponse.data;
  } catch (exception) {
    if (sequence !== requestSequence) return;
    rows.value = [];
    total.value = 0;
    error.value = exception.response?.data?.message || 'No fue posible cargar las existencias.';
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

const fileNameFrom = (header) => {
  const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(header ?? '');
  return match ? decodeURIComponent(match[1]) : 'existencias.csv';
};

/** Reutiliza la exportación del catálogo con los filtros vigentes (solo activos). */
const exportStock = async () => {
  if (exporting.value) return;
  exporting.value = true;

  try {
    const response = await api.get('/api/productos/exportar', {
      params: { ...filterParams(), estado: 'activos', nivel: level.value || undefined },
      responseType: 'blob',
    });
    const url = URL.createObjectURL(response.data);
    const link = Object.assign(document.createElement('a'), {
      href: url,
      download: fileNameFrom(response.headers['content-disposition']),
    });
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  } catch (exception) {
    error.value = exception.response?.status === 403
      ? 'No tienes permisos para exportar las existencias.'
      : 'No fue posible exportar las existencias. Intenta de nuevo.';
  } finally {
    exporting.value = false;
  }
};

watch([search, category, level], () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(loadStock, SEARCH_DEBOUNCE_MS);
});

onMounted(async () => {
  try {
    await Promise.all([loadCategories(), loadStock()]);
  } catch (exception) {
    error.value = exception.response?.data?.message || 'No fue posible cargar la consulta de existencias.';
  }
});

onBeforeUnmount(() => clearTimeout(searchTimer));
</script>

<template>
  <section class="feature-page">
    <div class="stock-heading">
      <div>
        <h1>Existencias</h1>
        <p>Disponibilidad actual del inventario por producto y categoría</p>
      </div>
      <div class="heading-actions">
        <span v-if="lastUpdate" class="last-update" :title="summary.consultado_en">
          <i class="fa-regular fa-clock" aria-hidden="true"></i> Actualizado {{ lastUpdate }}
        </span>
        <button v-if="canExport" type="button" class="button button-light heading-button"
          :disabled="exporting || !rows.length" title="Descarga las existencias visibles según los filtros"
          @click="exportStock">
          <i class="fa-solid" :class="exporting ? 'fa-spinner fa-spin' : 'fa-file-csv'" aria-hidden="true"></i>
          {{ exporting ? 'Exportando...' : 'Exportar CSV' }}
        </button>
        <button type="button" class="button button-light heading-button" :disabled="loading" @click="loadStock">
          <i class="fa-solid fa-rotate" :class="{ 'fa-spin': loading }" aria-hidden="true"></i> Actualizar
        </button>
      </div>
    </div>

    <div class="level-cards" role="group" aria-label="Resumen por nivel de stock">
      <button type="button" class="level-card level-all" :class="{ active: level === '' }" @click="level = ''">
        <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
        <strong>{{ summary.total }}</strong>
        <span>Productos activos</span>
      </button>
      <button v-for="(meta, key) in LEVELS" :key="key" type="button" class="level-card" :class="[meta.css, { active: level === key }]"
        :aria-pressed="level === key" @click="toggleLevel(key)">
        <i class="fa-solid" :class="meta.icon" aria-hidden="true"></i>
        <strong>{{ summary[key] }}</strong>
        <span>{{ meta.label }}</span>
      </button>
    </div>

    <div class="search-toolbar stock-toolbar">
      <div class="search-input-wrap">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input v-model="search" type="search" placeholder="Buscar por código o nombre..." aria-label="Buscar producto">
      </div>
      <select v-model="category" aria-label="Filtrar por categoría">
        <option value="">Todas las categorías</option>
        <option v-for="item in categories" :key="item.id_categoria" :value="item.id_categoria">{{ item.nombre }}</option>
      </select>
      <select v-model="level" class="level-filter" aria-label="Filtrar por nivel de stock">
        <option value="">Todos los niveles</option>
        <option v-for="(meta, key) in LEVELS" :key="key" :value="key">{{ meta.label }}</option>
      </select>
      <button v-if="hasFilters" type="button" class="table-action clear-filters" @click="clearFilters">
        <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i> Limpiar
      </button>
    </div>

    <div class="search-results">
      <p v-if="error" class="feature-alert" role="alert">{{ error }}</p>
      <div v-else-if="loading && !rows.length" class="feature-empty">
        <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
        <p>Consultando existencias...</p>
      </div>
      <div v-else-if="!rows.length" class="feature-empty">
        <i class="fa-solid" :class="hasFilters ? 'fa-filter-circle-xmark' : 'fa-box-open'" aria-hidden="true"></i>
        <p class="feature-empty-title">
          {{ hasFilters ? 'No hay productos que coincidan con los filtros' : 'No hay productos activos registrados' }}
        </p>
        <p v-if="hasFilters">Prueba con otro criterio o limpia los filtros.</p>
      </div>
      <div v-else class="feature-table-wrap">
        <table class="feature-table stock-table">
          <caption class="sr-only">Existencias de productos activos ordenadas por criticidad</caption>
          <thead>
            <tr>
              <th scope="col">Código</th>
              <th scope="col">Producto</th>
              <th scope="col">Categoría</th>
              <th scope="col">Unidad</th>
              <th scope="col" class="align-right">Existencia</th>
              <th scope="col" class="align-right">Mínimo</th>
              <th scope="col" class="align-right" title="Existencia actual menos existencia mínima">Diferencia</th>
              <th scope="col" class="align-center">Nivel</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id_producto" :class="`row-${row.nivel}`">
              <td class="product-code">{{ row.codigo }}</td>
              <td class="product-name">{{ row.nombre }}</td>
              <td>{{ row.categoria || '—' }}</td>
              <td>{{ row.unidad_medida }}</td>
              <td class="align-right quantity">{{ formatQuantity(row.existencia_actual) }}</td>
              <td class="align-right">{{ formatQuantity(row.existencia_minima) }}</td>
              <td class="align-right difference" :class="{ negative: row.diferencia < 0, zero: row.diferencia === 0, positive: row.diferencia > 0 }">
                {{ formatDifference(row.diferencia) }}
              </td>
              <td class="align-center">
                <span class="stock-badge" :class="{ 'stock-badge-low': row.nivel === 'bajo_minimo', 'stock-badge-out': row.nivel === 'agotado' }">
                  <i class="fa-solid" :class="LEVELS[row.nivel].icon" aria-hidden="true"></i> {{ row.nivel_etiqueta }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="truncated" class="table-footnote">
          Mostrando {{ rows.length }} de {{ total }} productos. Usa los filtros para acotar la consulta.
        </p>
      </div>
    </div>
  </section>
</template>

<style scoped>
.feature-page { display: flex; flex-direction: column; gap: 20px; }
.stock-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.stock-heading h1 { margin: 0; color: #172033; font-size: 24px; }
.stock-heading p { margin: 4px 0 0; color: #64748b; font-size: 13px; }
.heading-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.heading-button { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; white-space: nowrap; }
.heading-button:disabled { cursor: not-allowed; opacity: .6; }
.last-update { display: inline-flex; align-items: center; gap: 6px; color: #718096; font-size: 12px; }

.level-cards { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.level-card { display: grid; justify-items: start; gap: 4px; padding: 16px 18px; border: 1px solid #dce4ee; border-radius: 12px; background: #fff; box-shadow: 0 2px 3px #20324d08; text-align: left; transition: border-color .15s ease, transform .15s ease; }
.level-card:hover { transform: translateY(-1px); }
.level-card i { font-size: 18px; }
.level-card strong { color: #172033; font-size: 26px; line-height: 1.1; }
.level-card span { color: #64748b; font-size: 12px; font-weight: 600; }
.level-card.active { border-color: currentColor; box-shadow: 0 0 0 3px #f28c2822; }
.level-all { color: #203d62; }
.level-out { color: #a42828; }
.level-out strong { color: #a42828; }
.level-low { color: #a85a08; }
.level-low strong { color: #a85a08; }
.level-ok { color: #137342; }
.level-ok strong { color: #137342; }

.stock-toolbar { flex-wrap: wrap; align-items: center; }
.search-toolbar .level-filter { width: 180px; }
.clear-filters { display: inline-flex; align-items: center; gap: 6px; }

.stock-table .quantity { font-weight: 700; color: #172033; }
.difference { font-variant-numeric: tabular-nums; font-weight: 700; }
.difference.negative { color: #a42828; }
.difference.zero { color: #a85a08; }
.difference.positive { color: #137342; }
.stock-badge { display: inline-flex; align-items: center; gap: 6px; }
tr.row-agotado td { background: #fff5f5; }
tr.row-bajo_minimo td { background: #fff9ef; }
tr.row-agotado .product-name { color: #a42828; }
tr.row-bajo_minimo .product-name { color: #a85a08; }
.table-footnote { margin: 0; padding: 12px 14px; border-top: 1px solid #e2e8f0; color: #718096; font-size: 12px; }
.sr-only { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0 0 0 0); border: 0; white-space: nowrap; }

@media (max-width: 980px) {
  .level-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 720px) {
  .stock-heading { flex-direction: column; }
  .search-toolbar select { width: 100%; }
}
</style>
