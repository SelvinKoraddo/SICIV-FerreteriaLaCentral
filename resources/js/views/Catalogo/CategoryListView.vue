<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../../services/api';

const categories = ref([]);
const loading = ref(false);
const saving = ref(false);
const statusLoading = ref(false);
const statusAction = ref('');
const error = ref('');
const modal = ref(false);
const editing = ref(null);
const form = reactive({ nombre: '', descripcion: '' });

const reset = () => Object.assign(form, { nombre: '', descripcion: '' });

const load = async () => {
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

const openCreate = () => {
  editing.value = null;
  reset();
  modal.value = true;
};

const openEdit = (category) => {
  editing.value = category;
  Object.assign(form, { nombre: category.nombre, descripcion: category.descripcion || '' });
  modal.value = true;
};

const save = async () => {
  saving.value = true;
  error.value = '';
  try {
    if (editing.value) {
      await api.put(`/api/categorias/${editing.value.id_categoria}`, form);
    } else {
      await api.post('/api/categorias', form);
    }
    modal.value = false;
    await load();
  } catch (exception) {
    error.value = Object.values(exception.response?.data?.errors || {}).flat()[0]
      || exception.response?.data?.message
      || 'No fue posible guardar la categoría.';
  } finally {
    saving.value = false;
  }
};

const toggleStatus = async (category) => {
  statusAction.value = category.activa ? 'Desactivando' : 'Activando';
  statusLoading.value = true;
  try {
    await api.patch(`/api/categorias/${category.id_categoria}/estado`);
    await load();
  } catch (exception) {
    error.value = exception.response?.data?.message || 'No fue posible cambiar el estado.';
  } finally {
    statusLoading.value = false;
    statusAction.value = '';
  }
};

onMounted(load);
</script>

<template>
  <section class="feature-page">
    <div class="page-heading">
      <div><h1>Gestión de categorías</h1><p>Clasifica y organiza el catálogo de productos</p></div>
      <button class="button button-primary" type="button" @click="openCreate">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Nueva categoría
      </button>
    </div>
    <p v-if="error" class="feature-alert">{{ error }}</p>
    <div class="search-results feature-table-wrap">
      <table class="feature-table">
        <thead><tr><th>Nombre</th><th>Descripción</th><th>Productos</th><th>Estado</th><th class="align-right">Acciones</th></tr></thead>
        <tbody>
          <tr v-for="category in categories" :key="category.id_categoria">
            <td class="product-name">{{ category.nombre }}</td>
            <td>{{ category.descripcion || '—' }}</td>
            <td>{{ category.productos_count }}</td>
            <td><span class="status-badge" :class="category.activa ? 'status-active' : 'status-inactive'">{{ category.activa ? 'Activa' : 'Inactiva' }}</span></td>
            <td class="align-right">
              <button class="table-action" type="button" @click="openEdit(category)">Editar</button>
              <button class="table-action" type="button" :disabled="statusLoading" @click="toggleStatus(category)">{{ category.activa ? 'Desactivar' : 'Activar' }}</button>
            </td>
          </tr>
          <tr v-if="!loading && !categories.length"><td colspan="5" class="feature-empty">No hay categorías registradas.</td></tr>
        </tbody>
      </table>
    </div>

    <div v-if="statusLoading" class="modal">
      <div class="loading-panel">
        <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
        <p>{{ statusAction }} categoría...</p>
        <span>Guardando cambios, por favor espera.</span>
      </div>
    </div>

    <div v-if="modal" class="modal">
      <div class="modal-card category-modal">
        <div class="modal-head"><h3>{{ editing ? 'EDITAR CATEGORÍA' : 'NUEVA CATEGORÍA' }}</h3><button type="button" @click="modal = false">&times;</button></div>
        <form class="category-form" @submit.prevent="save">
          <label>Nombre<input v-model.trim="form.nombre" maxlength="50" required></label>
          <label>Descripción<textarea v-model.trim="form.descripcion" maxlength="255" rows="3"></textarea></label>
          <div class="form-actions"><button type="button" class="button button-light" @click="modal = false">Cancelar</button><button class="button button-primary" :disabled="saving">{{ saving ? 'Guardando...' : 'Guardar' }}</button></div>
        </form>
      </div>
    </div>
  </section>
</template>

<style scoped>
.status-badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.status-active { color: #137342; background: #eaf8f0; }
.status-inactive { color: #64748b; background: #eef2f7; }
.modal { position: fixed; inset: 0; z-index: 20; display: grid; place-items: center; padding: 20px; background: #0f192d99; }
.modal-card { width: min(460px, 100%); overflow: hidden; border-radius: 12px; background: #fff; }
.modal-head { display: flex; justify-content: space-between; padding: 16px 20px; color: #fff; background: #203d62; }
.modal-head h3 { margin: 0; font-size: 13px; }
.modal-head button { border: 0; color: #fff; background: transparent; font-size: 22px; }
.category-form { display: grid; gap: 16px; padding: 22px; }
.category-form textarea { width: 100%; padding: 9px 12px; border: 1px solid #ccd6e4; border-radius: 8px; resize: vertical; }
.form-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 8px; border-top: 1px solid #e2e8f0; }
.table-action:disabled { cursor: not-allowed; opacity: .5; }
.loading-panel { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 32px 48px; border-radius: 12px; background: #fff; text-align: center; box-shadow: 0 25px 50px #0004; }
.loading-panel i { color: #203d62; font-size: 24px; }
.loading-panel p { margin: 0; color: #1e293b; font-size: 14px; font-weight: 700; }
.loading-panel span { color: #64748b; font-size: 12px; }
</style>
