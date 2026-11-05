<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../services/api';

const users = ref([]);
const loading = ref(false);
const error = ref('');
const search = ref('');
const roleFilter = ref('');
const statusFilter = ref('');
const showForm = ref(false);
const editingUser = ref(null);
const openActions = ref(null);
const saving = ref(false);
const formError = ref('');
const form = ref({});

const filteredUsers = computed(() => users.value.filter((user) => {
    const text = search.value.trim().toLowerCase();
    const matchesText = !text || [user.name, user.usuario, user.email]
        .some((value) => String(value || '').toLowerCase().includes(text));
    const matchesRole = !roleFilter.value || user.rol === roleFilter.value;
    const matchesStatus = !statusFilter.value
        || (statusFilter.value === 'activo' ? user.activo : !user.activo);
    return matchesText && matchesRole && matchesStatus;
}));

const loadUsers = async () => {
    loading.value = true;
    error.value = '';
    try {
        users.value = (await api.get('/api/usuarios')).data;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar los usuarios.';
    } finally {
        loading.value = false;
    }
};

const resetForm = () => {
    form.value = { name: '', usuario: '', email: '', rol: 'VENDEDOR', password: '', activo: true };
    formError.value = '';
};

const openNewUser = () => {
    editingUser.value = null;
    resetForm();
    showForm.value = true;
};

const openEdit = (user) => {
    editingUser.value = user;
    form.value = { ...user, password: '' };
    formError.value = '';
    openActions.value = null;
    showForm.value = true;
};

const saveUser = async () => {
    saving.value = true;
    formError.value = '';
    try {
        const payload = { ...form.value };
        if (editingUser.value && !payload.password) delete payload.password;
        const response = editingUser.value
            ? await api.put(`/api/usuarios/${editingUser.value.id}`, payload)
            : await api.post('/api/usuarios', payload);
        const saved = response.data;
        const index = users.value.findIndex((user) => user.id === saved.id);
        if (index >= 0) users.value[index] = saved;
        else users.value.push(saved);
        showForm.value = false;
    } catch (exception) {
        formError.value = Object.values(exception.response?.data?.errors ?? {}).flat().join(' ')
            || exception.response?.data?.message || 'No fue posible guardar el usuario.';
    } finally {
        saving.value = false;
    }
};

const toggleStatus = async (user) => {
    openActions.value = null;
    try {
        const response = await api.patch(`/api/usuarios/${user.id}/estado`);
        const index = users.value.findIndex((item) => item.id === user.id);
        if (index >= 0) users.value[index] = response.data.user;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cambiar el estado del usuario.';
    }
};

onMounted(loadUsers);
</script>

