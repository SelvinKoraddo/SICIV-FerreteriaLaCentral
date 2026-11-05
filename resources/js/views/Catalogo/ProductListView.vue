<script setup>
/**
 * Gestión del catálogo de productos.
 *
 * Lista los productos con búsqueda y filtros por categoría y estado, y abre el
 * formulario para registrarlos o editarlos. Tras guardar muestra la confirmación
 * con el código asignado (CA-4) y resalta la fila afectada. Los productos no se
 * eliminan: se activan o desactivan para conservar su historial (RN-08).
 *
 * @see HU-03 Registro de productos
 * @see RN-08 Conservación del historial
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import api from '../../services/api';
import ProductFormModal from '../../components/Productos/ProductFormModal.vue';

const SEARCH_DEBOUNCE_MS = 350;
const TOAST_DURATION_MS = 6000;
const HIGHLIGHT_DURATION_MS = 4000;
const PAGE_SIZE = 200;

const products = ref([]);
const categories = ref([]);
const units = ref([]);
const search = ref('');
const category = ref('');
const status = ref('');
const loading = ref(false);
const error = ref('');
const showForm = ref(false);
const editing = ref(null);
const toast = ref(null);
const highlightedId = ref(null);
const pendingStatusChange = ref(null);
const changingStatus = ref(false);
const statusCancelButton = ref(null);
const exporting = ref(false);

const timers = { search: null, toast: null, highlight: null };
let requestSequence = 0;

const formatMoney = (value) => `$${Number(value ?? 0).toFixed(2)}`;
const formatQuantity = (value) => {
  const number = Number(value ?? 0);
  return Number.isInteger(number) ? String(number) : number.toFixed(2);
};

/** Clasificación de existencias según RN-04: agotado (0) o bajo mínimo. */
const stockState = (product) => {
  const current = Number(product.existencia_actual);
  if (current === 0) return { label: 'Agotado', css: 'stock-badge-out', alert: true };
  if (current <= Number(product.existencia_minima)) return { label: 'Reponer', css: 'stock-badge-low', alert: true };
  return { label: 'Normal', css: '', alert: false };
};

/** Productos con su estado de existencias precalculado (una sola evaluación por fila). */
const rows = computed(() => products.value.map((product) => ({ ...product, stock: stockState(product) })));

/** Indicadores del encabezado, calculados en un único recorrido. */
const summary = computed(() => rows.value.reduce(
  (totals, product) => {
    totals.total += 1;
    if (product.activo) {
      totals.active += 1;
      if (product.stock.alert) totals.alerts += 1;
    }
    return totals;
  },
  { total: 0, active: 0, alerts: 0 },
));

const hasFilters = computed(() => Boolean(search.value.trim() || category.value || status.value));

const loadCatalogs = async () => {
  const [categoriesResponse, unitsResponse] = await Promise.all([
    api.get('/api/categorias'),
    api.get('/api/productos/unidades-medida'),
  ]);
  categories.value = categoriesResponse.data;
  units.value = unitsResponse.data;
};

/**
 * Consulta los productos con los filtros vigentes. El número de secuencia
 * descarta respuestas de peticiones anteriores que lleguen fuera de orden.
 */
/** Parámetros de filtrado compartidos por el listado y la exportación. */
const filterParams = () => ({
  search: search.value.trim() || undefined,
  categoria: category.value || undefined,
  estado: status.value || undefined,
});

const loadProducts = async () => {
  const sequence = ++requestSequence;
  loading.value = true;
  error.value = '';

  try {
    const { data } = await api.get('/api/productos', { params: { ...filterParams(), per_page: PAGE_SIZE } });
    if (sequence === requestSequence) products.value = data.data ?? data;
  } catch (exception) {
    if (sequence === requestSequence) {
      products.value = [];
      error.value = exception.response?.data?.message || 'No fue posible cargar el catálogo de productos.';
    }
  } finally {
    if (sequence === requestSequence) loading.value = false;
  }
};

const openForm = (product = null) => {
  editing.value = product;
  showForm.value = true;
};

const closeForm = () => {
  showForm.value = false;
  editing.value = null;
};

const showToast = (message) => {
  clearTimeout(timers.toast);
  toast.value = { message };
  timers.toast = setTimeout(() => { toast.value = null; }, TOAST_DURATION_MS);
};

