<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-900">Gestión de Proveedores</h1>
        <p class="text-xs text-slate-500">Registro de casas comerciales de suministros</p>
      </div>
      <button @click="openCreate" class="bg-[#F28C28] hover:bg-[#e07b1a] text-white text-xs font-semibold px-4 py-2.5 rounded-lg shadow-sm flex items-center space-x-2">
        <i class="fa-solid fa-plus"></i><span>Nuevo Proveedor</span>
      </button>
    </div>

    <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 text-red-700 p-3 text-xs">{{ error }}</div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <table class="w-full text-left border-collapse table-striped">
        <thead>
          <tr class="bg-slate-100 text-slate-600 text-xs uppercase tracking-wider border-b border-slate-200">
            <th class="p-3">Nombre / Razón Social</th><th class="p-3">NIT</th><th class="p-3">Teléfono</th>
            <th class="p-3">Correo Electrónico</th><th class="p-3">Dirección</th><th class="p-3">Estado</th>
            <th class="p-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 text-xs text-slate-700">
          <tr v-for="s in suppliers" :key="s.id">
            <td class="p-3 font-semibold text-slate-900">{{ s.name }}</td><td class="p-3 font-mono">{{ s.nit }}</td>
            <td class="p-3">{{ s.phone }}</td><td class="p-3 text-slate-600">{{ s.email }}</td>
            <td class="p-3 text-slate-500">{{ s.address }}</td>
            <td class="p-3"><span class="px-2 py-0.5 rounded text-[11px] font-semibold" :class="s.active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'">{{ s.active ? 'Activo' : 'Inactivo' }}</span></td>
            <td class="p-3 text-right space-x-1">
              <button @click="openEdit(s)" class="text-[11px] px-2 py-1 rounded bg-blue-50 text-blue-700 hover:bg-blue-100">Editar</button>
              <button @click="toggleStatus(s)" :disabled="statusLoading" class="text-[11px] px-2 py-1 rounded disabled:cursor-not-allowed disabled:opacity-50" :class="s.active ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'">
                {{ s.active ? 'Desactivar' : 'Activar' }}
              </button>
              <button @click="selected = s" class="text-[11px] px-2 py-1 rounded bg-slate-100 hover:bg-slate-200">Ver Entradas</button>
            </td>
          </tr>
          <tr v-if="!suppliers.length"><td colspan="7" class="p-8 text-center text-slate-400">No hay proveedores registrados.</td></tr>
        </tbody>
      </table>
    </div>

    <div v-if="statusLoading" class="modal">
      <div class="loading-panel">
        <i class="fa-solid fa-spinner fa-spin text-2xl text-[#1F3A5F]"></i>
        <p>{{ statusAction }} proveedor...</p>
        <span>Guardando cambios, por favor espera.</span>
      </div>
    </div>

    <div v-if="modal" class="modal">
      <div class="modal-card max-w-lg">
        <div class="modal-head">
          <h3>{{ editing ? 'EDITAR PROVEEDOR' : 'NUEVO PROVEEDOR' }}</h3>
          <button @click="modal = false"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <form @submit.prevent="save" class="p-6 space-y-4">
          <label>Nombre / Razón Social<input v-model="form.name" required></label>
          <div class="grid grid-cols-2 gap-4"><label>NIT<input v-model="form.nit" required></label><label>Teléfono<input v-model="form.phone"></label></div>
          <label>Correo Electrónico<input v-model="form.email" type="email"></label>
          <label>Dirección<input v-model="form.address"></label>
          <div class="flex justify-end space-x-3 pt-3 border-t">
            <button type="button" @click="modal = false" class="px-4 py-2 border rounded-lg text-xs font-semibold">Cancelar</button>
            <button class="px-4 py-2 bg-[#1F3A5F] text-white rounded-lg text-xs font-semibold">{{ editing ? 'Guardar Cambios' : 'Guardar Proveedor' }}</button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="selected" class="modal">
      <div class="modal-card max-w-lg">
        <div class="modal-head"><h3>ENTRADAS DEL PROVEEDOR: {{ selected.name }}</h3><button @click="selected = null"><i class="fa-solid fa-xmark text-lg"></i></button></div>
        <div class="p-6 space-y-3">
          <p class="text-xs text-slate-600">Listado de recepciones registradas en el sistema:</p>
          <table class="w-full text-xs"><thead class="bg-slate-100"><tr><th class="p-2 text-left">Fecha</th><th class="p-2 text-left">Doc. Ref</th><th class="p-2 text-right">Monto Total</th></tr></thead>
            <tbody><tr v-for="e in selected.entries" :key="e.id"><td class="p-2">{{ e.date }}</td><td class="p-2">{{ e.doc }}</td><td class="p-2 text-right font-bold">${{ e.total.toFixed(2) }}</td></tr></tbody>
          </table>
          <button @click="selected = null" class="w-full py-2 bg-slate-200 text-xs rounded-lg">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../../services/api';