<template>
    <section class="feature-page">
        <div class="page-heading users-heading">
            <div><h1>Gestión de Usuarios</h1><p>Administración de cuentas y permisos del sistema SICIV</p></div>
            <button class="action-btn" type="button" @click="openNewUser">
                <i class="fa-solid fa-user-plus"></i> Nuevo Usuario
            </button>
        </div>

        <div class="search-toolbar users-filters">
            <label>USUARIO<input v-model="search" type="text" placeholder="Buscar usuario"></label>
            <label>ROL<select v-model="roleFilter">
                <option value="">Todos los roles</option><option>ADMINISTRADOR</option>
                <option>VENDEDOR</option><option>ENCARGADO_INVENTARIO</option>
            </select></label>
            <label>ESTADO<select v-model="statusFilter">
                <option value="">Todos</option><option value="activo">Activo</option><option value="inactivo">Inactivo</option>
            </select></label>
            <button class="filter-button" type="button" @click="loadUsers">Actualizar</button>
        </div>

        <div class="feature-table-wrap">
            <table class="feature-table">
                <thead><tr><th>NOMBRE COMPLETO</th><th>USUARIO</th><th>CORREO</th><th>ROL</th><th>ESTADO</th><th>ACCIONES</th></tr></thead>
                <tbody>
                    <tr v-if="loading"><td colspan="6" class="empty-state">Cargando usuarios...</td></tr>
                    <tr v-else-if="error"><td colspan="6" class="empty-state">{{ error }}</td></tr>
                    <tr v-else-if="!filteredUsers.length"><td colspan="6" class="empty-state">No hay usuarios registrados.</td></tr>
                    <tr v-for="user in filteredUsers" :key="user.id">
                        <td>{{ user.name }}</td><td>{{ user.usuario }}</td><td>{{ user.email }}</td><td>{{ user.rol }}</td>
                        <td><span :class="['status-badge', user.activo ? 'active' : 'inactive']">
                            {{ user.activo ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="actions-cell">
                            <button class="action-button" type="button" aria-label="Acciones"
                                @click="openActions = openActions === user.id ? null : user.id">⋮</button>
                            <div v-if="openActions === user.id" class="actions-menu">
                                <button type="button" @click="openEdit(user)">Editar</button>
                                <button type="button" @click="toggleStatus(user)">{{ user.activo ? 'Desactivar' : 'Activar' }}</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showForm" class="user-modal" role="dialog" aria-modal="true">
            <form class="user-form" @submit.prevent="saveUser">
                <header><h2>{{ editingUser ? 'Editar Usuario' : 'Nuevo Usuario' }}</h2>
                    <button type="button" class="modal-close" aria-label="Cerrar" @click="showForm = false">&times;</button></header>
                <p v-if="formError" class="form-error">{{ formError }}</p>
                <label>Nombre completo<input v-model="form.name" required maxlength="255"></label>
                <label>Usuario<input v-model="form.usuario" required maxlength="50" pattern="[A-Za-z0-9_-]+"></label>
                <label>Correo electrónico<input v-model="form.email" type="email" required></label>
                <label>Rol<select v-model="form.rol" required><option>ADMINISTRADOR</option><option>VENDEDOR</option><option>ENCARGADO_INVENTARIO</option></select></label>
                <label>Contraseña <small v-if="editingUser">(vacío para conservarla)</small>
                    <input v-model="form.password" type="password" :required="!editingUser" minlength="8"></label>
                <label class="checkbox-label"><input v-model="form.activo" type="checkbox"> Usuario activo</label>
                <footer><button type="button" class="cancel-button" @click="showForm = false">Cancelar</button>
                    <button class="action-btn" type="submit" :disabled="saving">{{ saving ? 'Guardando...' : 'Guardar' }}</button></footer>
            </form>
        </div>
    </section>
</template>

<style scoped>
.users-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; }
.users-filters { align-items: end; }
.action-btn, .filter-button { border: 0; border-radius: 8px; color: #fff; background: #1f3b68; font-weight: 700; cursor: pointer; }
.action-btn { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 18px; }
.filter-button { min-width: 140px; height: 48px; }
.action-btn:hover, .filter-button:hover { background: #25497e; }
.status-badge { display: inline-flex; padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.status-badge.active { color: #166534; background: #dcfce7; }
.status-badge.inactive { color: #991b1b; background: #fee2e2; }
.actions-cell { position: relative; }
.action-button { width: 36px; height: 36px; border: 0; border-radius: 8px; background: #f1f5f9; font-size: 20px; cursor: pointer; }
.actions-menu { position: absolute; right: 8px; z-index: 2; display: grid; min-width: 130px; border: 1px solid #dce4ee; border-radius: 8px; background: #fff; box-shadow: 0 5px 16px #17203326; }
.actions-menu button { padding: 9px 12px; border: 0; color: #334155; text-align: left; background: transparent; cursor: pointer; }
.actions-menu button:hover { background: #f1f5f9; }
.empty-state { padding: 40px; color: #64748b; text-align: center; }
.user-modal { position: fixed; inset: 0; z-index: 20; display: grid; padding: 20px; place-items: center; background: #0f192d80; }
.user-form { display: grid; width: min(520px, 100%); gap: 14px; padding: 24px; border-radius: 12px; background: #fff; box-shadow: 0 12px 35px #17203340; }
.user-form header, .user-form footer { display: flex; align-items: center; justify-content: space-between; }
.user-form h2 { margin: 0; color: #1f3a5f; font-size: 20px; }
.user-form label { display: grid; gap: 5px; color: #475569; font-size: 12px; font-weight: 700; }
.user-form input, .user-form select { min-height: 40px; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; font-weight: 400; }
.modal-close { border: 0; color: #64748b; background: transparent; font-size: 24px; cursor: pointer; }
.form-error { margin: 0; padding: 10px; border-radius: 6px; color: #991b1b; background: #fff1f2; font-size: 12px; }
.checkbox-label { display: flex !important; grid-template-columns: auto 1fr; align-items: center; }
.checkbox-label input { min-height: auto; }
.cancel-button { padding: 12px 18px; border: 0; border-radius: 8px; color: #475569; background: #e2e8f0; cursor: pointer; }
@media (max-width: 700px) { .users-heading { flex-direction: column; } .users-filters { flex-direction: column; align-items: stretch; } .filter-button { width: 100%; } }
</style>