const highlight = (id) => {
  clearTimeout(timers.highlight);
  highlightedId.value = id;
  timers.highlight = setTimeout(() => { highlightedId.value = null; }, HIGHLIGHT_DURATION_MS);
};

const onSaved = async ({ product, message }) => {
  closeForm();
  showToast(message);
  await loadProducts();
  highlight(product.id_producto);
};

/** Obtiene el nombre del archivo desde la cabecera Content-Disposition. */
const fileNameFrom = (header) => {
  const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(header ?? '');
  return match ? decodeURIComponent(match[1]) : 'catalogo_productos.csv';
};

/** Descarga el catálogo en CSV con los filtros vigentes. */
const exportCatalog = async () => {
  if (exporting.value) return;
  exporting.value = true;

  try {
    const response = await api.get('/api/productos/exportar', { params: filterParams(), responseType: 'blob' });
    const url = URL.createObjectURL(response.data);
    const link = Object.assign(document.createElement('a'), {
      href: url,
      download: fileNameFrom(response.headers['content-disposition']),
    });

    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    showToast('Catálogo exportado correctamente.');
  } catch (exception) {
    error.value = exception.response?.status === 403
      ? 'No tienes permisos para exportar el catálogo.'
      : 'No fue posible exportar el catálogo. Intenta de nuevo.';
  } finally {
    exporting.value = false;
  }
};

/** Abre el diálogo de confirmación para activar o desactivar un producto. */
const requestStatusChange = async (product) => {
  pendingStatusChange.value = { product, activate: !product.activo, error: '' };
  // El foco inicial en «Cancelar» evita confirmaciones accidentales y habilita la tecla Esc.
  await nextTick();
  statusCancelButton.value?.focus();
};

const cancelStatusChange = () => {
  if (!changingStatus.value) pendingStatusChange.value = null;
};

const confirmStatusChange = async () => {
  if (!pendingStatusChange.value || changingStatus.value) return;

  const { product, activate } = pendingStatusChange.value;
  changingStatus.value = true;

  try {
    const { data } = await api.patch(`/api/productos/${product.id_producto}/estado`, { activo: activate });
    pendingStatusChange.value = null;
    showToast(data.message);
    await loadProducts();
    highlight(product.id_producto);
  } catch (exception) {
    const statusCode = exception.response?.status;
    pendingStatusChange.value.error = statusCode === 403
      ? 'No tienes permisos para cambiar el estado de los productos.'
      : exception.response?.data?.message || 'No fue posible cambiar el estado del producto.';
  } finally {
    changingStatus.value = false;
  }
};

watch([search, category, status], () => {
  clearTimeout(timers.search);
  timers.search = setTimeout(loadProducts, SEARCH_DEBOUNCE_MS);
});

onMounted(async () => {
  try {
    await Promise.all([loadCatalogs(), loadProducts()]);
  } catch (exception) {
    error.value = exception.response?.data?.message || 'No fue posible cargar el catálogo.';
  }
});

onBeforeUnmount(() => Object.values(timers).forEach(clearTimeout));
</script>

