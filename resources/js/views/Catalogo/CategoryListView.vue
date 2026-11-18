<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../services/api';

const categories = ref([]);
const loading = ref(false);
const error = ref('');
const notice = ref('');
const search = ref('');
const status = ref('');
const openActions = ref(null);
const showForm = ref(false);
const editing = ref(null);
const saving = ref(false);
const formError = ref('');
const form = ref({ nombre: '', descripcion: '' });
const pendingStatusChange = ref(null);
const changingStatus = ref(false);

const filteredCategories = computed(() => categories.value
  .filter((category) => {
    const searchText = search.value.trim().toLowerCase();
    const matchesSearch = !searchText || [category.codigo, category.nombre, category.descripcion]
      .some((value) => String(value || '').toLowerCase().includes(searchText));
    const matchesStatus = !status.value || category.activa === (status.value === 'activa');

    return matchesSearch && matchesStatus;
  })
  .sort((first, second) => first.id_categoria - second.id_categoria));

const summary = computed(() => ({
  total: categories.value.length,
  active: categories.value.filter((category) => category.activa).length,
}));

const hasFilters = computed(() => Boolean(search.value.trim() || status.value));

const loadCategories = async () => {
  loading.value = true;
  error.value = '';

  try {
    categories.value = (await api.get('/api/categorias')).data;
  } catch (exception) {
    error.value = exception.response?.data?.message || 'No fue posible cargar las categorías.';
  } finally {
    loading.value = false;
  }
};

const openForm = (category = null) => {
  editing.value = category;
  form.value = category
    ? { nombre: category.nombre, descripcion: category.descripcion || '' }
    : { nombre: '', descripcion: '' };
  formError.value = '';
  openActions.value = null;
  showForm.value = true;
};

const closeForm = () => {
  if (!saving.value) showForm.value = false;
};

const saveCategory = async () => {
  saving.value = true;
  formError.value = '';

  try {
    const response = editing.value
      ? await api.put(`/api/categorias/${editing.value.id_categoria}`, form.value)
      : await api.post('/api/categorias', form.value);
    notice.value = response.data.message;
    showForm.value = false;
    await loadCategories();
  } catch (exception) {
    formError.value = Object.values(exception.response?.data?.errors ?? {}).flat().join(' ')
      || exception.response?.data?.message || 'No fue posible guardar la categoría.';
  } finally {
    saving.value = false;
  }
};

const requestStatusChange = (category) => {
  openActions.value = null;
  pendingStatusChange.value = { category, activa: !category.activa, error: '' };
};

const cancelStatusChange = () => {
  if (!changingStatus.value) pendingStatusChange.value = null;
};

const confirmStatusChange = async () => {
  if (!pendingStatusChange.value || changingStatus.value) return;

  const { category, activa } = pendingStatusChange.value;
  changingStatus.value = true;

  try {
    const response = await api.patch(`/api/categorias/${category.id_categoria}/estado`, { activa });
    pendingStatusChange.value = null;
    notice.value = response.data.message;
    await loadCategories();
  } catch (exception) {
    if (pendingStatusChange.value) {
      pendingStatusChange.value.error = exception.response?.status === 403
        ? 'No se puede desactivar: la categoría tiene productos activos o historial asociado.'
        : Object.values(exception.response?.data?.errors ?? {}).flat().join(' ')
          || exception.response?.data?.message || 'No fue posible cambiar el estado de la categoría.';
    }
  } finally {
    changingStatus.value = false;
  }
};

onMounted(loadCategories);
</script>

