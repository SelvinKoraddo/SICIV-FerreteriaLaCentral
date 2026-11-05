<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../services/api';

const products = ref([]);
const selectedId = ref('');
const selectedQuantity = ref(1);
const cart = ref([]);
const loading = ref(false);
const submitting = ref(false);
const error = ref('');
const toast = ref('');
const receipt = ref(null);
let toastTimer;

const selectedProduct = computed(() =>
  products.value.find((product) => String(product.id_producto) === String(selectedId.value)),
);

const total = computed(() =>
  cart.value.reduce((sum, item) => sum + (Number(item.cantidad) || 0) * item.precio_unitario, 0),
);

const hasInvalidQuantity = computed(() =>
  cart.value.some((item) =>
    !Number.isFinite(Number(item.cantidad))
    || Number(item.cantidad) <= 0
    || Number(item.cantidad) > Number(item.existencia_actual),
  ),
);

const showToast = (message) => {
  toast.value = message;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    toast.value = '';
  }, 4000);
};

const loadProducts = async () => {
  loading.value = true;
  error.value = '';

  try {
    const response = await api.get('/api/productos', { params: { activos: true, per_page: 100 } });
    products.value = response.data.data ?? response.data;
  } catch (exception) {
    error.value = exception.response?.data?.message || 'No fue posible cargar el inventario.';
  } finally {
    loading.value = false;
  }
};

const addToCart = () => {
  const product = selectedProduct.value;
  const quantity = Number(selectedQuantity.value);

  if (!product || !Number.isFinite(quantity) || quantity <= 0) {
    showToast('Selecciona un producto e ingresa una cantidad válida.');
    return;
  }

  const existing = cart.value.find((item) => item.id_producto === product.id_producto);
  const nextQuantity = Number(existing?.cantidad ?? 0) + quantity;

  if (nextQuantity > Number(product.existencia_actual)) {
    showToast('La cantidad solicitada supera la existencia disponible.');
    return;
  }

  if (existing) {
    existing.cantidad = nextQuantity;
  } else {
    cart.value.push({
      id_producto: product.id_producto,
      codigo: product.codigo,
      nombre: product.nombre,
      unidad_medida: product.unidad_medida,
      cantidad: quantity,
      precio_unitario: Number(product.precio_venta),
      existencia_actual: Number(product.existencia_actual),
    });
  }

  selectedId.value = '';
  selectedQuantity.value = 1;
};

const removeFromCart = (id) => {
  cart.value = cart.value.filter((item) => item.id_producto !== id);
};

const updateQuantity = (item, value) => {
  item.cantidad = value;
  if (value !== '' && Number(value) > Number(item.existencia_actual)) {
    showToast(`La existencia máxima de ${item.nombre} es ${item.existencia_actual}.`);
  }
};

const cancelSale = () => {
  if (!cart.value.length || window.confirm('¿Deseas cancelar la venta actual?')) {
    cart.value = [];
    selectedId.value = '';
    selectedQuantity.value = 1;
    receipt.value = null;
    error.value = '';
  }
};

const confirmSale = async () => {
  if (!cart.value.length || hasInvalidQuantity.value) {
    showToast('Corrige las cantidades antes de confirmar la venta.');
    return;
  }

  submitting.value = true;
  error.value = '';

  try {
    const response = await api.post('/api/ventas', {
      fecha: new Date().toISOString(),
      detalles: cart.value.map((item) => ({
        id_producto: item.id_producto,
        cantidad: Number(item.cantidad),
      })),
    });

    receipt.value = response.data;
    cart.value = [];
    selectedId.value = '';
    selectedQuantity.value = 1;
    await loadProducts();
  } catch (exception) {
    error.value = exception.response?.data?.message
      || Object.values(exception.response?.data?.errors ?? {}).flat().join(' ')
      || 'No fue posible registrar la venta.';
  } finally {
    submitting.value = false;
  }
};

onMounted(loadProducts);
</script>

