<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import api, { SESSION_EXPIRED_EVENT } from './services/api';
import LoginForm from './components/Auth/LoginForm.vue';
import Sidebar from './views/Layout/Sidebar.vue';
import DashboardView from './views/Dashboard/DashboardView.vue';
import ProductListView from './views/Catalogo/ProductListView.vue';
import CategoryListView from './views/Catalogo/CategoryListView.vue';
import SupplierListView from './views/Proveedores/SupplierListView.vue';
import InventoryEntryView from './views/Inventario/InventoryEntryView.vue';
import StockListView from './views/Inventario/StockListView.vue';
import AlertListView from './views/Inventario/AlertListView.vue';
import SalesListView from './views/Ventas/SalesListView.vue';
import PosView from './views/Ventas/PosView.vue';
import SalesSummaryView from './views/Ventas/SalesSummaryView.vue';
import TopProductsView from './views/Ventas/TopProductsView.vue';
import ProductSearchView from './views/Catalogo/ProductSearchView.vue';
import UsersView from './views/Usuarios/UsersView.vue';
import MovementHistoryView from './views/Inventario/MovementHistoryView.vue';

const user = ref(null);
const screen = ref('dashboard');
const loading = ref(true);
const error = ref('');
const loginNotice = ref('');
const alertCounts = ref({ total: 0, agotado: 0, bajo_minimo: 0 });
const showAlertBanner = ref(false);
const role = computed(() => user.value?.rol);
const canManageCatalog = computed(() => ['ADMINISTRADOR', 'ENCARGADO_INVENTARIO'].includes(role.value));
const canSell = computed(() => ['ADMINISTRADOR', 'VENDEDOR'].includes(role.value));
const userName = computed(() => user.value?.name || 'Usuario');

/** Frecuencia con la que se actualiza el contador de alertas del menú. */
const ALERT_REFRESH_MS = 60000;
let alertTimer = null;

/**
 * Consulta el número de alertas activas (HU-08 CA-3). Un fallo no interrumpe la
 * aplicación: el contador conserva su último valor conocido.
 */
const loadAlertCounts = async () => {
    if (!user.value) return;
    try {
        alertCounts.value = (await api.get('/api/alertas/conteo')).data;
    } catch {
        // El contador es informativo; los errores de sesión los gestiona el interceptor.
    }
};

const startAlertPolling = () => {
    clearInterval(alertTimer);
    alertTimer = setInterval(loadAlertCounts, ALERT_REFRESH_MS);
};

const stopAlertPolling = () => {
    clearInterval(alertTimer);
    alertTimer = null;
    alertCounts.value = { total: 0, agotado: 0, bajo_minimo: 0 };
    showAlertBanner.value = false;
};

const loadSession = async () => {
    try {
        user.value = (await api.get('/api/me')).data.user;
        await loadAlertCounts();
        startAlertPolling();
    } catch (exception) {
        if (exception.response?.status !== 401) error.value = 'No fue posible verificar la sesión.';
    } finally {
        loading.value = false;
    }
};

const resetSession = () => {
    user.value = null;
    screen.value = 'dashboard';
    stopAlertPolling();
};

const logout = async () => {
    try {
        await api.post('/api/logout');
    } finally {
        resetSession();
    }
};

/** Regresa al inicio de sesión cuando el servidor informa que la sesión caducó. */
const onSessionExpired = () => {
    if (!user.value) return;
    resetSession();
    loginNotice.value = 'Tu sesión expiró por inactividad. Vuelve a iniciar sesión.';
};

/** Al iniciar sesión se informa el número de alertas activas (HU-08 CA-3). */
const onAuthenticated = async (authenticatedUser) => {
    user.value = authenticatedUser;
    loginNotice.value = '';
    await loadAlertCounts();
    showAlertBanner.value = alertCounts.value.total > 0;
    startAlertPolling();
};

const openAlerts = () => {
    showAlertBanner.value = false;
    screen.value = 'alerts';
};

// Cada cambio de pantalla refresca el contador para reflejar ventas o entradas recientes.
watch(screen, loadAlertCounts);