<template>
  <section class="feature-page">
    <div class="products-heading">
      <div>
        <h1>Productos</h1>
        <p>Catálogo de productos: registro y mantenimiento</p>
      </div>
      <div class="heading-actions">
        <button type="button" class="button button-light heading-button" :disabled="exporting || !rows.length"
          title="Descarga los productos visibles según los filtros aplicados" @click="exportCatalog">
          <i class="fa-solid" :class="exporting ? 'fa-spinner fa-spin' : 'fa-file-csv'" aria-hidden="true"></i>
          {{ exporting ? 'Exportando...' : 'Exportar CSV' }}
        </button>
        <button type="button" class="button button-primary heading-button" @click="openForm()">
          <i class="fa-solid fa-plus" aria-hidden="true"></i> Nuevo producto
        </button>
      </div>
    </div>

    <div class="search-toolbar products-toolbar">
      <div class="search-input-wrap">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input v-model="search" type="search" placeholder="Buscar por código o nombre (ej: PRD-000001, Martillo)..."
          aria-label="Buscar productos">
      </div>
      <select v-model="category" aria-label="Filtrar por categoría">
        <option value="">Todas las categorías</option>
        <option v-for="item in categories" :key="item.id_categoria" :value="item.id_categoria">{{ item.nombre }}</option>
      </select>
      <select v-model="status" class="status-filter" aria-label="Filtrar por estado">
        <option value="">Todos los estados</option>
        <option value="activos">Activos</option>
        <option value="inactivos">Inactivos</option>
      </select>
    </div>

    <div class="search-results">
      <div class="stock-summary products-summary">
        <span><strong>{{ summary.total }}</strong> productos</span>
        <span><strong class="ok">{{ summary.active }}</strong> activos</span>
        <span><strong class="warn">{{ summary.alerts }}</strong> en alerta de stock</span>
        <button class="table-action refresh-action" type="button" :disabled="loading" @click="loadProducts">
          <i class="fa-solid fa-rotate" :class="{ 'fa-spin': loading }" aria-hidden="true"></i> Actualizar
        </button>
      </div>

      <p v-if="error" class="feature-alert" role="alert">{{ error }}</p>
      <div v-else-if="loading && !rows.length" class="feature-empty">
        <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
        <p>Cargando productos...</p>
      </div>
      <div v-else-if="!rows.length" class="feature-empty">
        <i class="fa-solid fa-box-open" aria-hidden="true"></i>
        <p class="feature-empty-title">
          {{ hasFilters ? 'No se encontraron productos con ese criterio' : 'Aún no hay productos registrados' }}
        </p>
        <p v-if="!hasFilters">Usa «Nuevo producto» para registrar el primero.</p>
      </div>
      <div v-else class="feature-table-wrap">
        <table class="feature-table">
          <thead>
            <tr>
              <th scope="col">Código</th>
              <th scope="col">Producto</th>
              <th scope="col">Categoría</th>
              <th scope="col">Unidad</th>
              <th scope="col" class="align-right">Precio</th>
              <th scope="col" class="align-right">Existencia</th>
              <th scope="col" class="align-right">Mínimo</th>
              <th scope="col" class="align-center">Stock</th>
              <th scope="col" class="align-center">Estado</th>
              <th scope="col" class="align-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="product in rows" :key="product.id_producto"
              :class="{ highlighted: product.id_producto === highlightedId, inactive: !product.activo }">
              <td class="product-code">{{ product.codigo }}</td>
              <td class="product-name">
                {{ product.nombre }}
                <small v-if="product.descripcion" class="product-description">{{ product.descripcion }}</small>
              </td>
              <td>{{ product.categoria?.nombre || '—' }}</td>
              <td>{{ product.unidad_medida }}</td>
              <td class="align-right">{{ formatMoney(product.precio_venta) }}</td>
              <td class="align-right">{{ formatQuantity(product.existencia_actual) }}</td>
              <td class="align-right">{{ formatQuantity(product.existencia_minima) }}</td>
              <td class="align-center">
                <span class="stock-badge" :class="product.stock.css">{{ product.stock.label }}</span>
              </td>
              <td class="align-center">
                <span class="state-badge" :class="product.activo ? 'state-active' : 'state-inactive'">
                  {{ product.activo ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
              <td class="align-right">
                <div class="row-actions">
                  <button type="button" class="table-action" :aria-label="`Editar ${product.codigo}`"
                    @click="openForm(product)">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i> Editar
                  </button>
                  <button type="button" class="table-action"
                    :class="product.activo ? 'action-deactivate' : 'action-activate'"
                    :aria-label="`${product.activo ? 'Desactivar' : 'Activar'} ${product.codigo}`"
                    @click="requestStatusChange(product)">
                    <i class="fa-solid" :class="product.activo ? 'fa-ban' : 'fa-circle-check'" aria-hidden="true"></i>
                    {{ product.activo ? 'Desactivar' : 'Activar' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <ProductFormModal v-if="showForm" :product="editing" :categories="categories" :units="units"
      @saved="onSaved" @close="closeForm" />

    <div v-if="pendingStatusChange" class="sale-detail-modal" role="alertdialog" aria-modal="true"
      aria-labelledby="status-dialog-title" @keydown.esc="cancelStatusChange">
      <div class="sale-detail-box">
        <header class="sale-detail-header">
          <h2 id="status-dialog-title">
            {{ pendingStatusChange.activate ? 'ACTIVAR PRODUCTO' : 'DESACTIVAR PRODUCTO' }}
          </h2>
          <button type="button" class="modal-close" aria-label="Cerrar" :disabled="changingStatus"
            @click="cancelStatusChange">&times;</button>
        </header>
        <div class="sale-detail-content status-dialog">
          <p>
            ¿Deseas {{ pendingStatusChange.activate ? 'activar' : 'desactivar' }} el producto
            <strong>{{ pendingStatusChange.product.codigo }} — {{ pendingStatusChange.product.nombre }}</strong>?
          </p>
          <p class="status-dialog-note">
            {{ pendingStatusChange.activate
              ? 'Volverá a estar disponible para ventas y entradas de inventario.'
              : 'No se elimina: conserva su código e historial, pero no podrá seleccionarse en nuevas ventas ni entradas.' }}
          </p>
          <p v-if="pendingStatusChange.error" class="feature-alert" role="alert">{{ pendingStatusChange.error }}</p>
          <div class="status-dialog-actions">
            <button ref="statusCancelButton" type="button" class="button button-light" :disabled="changingStatus"
              @click="cancelStatusChange">
              Cancelar
            </button>
            <button type="button" class="button"
              :class="pendingStatusChange.activate ? 'button-confirm-activate' : 'button-confirm-deactivate'"
              :disabled="changingStatus" @click="confirmStatusChange">
              <i v-if="changingStatus" class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
              {{ pendingStatusChange.activate ? 'Sí, activar' : 'Sí, desactivar' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <Transition name="toast">
      <div v-if="toast" class="pos-toast receipt" role="status" aria-live="polite">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <div><strong>{{ toast.message }}</strong></div>
        <button type="button" aria-label="Cerrar aviso" @click="toast = null">&times;</button>
      </div>
    </Transition>
  </section>
</template>

<style scoped>
.feature-page { display: flex; flex-direction: column; gap: 20px; }
.products-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.products-heading h1 { margin: 0; color: #172033; font-size: 24px; }
.products-heading p { margin: 4px 0 0; color: #64748b; font-size: 13px; }
.heading-actions { display: flex; flex-wrap: wrap; gap: 10px; }
.heading-button { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; white-space: nowrap; }
.heading-button:disabled { cursor: not-allowed; opacity: .6; }
.products-toolbar { flex-wrap: wrap; align-items: center; }
.search-toolbar .status-filter { width: 180px; }
.row-actions { display: inline-flex; gap: 6px; justify-content: flex-end; }
.action-deactivate { color: #a42828; }
.action-deactivate:hover:not(:disabled) { border-color: #a42828; background: #fff0f0; }
.action-activate { color: #137342; }
.action-activate:hover:not(:disabled) { border-color: #137342; background: #eaf8f0; }
.status-dialog { display: grid; gap: 12px; color: #334155; font-size: 13px; }
.status-dialog p { margin: 0; }
.status-dialog-note { color: #64748b; font-size: 12px; }
.status-dialog-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 6px; }
.status-dialog-actions .button { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; }
.status-dialog-actions .button:disabled { cursor: not-allowed; opacity: .6; }
.button-confirm-deactivate { color: #fff; background: #dc2626; }
.button-confirm-deactivate:hover:not(:disabled) { background: #b91c1c; }
.button-confirm-activate { color: #fff; background: #16a34a; }
.button-confirm-activate:hover:not(:disabled) { background: #15803d; }
.products-summary { flex-wrap: wrap; gap: 18px; }
.products-summary strong { color: #203d62; font-size: 20px; }
.products-summary strong.ok { color: #137342; }
.products-summary strong.warn { color: #c76c0b; }
.products-summary .refresh-action { display: inline-flex; align-items: center; align-self: center; gap: 6px; margin-left: auto; }
.product-description { display: block; margin-top: 2px; color: #718096; font-size: 11px; font-weight: 400; }
.state-badge { display: inline-block; padding: 4px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.state-active { color: #137342; background: #eaf8f0; }
.state-inactive { color: #475569; background: #eef2f7; }
tr.inactive td,
tr.inactive .product-name { color: #94a3b8; }
tr.highlighted td { background: #fff7eb; transition: background .6s ease; }
.table-action { display: inline-flex; align-items: center; gap: 6px; }
.toast-enter-active,
.toast-leave-active { transition: opacity .25s ease, transform .25s ease; }
.toast-enter-from,
.toast-leave-to { opacity: 0; transform: translate(-50%, -8px); }

@media (max-width: 720px) {
  .products-heading { flex-direction: column; }
  .search-toolbar select { width: 100%; }
}
</style>