<template>
  <section class="pos-page">
    <div class="pos-heading">
      <h2>Registrar Venta</h2>
      <p>Procesamiento inmediato de salida por venta</p>
    </div>

    <p v-if="error" class="pos-alert">{{ error }}</p>
    <div v-if="toast" class="pos-toast">
      <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
      {{ toast }}
    </div>
    <div v-if="receipt" class="receipt">
      <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
      <div>
        <strong>Venta registrada correctamente</strong>
        <span>Comprobante: {{ receipt.numero }}</span>
      </div>
      <button type="button" aria-label="Cerrar comprobante" @click="receipt = null">&times;</button>
    </div>

    <div class="pos-grid">
      <div class="pos-left">
        <div class="pos-card product-selector">
          <label for="pos-product">Seleccionar producto</label>
          <select id="pos-product" v-model="selectedId" :disabled="loading">
            <option value="">
              {{ loading ? 'Cargando inventario...' : '-- Elige un producto del inventario --' }}
            </option>
            <option v-for="product in products" :key="product.id_producto" :value="product.id_producto">
              {{ product.codigo }} - {{ product.nombre }} (Stock: {{ product.existencia_actual }})
            </option>
          </select>
        </div>

        <div class="pos-card product-detail">
          <template v-if="selectedProduct">
            <div class="product-detail-header">
              <div>
                <span class="product-code">{{ selectedProduct.codigo }}</span>
                <h3>{{ selectedProduct.nombre }}</h3>
                <p class="product-subtitle">
                  {{ selectedProduct.categoria?.nombre || 'Producto de inventario' }}
                  <span>•</span>
                  Unidad: {{ selectedProduct.unidad_medida }}
                </p>
              </div>
              <span class="stock-status">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                Correcto ({{ selectedProduct.existencia_actual }})
              </span>
            </div>
            <div class="product-detail-data">
              <div>
                <span>Precio unitario</span>
                <strong>${{ Number(selectedProduct.precio_venta).toFixed(2) }}</strong>
              </div>
              <div>
                <span>Existencia disponible</span>
                <strong>{{ selectedProduct.existencia_actual }} {{ selectedProduct.unidad_medida }}</strong>
              </div>
            </div>
            <div class="add-product-row">
              <label for="pos-quantity">Cantidad a vender</label>
              <input id="pos-quantity" v-model="selectedQuantity" type="number" min="0.01" step="0.01"
                :max="selectedProduct.existencia_actual">
              <button type="button" class="pos-button detail-button" @click="addToCart">
                <i class="fa-solid fa-cart-plus" aria-hidden="true"></i>
                Agregar al Detalle
              </button>
            </div>
          </template>
          <div v-else class="product-placeholder">
            <i class="fa-solid fa-hand-pointer" aria-hidden="true"></i>
            <p>Selecciona un producto arriba para ver su disponibilidad</p>
          </div>
        </div>
      </div>

      <div class="pos-card sale-detail">
        <div class="sale-detail-heading">
          <h3>Detalle de la venta</h3>
          <span>{{ cart.length }} ítems</span>
        </div>

        <div class="cart-items">
          <div v-if="!cart.length" class="product-placeholder">
            <i class="fa-solid fa-cart-flatbed" aria-hidden="true"></i>
            <p>El carrito de venta está vacío</p>
          </div>
          <div v-for="item in cart" :key="item.id_producto" class="cart-item">
            <div class="cart-item-info">
              <strong>{{ item.nombre }}</strong>
              <small>{{ item.cantidad }} x ${{ item.precio_unitario.toFixed(2) }}</small>
            </div>
            <input :value="item.cantidad" type="number" min="0" step="0.01" :max="item.existencia_actual"
              :aria-invalid="!Number(item.cantidad) || Number(item.cantidad) > item.existencia_actual"
              :class="{ 'quantity-invalid': Number(item.cantidad) <= 0 || Number(item.cantidad) > item.existencia_actual }"
              @input="updateQuantity(item, $event.target.value)">
            <strong>${{ (item.cantidad * item.precio_unitario).toFixed(2) }}</strong>
            <button type="button" class="remove-item" aria-label="Eliminar producto"
              @click="removeFromCart(item.id_producto)">
              <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <div class="sale-footer">
          <div class="sale-total">
            <span>Total a pagar</span>
            <strong>${{ total.toFixed(2) }}</strong>
          </div>
          <div class="sale-actions">
            <button type="button" class="pos-button secondary" :disabled="!cart.length" @click="cancelSale">
              Cancelar
            </button>
            <button type="button" class="pos-button primary"
              :disabled="!cart.length || hasInvalidQuantity || submitting" @click="confirmSale">
              {{ submitting ? 'Procesando...' : 'Confirmar Venta' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.pos-page {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.pos-heading h2 {
  margin: 0;
  color: #111827;
  font-size: 21px;
  font-weight: 800;
}

.pos-heading p {
  margin: 2px 0 0;
  color: #718096;
  font-size: 12px;
}

.pos-grid {
  display: grid;
  gap: 20px;
  grid-template-columns: minmax(0, 1.5fr) minmax(360px, 1fr);
  min-height: calc(100vh - 182px);
}

.pos-left {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.pos-card {
  border: 1px solid #dce4ee;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 2px 3px #20324d08;
}

.product-selector {
  padding: 15px;
}

.pos-card label {
  display: block;
  margin-bottom: 9px;
  color: #172033;
  font-size: 11px;
  text-transform: uppercase;
}

.product-detail {
  display: flex;
  flex: 1;
  flex-direction: column;
  justify-content: flex-start;
  padding: 18px;
}

.product-detail-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 0 0 13px;
}

.product-detail-header h3 {
  margin: 5px 0 2px;
  color: #172033;
  font-size: 17px;
}

.product-subtitle {
  margin: 0;
  color: #718096;
  font-size: 12px;
}

.product-subtitle span {
  margin: 0 3px;
}

.stock-status {
  flex: 0 0 auto;
  padding: 5px 10px;
  border-radius: 999px;
  color: #13834d;
  background: #d7f8e9;
  font-size: 11px;
  font-weight: 700;
}

.stock-status i {
  margin-right: 3px;
}

.product-code {
  color: #f28c28;
  font-family: monospace;
  font-size: 11px;
  font-weight: 700;
}

.product-detail-data {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  margin: 0 0 15px;
  padding: 13px 14px;
  border: 1px solid #e1e7ef;
  border-radius: 11px;
  background: #f8fafc;
  color: #718096;
  font-size: 10px;
  text-transform: uppercase;
}

.product-detail-data>div:last-child {
  text-align: right;
}

.product-detail-data span,
.product-detail-data strong {
  display: block;
}

.product-detail-data strong {
  margin-top: 3px;
  color: #111827;
  font-size: 20px;
  text-transform: none;
}

.add-product-row {
  display: grid;
  align-content: start;
  gap: 8px;
  grid-template-columns: 1fr;
  margin-top: auto;
}

.add-product-row label {
  margin: 0;
}

.add-product-row input {
  min-height: 38px;
}

.pos-button {
  border: 0;
  border-radius: 8px;
  min-height: 36px;
  padding: 9px 16px;
  font-weight: 700;
  transition: background .2s, opacity .2s;
}

.pos-button.detail-button {
  width: 100%;
  margin-top: auto;
  color: #fff;
  background: #203d62;
}

.pos-button.detail-button:hover:not(:disabled) {
  background: #162f50;
}

.pos-button.primary {
  color: #fff;
  background: #f28c28;
}

.pos-button.primary:hover:not(:disabled) {
  background: #e07b1a;
}

.pos-button.secondary {
  color: #64748b;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.pos-button:disabled {
  cursor: not-allowed;
  opacity: .5;
}

.product-placeholder {
  display: grid;
  place-items: center;
  color: #94a3b8;
  text-align: center;
}

.product-placeholder i {
  margin-bottom: 12px;
  color: #9fb1ca;
  font-size: 32px;
}

.product-placeholder p {
  margin: 0;
  font-size: 12px;
}

.sale-detail {
  display: flex;
  flex-direction: column;
  padding: 18px;
}

.sale-detail-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 11px;
  border-bottom: 1px solid #e2e8f0;
}

.sale-detail-heading h3 {
  margin: 0;
  color: #334155;
  font-size: 12px;
  text-transform: uppercase;
}

.sale-detail-heading span {
  padding: 5px 9px;
  border-radius: 4px;
  color: #64748b;
  background: #f1f5f9;
  font-family: monospace;
  font-size: 11px;
}

.cart-items {
  flex: 1;
  max-height: 390px;
  overflow-y: auto;
}

.cart-items>.product-placeholder {
  height: 100%;
  min-height: 250px;
}

.cart-item {
  display: grid;
  align-items: center;
  gap: 8px;
  grid-template-columns: minmax(0, 1fr) 58px 76px 22px;
  margin-top: 10px;
  padding: 10px;
  border: 1px solid #e2e8f0;
  border-radius: 7px;
  background: #f8fafc;
}

.cart-item-info {
  min-width: 0;
}

.cart-item-info strong,
.cart-item-info small {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cart-item-info strong {
  color: #172033;
  font-size: 12px;
}

.cart-item-info small {
  margin-top: 3px;
  color: #718096;
  font-size: 10px;
}

.cart-item input {
  padding: 7px;
  text-align: center;
}

.cart-item>strong {
  color: #172033;
  font-size: 12px;
  text-align: right;
}

.quantity-invalid {
  border-color: #dc2626;
  background: #fff5f5;
}

.cart-item input[aria-invalid="true"]:focus {
  border-color: #dc2626;
  box-shadow: 0 0 0 3px #dc262622;
}

.remove-item {
  border: 0;
  color: #94a3b8;
  background: transparent;
}

.remove-item:hover {
  color: #dc2626;
}

.sale-footer {
  margin-top: auto;
  padding-top: 14px;
}

.sale-total {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 0;
  border-top: 1px solid #e2e8f0;
}

.sale-total span {
  color: #475569;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
}

.sale-total strong {
  color: #1f3a5f;
  font-size: 28px;
}

.sale-actions {
  display: grid;
  gap: 10px;
  grid-template-columns: 1fr 1.5fr;
}

.pos-alert,
.receipt {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 8px;
  font-size: 13px;
}

.pos-alert {
  margin: 0;
  color: #a42828;
  background: #fff0f0;
}

.pos-toast {
  position: fixed;
  top: 72px;
  right: 24px;
  z-index: 11;
  display: flex;
  gap: 10px;
  align-items: center;
  max-width: 360px;
  padding: 13px 16px;
  border-left: 4px solid #dc2626;
  border-radius: 8px;
  color: #991b1b;
  background: #fff1f2;
  box-shadow: 0 8px 26px #17203326;
  font-size: 13px;
  font-weight: 700;
}

@media (max-width: 640px) {
  .pos-toast {
    top: 68px;
    right: 16px;
    left: 16px;
    max-width: none;
  }
}

.receipt {
  justify-content: space-between;
  color: #137342;
  background: #eaf8f0;
}

.receipt i {
  font-size: 19px;
}

.receipt div {
  display: grid;
  flex: 1;
  gap: 2px;
}

.receipt span {
  font-size: 12px;
}

.receipt button {
  border: 0;
  color: #137342;
  background: transparent;
  font-size: 20px;
}

@media (max-width: 980px) {
  .pos-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 520px) {
  .add-product-row {
    grid-template-columns: 1fr;
  }

  .sale-actions {
    grid-template-columns: 1fr;
  }
}
</style>