onMounted(() => {
    window.addEventListener(SESSION_EXPIRED_EVENT, onSessionExpired);
    loadSession();
});
onBeforeUnmount(() => {
    window.removeEventListener(SESSION_EXPIRED_EVENT, onSessionExpired);
    clearInterval(alertTimer);
});
</script>

<template>
    <div v-if="loading" class="loading-screen">Cargando Ferretería La Central...</div>
    <LoginForm v-else-if="!user" :notice="loginNotice" @authenticated="onAuthenticated" />
    <div v-else class="app-shell">
        <header class="topbar">
            <div class="topbar-brand">
                <span class="brand-symbol" aria-hidden="true"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                <strong>SICIV</strong>
                <span class="brand-divider"></span>
                <span class="business-name">Ferretería La Central, S.A. de C.V.</span>
            </div>
            <div class="topbar-user">
                <div class="user-details"><strong>{{ userName }}</strong><span>{{ role }}</span></div>
                <span class="user-divider"></span>
                <button type="button" @click="logout"><i class="fa-solid fa-right-from-bracket"></i> Cerrar
                    sesión</button>
            </div>
        </header>

        <Sidebar :user="user" :screen="screen" :can-manage-catalog="canManageCatalog" :can-sell="canSell"
            :alert-count="alertCounts.total" @navigate="screen = $event" />

        <main class="main-content">
            <p v-if="error" class="alert alert-error">{{ error }}</p>
            <div v-if="showAlertBanner && alertCounts.total > 0" class="alert-banner" role="status" aria-live="polite">
                <i class="fa-solid fa-bell" aria-hidden="true"></i>
                <p>
                    Hay <strong>{{ alertCounts.total }}</strong>
                    {{ alertCounts.total === 1 ? 'producto' : 'productos' }} en alerta de existencia<template
                        v-if="alertCounts.agotado"> ({{ alertCounts.agotado }} {{ alertCounts.agotado === 1 ? 'agotado' : 'agotados' }})</template>.
                </p>
                <button type="button" class="alert-banner-action" @click="openAlerts">Ver alertas</button>
                <button type="button" class="alert-banner-close" aria-label="Cerrar aviso" @click="showAlertBanner = false">&times;</button>
            </div>
            <DashboardView v-if="screen === 'dashboard'" :role="role" :can-manage-catalog="canManageCatalog"
                :can-sell="canSell" @navigate="screen = $event" />
            <ProductListView v-else-if="screen === 'products'" />
            <CategoryListView v-else-if="screen === 'categories'" />
            <SupplierListView v-else-if="screen === 'suppliers'" />
            <InventoryEntryView v-else-if="screen === 'inventory_entry'" />
            <StockListView v-else-if="screen === 'stocks'" :can-export="canManageCatalog" />
            <AlertListView v-else-if="screen === 'alerts'" @counts-changed="alertCounts = $event" />
            <ProductSearchView v-else-if="screen === 'product_search'" />
            <SalesListView v-else-if="screen === 'sales_history'" :user="user" />
            <PosView v-else-if="screen === 'pos'" />
            <SalesSummaryView v-else-if="screen === 'sales_summary'" />
            <TopProductsView v-else-if="screen === 'top_products'" />
            <MovementHistoryView v-else-if="screen === 'movement_history'" />
            <UsersView v-else-if="screen === 'users'" />
        </main>
    </div>
</template>

<style scoped>
.alert-banner { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; padding: 12px 14px 12px 16px; border-left: 4px solid #dc2626; border-radius: 10px; color: #7f1d1d; background: #fff1f2; box-shadow: 0 2px 6px #20324d0f; font-size: 13px; }
.alert-banner > i { color: #dc2626; font-size: 18px; }
.alert-banner p { flex: 1; margin: 0; }
.alert-banner-action { padding: 8px 14px; border: 0; border-radius: 7px; color: #fff; background: #dc2626; font-size: 12px; font-weight: 700; white-space: nowrap; }
.alert-banner-action:hover { background: #b91c1c; }
.alert-banner-close { border: 0; color: #9f1239; background: transparent; font-size: 22px; line-height: 1; }
</style>
