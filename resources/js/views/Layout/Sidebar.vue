<script setup>
import { computed } from 'vue';

const props = defineProps({
  user: Object,
  screen: String,
  canManageCatalog: Boolean,
  canSell: Boolean,
  /** Número de alertas de existencia activas (HU-08 CA-3). */
  alertCount: { type: Number, default: 0 },
});
const emit = defineEmits(['navigate']);
const menuGroups = computed(() => [
  {
    label: 'Catálogo',
    items: [
      { id: 'product_search', label: 'Buscar Productos', icon: 'fa-magnifying-glass', show: true },
      { id: 'products', label: 'Productos', icon: 'fa-boxes-stacked', show: props.canManageCatalog },
      { id: 'suppliers', label: 'Proveedores', icon: 'fa-truck-field', show: props.canManageCatalog },
      { id: 'categories', label: 'Categorías', icon: 'fa-tags', show: props.canManageCatalog },
    ],
  },
  {
    label: 'Inventario',
    items: [
      { id: 'stocks', label: 'Existencias', icon: 'fa-warehouse', show: true },
      { id: 'alerts', label: 'Alertas', icon: 'fa-bell', show: true, badge: props.alertCount },
      { id: 'inventory_entry', label: 'Entrada de inventario', icon: 'fa-file-circle-plus', show: props.canManageCatalog },
      { id: 'movement_history', label: 'Historial de movimientos', icon: 'fa-clock-rotate-left', show: true },
    ],
  },
  {
    label: 'Ventas',
    items: [
      { id: 'pos', label: 'Registrar Venta', icon: 'fa-cart-shopping', show: props.canSell },
      { id: 'sales_history', label: props.user?.rol === 'VENDEDOR' ? 'Mis Ventas' : 'Ventas', icon: 'fa-receipt', show: true },
    ],
  },
  {
    label: 'Reportes',
    items: [
      { id: 'sales_summary', label: 'Resumen ventas', icon: 'fa-chart-pie', show: true },
      { id: 'top_products', label: 'Mayor movimiento', icon: 'fa-chart-bar', show: props.user?.rol === 'ADMINISTRADOR' },
    ],
  },
  {
    label: 'Administración',
    items: [
      { id: 'users', label: 'Usuarios', icon: 'fa-users', show: props.user?.rol === 'ADMINISTRADOR' },
    ],
  },
]);
</script>

<template>
  <aside class="sidebar">
    <div class="sidebar-role">ROL ACTIVO: <strong>{{ user?.rol }}</strong></div>
    <nav aria-label="Navegación principal">
      <button type="button" class="sidebar-home" data-label="Inicio" title="Inicio" :class="{ active: screen === 'dashboard' }"
        @click="emit('navigate', 'dashboard')">
        <i class="menu-icon fa-solid fa-house" aria-hidden="true"></i>
        <span>Inicio</span>
      </button>
      <template v-for="group in menuGroups" :key="group.label">
        <h2 v-if="group.items.some((item) => item.show)" class="menu-section-title">{{ group.label }}</h2>
        <template v-for="item in group.items.filter((entry) => entry.show)" :key="item.id">
          <button type="button" :data-label="item.label" :title="item.label" :class="{ active: screen === item.id }"
            @click="emit('navigate', item.id)">
            <i class="menu-icon fa-solid" :class="item.icon" aria-hidden="true"></i>
            <span>{{ item.label }}</span>
            <b v-if="item.badge" class="menu-badge" :aria-label="`${item.badge} alertas activas`">
              {{ item.badge > 99 ? '99+' : item.badge }}
            </b>
          </button>
        </template>
      </template>
    </nav>
    <div class="sidebar-footer">v1.0.0</div>
  </aside>
</template>

<style scoped>
.sidebar nav button { position: relative; }
.menu-section-title { margin: 12px 13px 2px; color: #7187a5; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.sidebar-home { margin-bottom: 4px; }
.menu-badge { display: inline-grid; min-width: 20px; height: 20px; margin-left: auto; padding: 0 6px; place-items: center; border-radius: 999px; color: #fff; background: #dc2626; font-size: 11px; font-weight: 800; line-height: 1; }

@media (max-width: 980px) {
  .menu-section-title { overflow: hidden; height: 12px; margin: 10px 0 0; font-size: 0; }
  .menu-badge { position: absolute; top: 4px; right: 4px; min-width: 16px; height: 16px; padding: 0 4px; font-size: 10px; }
}

@media (max-width: 700px) {
  .sidebar nav button[data-label]::after {
    position: absolute;
    top: 50%;
    left: calc(100% + 10px);
    z-index: 20;
    padding: 7px 10px;
    border-radius: 6px;
    color: #fff;
    background: #203d62;
    box-shadow: 0 4px 12px #02081755;
    content: attr(data-label);
    font-size: 12px;
    font-weight: 600;
    line-height: 1.2;
    opacity: 0;
    pointer-events: none;
    transform: translate(-4px, -50%);
    transition: opacity .15s ease, transform .15s ease;
    white-space: nowrap;
  }

  .sidebar nav button[data-label]:hover::after,
  .sidebar nav button[data-label]:focus-visible::after {
    opacity: 1;
    transform: translate(0, -50%);
  }
}
</style>
