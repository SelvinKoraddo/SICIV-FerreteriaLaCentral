<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import api from '../../services/api';

const products = ref([]);
const categories = ref([]);
const search = ref('');
const category = ref('');
const loading = ref(false);
const error = ref('');
let searchTimer;
let requestSequence = 0;

const loadCategories = async () => {
  const response = await api.get('/api/categorias');
  categories.value = response.data;
};

const searchProducts = async () => {
  const sequence = ++requestSequence;
  loading.value = true;
  error.value = '';

  try {
    const response = await api.get('/api/productos', {
      params: {
        search: search.value.trim(),
        categoria: category.value,
        activos: true,
        per_page: 100,
      },
    });
    if (sequence === requestSequence) products.value = response.data.data ?? response.data;
  } catch (exception) {
    if (sequence === requestSequence) {
      products.value = [];
      error.value = exception.response?.data?.message || 'No fue posible consultar los productos.';
    }
  } finally {
    if (sequence === requestSequence) loading.value = false;
  }
};

watch([search, category], () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(searchProducts, 350);
});

onMounted(async () => {
  try {
    await Promise.all([loadCategories(), searchProducts()]);
  } catch (exception) {
    error.value = exception.response?.data?.message || 'No fue posible cargar el catálogo.';
  }
});

onBeforeUnmount(() => clearTimeout(searchTimer));
</script>

<template>
  <section class="feature-page">
    <div class="feature-heading">
      <h2>Búsqueda Rápida de Productos</h2>
      <p>Consulta de existencias y precios en tiempo real</p>
    </div>

    <div class="search-toolbar">
      <div class="search-input-wrap">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input v-model="search" type="search"
          placeholder="Ingresa código o nombre de producto (ej: Martillo, Cemento, PROD-001)..."
          aria-label="Buscar productos">
      </div>
      <select v-model="category" aria-label="Filtrar por categoría">
        <option value="">Todas las categorías</option>
        <option v-for="item in categories" :key="item.id_categoria" :value="item.id_categoria">
          {{ item.nombre }}
        </option>
      </select>
    </div>

    <div class="search-results">
      <p v-if="error" class="feature-alert">{{ error }}</p>
      <div v-else-if="loading" class="feature-empty">
        <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
        <p>Consultando productos...</p>
      </div>
      <div v-else-if="!products.length" class="feature-empty">
        <i class="fa-solid fa-box-open" aria-hidden="true"></i>
        <p class="feature-empty-title">No se encontraron productos con ese criterio</p>
        <p>Verifica la ortografía o intenta con otra categoría.</p>
      </div>
      <div v-else class="feature-table-wrap">
        <table class="feature-table">
          <thead>
            <tr>
              <th>Código</th>
              <th>Producto</th>
              <th>Categoría</th>
              <th>Unidad</th>
              <th class="align-right">Precio</th>
              <th class="align-center">Existencia</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="product in products" :key="product.id_producto">
              <td class="product-code">{{ product.codigo }}</td>
              <td class="product-name">{{ product.nombre }}</td>
              <td>{{ product.categoria?.nombre || '—' }}</td>
              <td>{{ product.unidad_medida }}</td>
              <td class="align-right product-price">${{ Number(product.precio_venta).toFixed(2) }}</td>
              <td class="align-center">
                <span class="stock-badge" :class="{
                  'stock-badge-low': Number(product.existencia_actual) <= Number(product.existencia_minima),
                  'stock-badge-out': Number(product.existencia_actual) === 0,
                }">
                  {{ product.existencia_actual }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>

<style scoped>
.feature-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.feature-heading h2 {
  margin: 0;
  color: #172033;
  font-size: 24px;
}

.feature-heading p {
  margin: 3px 0 0;
  color: #718096;
  font-size: 12px;
}

.search-toolbar,
.search-results {
  border: 1px solid #e4e9f0;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 4px 14px #20324d08;
}

.search-toolbar {
  display: flex;
  gap: 14px;
  padding: 16px;
}

.search-input-wrap {
  position: relative;
  flex: 1;
}

.search-input-wrap i {
  position: absolute;
  top: 13px;
  left: 13px;
  color: #94a3b8;
}

.search-input-wrap input {
  padding-left: 38px;
}

.search-toolbar select {
  width: 230px;
}

.search-results {
  min-height: 300px;
  overflow: hidden;
}

.feature-table-wrap {
  overflow-x: auto;
}

.feature-table {
  width: 100%;
  border-collapse: collapse;
  color: #475569;
  font-size: 12px;
  text-align: left;
}

.feature-table th {
  padding: 13px;
  color: #475569;
  background: #f1f5f9;
  border-bottom: 1px solid #e2e8f0;
  font-size: 11px;
  text-transform: uppercase;
}

.feature-table td {
  padding: 13px;
  border-bottom: 1px solid #e2e8f0;
}

.feature-table tbody tr:last-child td {
  border-bottom: 0;
}

.product-code {
  color: #475569;
  font-family: monospace;
  font-weight: 700;
}

.product-name,
.product-price {
  color: #172033;
  font-weight: 700;
}

.align-right {
  text-align: right;
}

.align-center {
  text-align: center;
}

.stock-badge {
  display: inline-block;
  min-width: 34px;
  padding: 5px 8px;
  border-radius: 999px;
  color: #137342;
  background: #eaf8f0;
  font-weight: 700;
}

.stock-badge-low {
  color: #a85a08;
  background: #fff2df;
}

.stock-badge-out {
  color: #a42828;
  background: #fff0f0;
}

.feature-empty {
  display: grid;
  place-items: center;
  min-height: 300px;
  padding: 40px;
  color: #94a3b8;
  text-align: center;
}

.feature-empty i {
  margin-bottom: 12px;
  color: #cbd5e1;
  font-size: 36px;
}

.feature-empty p {
  margin: 3px 0;
  font-size: 12px;
}

.feature-empty-title {
  color: #475569;
  font-weight: 700;
}

.feature-alert {
  margin: 16px;
  padding: 12px;
  border-radius: 8px;
  color: #a42828;
  background: #fff0f0;
  font-size: 13px;
}

@media (max-width: 700px) {
  .search-toolbar {
    flex-direction: column;
  }

  .search-toolbar select {
    width: 100%;
  }
}
</style>