<template>
  <section class="feature-page categories-page" @click="openActions = null">
    <div class="products-heading">
      <div>
        <h1>Categorías</h1>
        <p>Administra las categorías del catálogo</p>
      </div>
      <button type="button" class="button button-primary heading-button" @click="openForm()">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Nueva categoría
      </button>
    </div>

    <div class="search-toolbar categories-toolbar">
      <div class="search-input-wrap">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input v-model="search" type="search" placeholder="Buscar por código, nombre o descripción"
          aria-label="Buscar categorías">
      </div>
      <select v-model="status" aria-label="Filtrar categorías por estado">
        <option value="">Todos los estados</option>
        <option value="activa">Activas</option>
        <option value="inactiva">Inactivas</option>
      </select>
    </div>

    <div class="search-results categories-results">
      <div class="stock-summary categories-summary">
        <span><strong>{{ summary.total }}</strong> categorías</span>
        <span><strong class="ok">{{ summary.active }}</strong> activas</span>
        <button class="table-action refresh-action" type="button" :disabled="loading" @click.stop="loadCategories">
          <i class="fa-solid fa-rotate" :class="{ 'fa-spin': loading }" aria-hidden="true"></i> Actualizar
        </button>
      </div>

      <p v-if="error" class="feature-alert" role="alert">{{ error }}</p>
      <div v-else-if="loading && !categories.length" class="feature-empty">
        <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
        <p>Cargando categorías...</p>
      </div>
      <div v-else-if="!filteredCategories.length" class="feature-empty">
        <i class="fa-solid fa-tags" aria-hidden="true"></i>
        <p>{{ hasFilters ? 'No se encontraron categorías con ese criterio.' : 'Aún no hay categorías registradas.' }}</p>
        <p v-if="!hasFilters">Usa «Nueva categoría» para agregar la primera.</p>
      </div>
      <div v-else class="feature-table-wrap categories-table-wrap" @keydown.esc="openActions = null">
        <div class="categories-table-scroll">
          <table class="feature-table">
          <thead>
            <tr>
              <th scope="col">Código</th>
              <th scope="col">Categoría</th>
              <th scope="col">Descripción</th>
              <th scope="col" class="align-center">Productos</th>
              <th scope="col" class="align-center">Estado</th>
              <th scope="col" class="align-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(category, index) in filteredCategories" :key="category.id_categoria"
              :class="{ inactive: !category.activa }">
              <td class="category-code">{{ category.codigo }}</td>
              <td class="category-name">{{ category.nombre }}</td>
              <td>{{ category.descripcion || '—' }}</td>
              <td class="align-center">{{ category.productos_count }}</td>
              <td class="align-center">
                <span class="category-state" :class="category.activa ? 'state-active' : 'state-inactive'">
                  {{ category.activa ? 'Activa' : 'Inactiva' }}
                </span>
              </td>
              <td class="align-right category-actions-cell">
                <button type="button" class="category-actions-trigger"
                  :aria-label="`Acciones para ${category.nombre}`"
                  :aria-expanded="openActions === category.id_categoria"
                  @click.stop="openActions = openActions === category.id_categoria ? null : category.id_categoria">
                  <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                </button>
                <div v-if="openActions === category.id_categoria" class="category-actions-menu"
                  :class="{ 'category-actions-menu-up': index === filteredCategories.length - 1 }" role="menu"
                  @click.stop>
                  <button type="button" role="menuitem" @click="openForm(category)">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i> Editar
                  </button>
                  <button type="button" role="menuitem" :class="category.activa ? 'deactivate-action' : 'activate-action'"
                    @click="requestStatusChange(category)">
                    <i class="fa-solid" :class="category.activa ? 'fa-ban' : 'fa-circle-check'" aria-hidden="true"></i>
                    {{ category.activa ? 'Desactivar' : 'Activar' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="showForm" class="category-modal" role="dialog" aria-modal="true"
      :aria-labelledby="'category-form-title'" @click.self="closeForm">
      <form class="category-form" @submit.prevent="saveCategory">
        <header class="category-form-header">
          <div>
            <h2 id="category-form-title">{{ editing ? 'Editar categoría' : 'Nueva categoría' }}</h2>
            <p>{{ editing ? 'Actualiza los datos de la categoría.' : 'Agrega una categoría al catálogo.' }}</p>
          </div>
          <button type="button" class="modal-close" aria-label="Cerrar" :disabled="saving" @click="closeForm">&times;</button>
        </header>
        <p v-if="formError" class="feature-alert" role="alert">{{ formError }}</p>
        <label>Nombre
          <input v-model="form.nombre" required maxlength="50" autofocus>
        </label>
        <label>Descripción
          <textarea v-model="form.descripcion" maxlength="255" rows="3"></textarea>
        </label>
        <footer class="category-form-actions">
          <button type="button" class="button button-light" :disabled="saving" @click="closeForm">Cancelar</button>
          <button type="submit" class="button button-primary" :disabled="saving">
            <i v-if="saving" class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
            {{ saving ? 'Guardando...' : 'Guardar categoría' }}
          </button>
        </footer>
      </form>
    </div>

    <div v-if="pendingStatusChange" class="category-modal" role="alertdialog" aria-modal="true"
      aria-labelledby="category-status-title" @keydown.esc="cancelStatusChange">
      <div class="category-confirm-dialog">
        <header class="category-form-header">
          <div>
            <h2 id="category-status-title">{{ pendingStatusChange.activa ? 'Activar categoría' : 'Desactivar categoría' }}</h2>
            <p>{{ pendingStatusChange.category.nombre }}</p>
          </div>
          <button type="button" class="modal-close" aria-label="Cerrar" :disabled="changingStatus"
            @click="cancelStatusChange">&times;</button>
        </header>
        <p class="category-confirm-copy">
          ¿Confirmas que deseas {{ pendingStatusChange.activa ? 'activar' : 'desactivar' }} esta categoría?
        </p>
        <p v-if="pendingStatusChange.error" class="feature-alert" role="alert">{{ pendingStatusChange.error }}</p>
        <footer class="category-form-actions">
          <button type="button" class="button button-light" :disabled="changingStatus"
            @click="cancelStatusChange">Cancelar</button>
          <button type="button" class="button" :class="pendingStatusChange.activa ? 'confirm-activate' : 'confirm-deactivate'"
            :disabled="changingStatus" @click="confirmStatusChange">
            {{ changingStatus ? 'Procesando...' : pendingStatusChange.activa ? 'Sí, activar' : 'Sí, desactivar' }}
          </button>
        </footer>
      </div>
    </div>

    <Transition name="toast">
      <div v-if="notice" class="pos-toast receipt" role="status" aria-live="polite">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <div><strong>{{ notice }}</strong></div>
        <button type="button" aria-label="Cerrar aviso" @click="notice = ''">&times;</button>
      </div>
    </Transition>
  </section>
</template>

<style scoped>
.categories-page { min-width: 0; }
.products-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.products-heading h1 { margin: 0; color: #172033; font-size: 24px; }
.products-heading p { margin: 4px 0 0; color: #64748b; font-size: 13px; }
.heading-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; white-space: nowrap; }
.categories-toolbar { display: grid; align-items: center; grid-template-columns: minmax(0, 1fr) 190px; }
.categories-toolbar .search-input-wrap { min-width: 0; }
.categories-toolbar select { width: 100%; min-width: 0; }
.categories-results { min-width: 0; }
.categories-summary { flex-wrap: wrap; gap: 18px; }
.categories-summary strong { color: #203d62; font-size: 20px; }
.categories-summary strong.ok { color: #137342; }
.categories-summary .refresh-action { display: inline-flex; align-items: center; align-self: center; gap: 6px; margin-left: auto; }
.category-name { color: #172033; font-weight: 700; }
.category-code { color: #475569; font-family: monospace; font-weight: 700; }
.category-state { display: inline-flex; padding: 4px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.category-state.state-active { color: #137342; background: #eaf8f0; }
.category-state.state-inactive { color: #475569; background: #eef2f7; }
.categories-table-wrap { overflow: visible; }
.categories-table-scroll { max-width: 100%; overflow-x: auto; overflow-y: hidden; }
.categories-table-wrap .feature-table { width: 100%; min-width: 880px; table-layout: fixed; }
.categories-table-wrap .feature-table th:nth-child(1) { width: 15%; }
.categories-table-wrap .feature-table th:nth-child(2) { width: 19%; }
.categories-table-wrap .feature-table th:nth-child(3) { width: 34%; }
.categories-table-wrap .feature-table th:nth-child(4) { width: 11%; }
.categories-table-wrap .feature-table th:nth-child(5) { width: 13%; }
.categories-table-wrap .feature-table th:nth-child(6) { width: 8%; }
.categories-table-wrap .feature-table td:nth-child(3) { overflow-wrap: anywhere; }
.category-actions-cell { position: relative; min-width: 72px; }
.category-actions-trigger { display: inline-grid; width: 34px; height: 34px; place-items: center; border: 1px solid transparent; border-radius: 7px; color: #475569; background: transparent; }
.category-actions-trigger:hover, .category-actions-trigger[aria-expanded="true"] { border-color: #dce4ee; color: #203d62; background: #f1f5f9; }
.category-actions-menu { position: absolute; top: calc(100% - 4px); right: 8px; z-index: 3; display: grid; min-width: 150px; padding: 4px; border: 1px solid #dce4ee; border-radius: 7px; background: #fff; box-shadow: 0 5px 16px #17203326; text-align: left; }
.category-actions-menu-up { top: auto; bottom: calc(100% - 4px); }
.category-actions-menu button { display: flex; align-items: center; gap: 9px; padding: 9px 10px; border: 0; border-radius: 4px; color: #334155; background: transparent; font-size: 12px; text-align: left; }
.category-actions-menu button:hover { background: #f1f5f9; }
.category-actions-menu i { width: 14px; text-align: center; }
.category-actions-menu .deactivate-action { color: #a42828; }
.category-actions-menu .activate-action { color: #137342; }
.categories-page tr.inactive td { color: #94a3b8; }
.categories-page tr.inactive .category-state { color: #475569; }
.category-modal { position: fixed; inset: 0; z-index: 20; display: grid; overflow-y: auto; padding: 20px; place-items: center; background: #0f192d80; }
.category-form, .category-confirm-dialog { display: grid; width: min(500px, 100%); gap: 16px; padding: 24px; border: 1px solid #dce4ee; border-radius: 10px; background: #fff; box-shadow: 0 12px 35px #17203340; }
.category-form-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.category-form-header h2 { margin: 0; color: #172033; font-size: 20px; }
.category-form-header p { margin: 4px 0 0; color: #64748b; font-size: 12px; }
.category-form .modal-close, .category-confirm-dialog .modal-close { padding: 0 4px; }
.category-form label { display: grid; gap: 6px; color: #445168; font-size: 12px; font-weight: 700; }
.category-form input, .category-form textarea { width: 100%; padding: 10px 12px; border: 1px solid #ccd6e4; border-radius: 7px; color: #172033; background: #fff; font: inherit; font-weight: 400; resize: vertical; }
.category-form input:focus, .category-form textarea:focus { border-color: #f28c28; outline: none; box-shadow: 0 0 0 3px #f28c2822; }
.category-form-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 4px; }
.category-form-actions .button { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; }
.category-form-actions .button:disabled, .category-form .modal-close:disabled { cursor: not-allowed; opacity: .6; }
.category-confirm-copy { margin: 0; color: #334155; font-size: 13px; }
.confirm-deactivate { color: #fff; background: #dc2626; }
.confirm-deactivate:hover:not(:disabled) { background: #b91c1c; }
.confirm-activate { color: #fff; background: #16a34a; }
.confirm-activate:hover:not(:disabled) { background: #15803d; }

@media (max-width: 1100px) {
  .categories-toolbar { grid-template-columns: minmax(0, 1fr) 190px; }
}

@media (max-width: 800px) {
  .categories-toolbar { grid-template-columns: 1fr; }
  .categories-toolbar select { width: 100%; }
}

@media (max-width: 720px) {
  .products-heading { flex-direction: column; }
  .categories-summary .refresh-action { margin-left: 0; }
  .category-form, .category-confirm-dialog { padding: 18px; }
  .category-form-actions { flex-wrap: wrap; }
}
</style>