const suppliers = ref([]);
const modal = ref(false);
const editing = ref(null);
const selected = ref(null);
const error = ref('');
const statusLoading = ref(false);
const statusAction = ref('');
const form = reactive({ name: '', nit: '', phone: '', email: '', address: '' });

const adapt = (supplier) => ({
  id: supplier.id_proveedor,
  name: supplier.nombre,
  nit: supplier.nit,
  phone: supplier.telefono || '',
  email: supplier.correo || '',
  address: supplier.direccion || '',
  active: !!supplier.activo,
  entries: (supplier.entradas || []).map((entry) => ({
    id: entry.id_entrada,
    date: entry.fecha ? String(entry.fecha).slice(0, 10) : '',
    doc: entry.referencia || entry.numero || '',
    total: Number(entry.total || 0),
  })),
});

function resetForm() {
  Object.assign(form, { name: '', nit: '', phone: '', email: '', address: '' });
}

function openCreate() {
  editing.value = null;
  resetForm();
  modal.value = true;
}

function openEdit(supplier) {
  editing.value = supplier;
  Object.assign(form, { name: supplier.name, nit: supplier.nit, phone: supplier.phone, email: supplier.email, address: supplier.address });
  modal.value = true;
}

async function load() {
  error.value = '';
  try {
    const { data } = await api.get('/api/proveedores');
    suppliers.value = (data.data || []).map(adapt);
  } catch (e) {
    error.value = e.response?.data?.message || 'No fue posible cargar proveedores.';
  }
}

async function save() {
  error.value = '';
  const payload = { nombre: form.name, nit: form.nit, telefono: form.phone, correo: form.email || null, direccion: form.address || null };
  try {
    if (editing.value) {
      await api.put(`/api/proveedores/${editing.value.id}`, payload);
    } else {
      await api.post('/api/proveedores', payload);
    }
    resetForm();
    editing.value = null;
    modal.value = false;
    await load();
  } catch (e) {
    error.value = Object.values(e.response?.data?.errors || {}).flat()[0] || e.response?.data?.message || 'No fue posible guardar el proveedor.';
  }
}

async function toggleStatus(supplier) {
  error.value = '';
  statusAction.value = supplier.active ? 'Desactivando' : 'Activando';
  statusLoading.value = true;
  try {
    await api.patch(`/api/proveedores/${supplier.id}/estado`);
    await load();
  } catch (e) {
    error.value = e.response?.data?.message || 'No fue posible cambiar el estado del proveedor.';
  } finally {
    statusLoading.value = false;
    statusAction.value = '';
  }
}

onMounted(load);
</script>

<style scoped>
label{display:flex;flex-direction:column;gap:.25rem;font-size:.75rem;font-weight:600;text-transform:uppercase;color:#334155}
input{width:100%;padding:.625rem;border:1px solid #cbd5e1;border-radius:.5rem;font-size:.75rem;text-transform:none;font-weight:400}
.table-striped tbody tr:nth-child(even){background:#F8FAFC}
.modal{position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:#0f172a99;backdrop-filter:blur(3px);padding:1rem}
.modal-card{width:100%;overflow:hidden;border-radius:.75rem;background:white;box-shadow:0 25px 50px #0004}
.modal-head{display:flex;justify-content:space-between;align-items:center;background:#1F3A5F;padding:1rem 1.5rem;color:white;font-size:.875rem;font-weight:700;text-transform:uppercase}
.loading-panel{display:flex;flex-direction:column;align-items:center;gap:.5rem;border-radius:.75rem;background:white;padding:2rem 3rem;text-align:center;box-shadow:0 25px 50px #0004}
.loading-panel p{font-size:.875rem;font-weight:700;color:#1e293b}
.loading-panel span{font-size:.75rem;color:#64748b}
</style>
