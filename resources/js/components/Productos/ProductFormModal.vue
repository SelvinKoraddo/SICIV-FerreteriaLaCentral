<script setup>
/**
 * Formulario modal para registrar o editar un producto del catálogo.
 *
 * - Alta: el código lo asigna el sistema (RF-06) y se captura la existencia inicial.
 * - Edición: el código y la existencia actual son de solo lectura (RN-02).
 * - Los errores de validación (HTTP 422) se muestran junto a cada campo (CU-03 5a).
 *
 * @see HU-03 Registro de productos
 * @emits saved  { product, message, created } cuando el servidor confirma el guardado.
 * @emits close  cuando el usuario cancela.
 */
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import api from '../../services/api';

const props = defineProps({
  /** Producto a editar; null para registrar uno nuevo. */
  product: { type: Object, default: null },
  /** Categorías provenientes de GET /api/categorias. */
  categories: { type: Array, default: () => [] },
  /** Unidades provenientes de GET /api/productos/unidades-medida. */
  units: { type: Array, default: () => [] },
});
const emit = defineEmits(['saved', 'close']);

/** Valor máximo representable en las columnas decimal(10,2) de existencias. */
const MAX_QUANTITY = '99999999.99';

const EMPTY_FORM = Object.freeze({
  nombre: '',
  descripcion: '',
  id_categoria: '',
  unidad_medida: 'UNIDAD',
  precio_venta: '',
  existencia_inicial: '',
  existencia_minima: '',
});

const form = reactive({ ...EMPTY_FORM });
const errors = ref({});
const generalError = ref('');
const saving = ref(false);
const firstInput = ref(null);

const isEdit = computed(() => Boolean(props.product?.id_producto));
const title = computed(() => (isEdit.value ? `Editar producto ${props.product.codigo}` : 'Nuevo producto'));

/**
 * Categorías activas. En edición se incluye la categoría actual aunque esté
 * inactiva, para que el producto pueda guardarse sin reasignarla.
 */
const categoryOptions = computed(() => {
  const options = props.categories.filter((category) => Boolean(category.activa));
  const current = props.product?.categoria;

  if (isEdit.value && current && !options.some((category) => category.id_categoria === current.id_categoria)) {
    options.push({ ...current, inactiva: true });
  }
  return options;
});

/**
 * Unidades estándar. En edición, si el producto conserva una unidad registrada
 * antes de la lista estándar, se muestra marcada para forzar su reemplazo.
 */
const unitOptions = computed(() => {
  const legacyUnit = props.product?.unidad_medida;

  if (isEdit.value && legacyUnit && !props.units.some((unit) => unit.valor === legacyUnit)) {
    return [
      { valor: legacyUnit, etiqueta: `${legacyUnit} (no estándar, seleccione otra)`, permite_fracciones: true, legacy: true },
      ...props.units,
    ];
  }
  return props.units;
});

const selectedUnit = computed(() => unitOptions.value.find((unit) => unit.valor === form.unidad_medida));
const quantityStep = computed(() => (selectedUnit.value?.permite_fracciones ? '0.01' : '1'));
const quantityHint = computed(() => {
  if (selectedUnit.value?.legacy) return 'Esta unidad ya no es válida: elige una de la lista para poder guardar.';
  if (selectedUnit.value?.permite_fracciones) return `La unidad ${selectedUnit.value.etiqueta} admite decimales.`;
  return 'Esta unidad solo admite cantidades enteras.';
});

/** Carga en el formulario los datos del producto, o lo deja vacío para un alta. */
const fillForm = () => {
  errors.value = {};
  generalError.value = '';

  if (!isEdit.value) {
    Object.assign(form, EMPTY_FORM);
    return;
  }

  Object.assign(form, {
    nombre: props.product.nombre ?? '',
    descripcion: props.product.descripcion ?? '',
    id_categoria: props.product.id_categoria ?? '',
    unidad_medida: props.product.unidad_medida ?? EMPTY_FORM.unidad_medida,
    precio_venta: props.product.precio_venta ?? '',
    existencia_inicial: '',
    existencia_minima: props.product.existencia_minima ?? '',
  });
};

/** Elimina el error de un campo cuando el usuario lo corrige. */
const clearError = (field) => {
  if (!errors.value[field]) return;
  const { [field]: _removed, ...rest } = errors.value;
  errors.value = rest;
};

/** Construye el cuerpo de la petición; la existencia inicial solo se envía en el alta. */
const buildPayload = () => {
  const payload = {
    nombre: form.nombre.trim(),
    descripcion: form.descripcion.trim() || null,
    id_categoria: form.id_categoria,
    unidad_medida: form.unidad_medida,
    precio_venta: form.precio_venta,
    existencia_minima: form.existencia_minima,
  };

  if (!isEdit.value) payload.existencia_inicial = form.existencia_inicial === '' ? 0 : form.existencia_inicial;
  return payload;
};

/** Traduce la respuesta de error del servidor a mensajes para el usuario. */
const handleError = (exception) => {
  const { status, data } = exception.response ?? {};

  if (status === 422) {
    errors.value = Object.fromEntries(
      Object.entries(data?.errors ?? {}).map(([field, messages]) => [field, messages[0]]),
    );
    generalError.value = 'Revisa los campos marcados.';
  } else if (status === 403) {
    generalError.value = 'No tienes permisos para gestionar el catálogo de productos.';
  } else if (status === 401 || status === 419) {
    generalError.value = 'Tu sesión expiró. Vuelve a iniciar sesión.';
  } else {
    generalError.value = data?.message || 'No fue posible guardar el producto. Intenta de nuevo.';
  }
};

const submit = async () => {
  if (saving.value) return;

  saving.value = true;
  errors.value = {};
  generalError.value = '';

  try {
    const request = isEdit.value
      ? api.put(`/api/productos/${props.product.id_producto}`, buildPayload())
      : api.post('/api/productos', buildPayload());
    const { data } = await request;

    emit('saved', { product: data.producto, message: data.message, created: !isEdit.value });
  } catch (exception) {
    handleError(exception);
  } finally {
    saving.value = false;
  }
};

const close = () => {
  if (!saving.value) emit('close');
};

const onKeydown = (event) => {
  if (event.key === 'Escape') close();
};

watch(() => props.product, fillForm, { immediate: true });

/** Elemento que tenía el foco al abrir el diálogo; lo recupera al cerrarse (accesibilidad). */
let previousFocus = null;

onMounted(() => {
  previousFocus = document.activeElement;
  document.addEventListener('keydown', onKeydown);
  firstInput.value?.focus();
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
  previousFocus?.focus?.();
});
</script>

<template>
  <!-- El diálogo no se cierra al hacer clic en el fondo para no descartar datos por accidente. -->
  <div class="sale-detail-modal" role="dialog" aria-modal="true" :aria-label="title">
    <div class="sale-detail-box product-form-box">
      <header class="sale-detail-header">
        <h2>{{ title.toUpperCase() }}</h2>
        <button type="button" class="modal-close" aria-label="Cerrar" :disabled="saving" @click="close">&times;</button>
      </header>

      <form class="product-form" novalidate @submit.prevent="submit">
        <p v-if="generalError" class="feature-alert" role="alert">{{ generalError }}</p>
        <p v-if="isEdit && !product.activo" class="form-notice">
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
          Este producto está inactivo. Puedes editar sus datos; para reactivarlo usa «Activar» en la tabla.
        </p>

        <div class="form-grid">
          <label class="form-field form-field-full">
            <span>Código</span>
            <input v-if="isEdit" :value="product.codigo" type="text" readonly class="readonly">
            <input v-else value="Se generará automáticamente al guardar" type="text" readonly
              class="readonly muted-input" tabindex="-1">
            <small v-if="!isEdit" class="form-hint">El sistema asigna un código único e inmutable (formato PRD-000001).</small>
          </label>

          <label class="form-field form-field-full" :class="{ invalid: errors.nombre }">
            <span>Nombre <b aria-hidden="true">*</b></span>
            <input ref="firstInput" v-model="form.nombre" type="text" maxlength="100" required
              placeholder="Ej: Martillo de uña 16 oz" :aria-invalid="Boolean(errors.nombre)" @input="clearError('nombre')">
            <small v-if="errors.nombre" class="form-error">{{ errors.nombre }}</small>
          </label>

          <label class="form-field form-field-full" :class="{ invalid: errors.descripcion }">
            <span>Descripción</span>
            <textarea v-model="form.descripcion" rows="2" maxlength="1000"
              placeholder="Detalles opcionales del producto" @input="clearError('descripcion')"></textarea>
            <small v-if="errors.descripcion" class="form-error">{{ errors.descripcion }}</small>
          </label>

          <label class="form-field" :class="{ invalid: errors.id_categoria }">
            <span>Categoría <b aria-hidden="true">*</b></span>
            <select v-model="form.id_categoria" required :aria-invalid="Boolean(errors.id_categoria)"
              @change="clearError('id_categoria')">
              <option value="" disabled>Selecciona una categoría</option>
              <option v-for="category in categoryOptions" :key="category.id_categoria" :value="category.id_categoria">
                {{ category.nombre }}{{ category.inactiva ? ' (inactiva)' : '' }}
              </option>
            </select>
            <small v-if="errors.id_categoria" class="form-error">{{ errors.id_categoria }}</small>
            <small v-else-if="!categoryOptions.length" class="form-error">
              No hay categorías activas. Registra una antes de crear productos.
            </small>
          </label>

          <label class="form-field" :class="{ invalid: errors.unidad_medida }">
            <span>Unidad de medida <b aria-hidden="true">*</b></span>
            <select v-model="form.unidad_medida" required :aria-invalid="Boolean(errors.unidad_medida)"
              @change="clearError('unidad_medida')">
              <option v-for="unit in unitOptions" :key="unit.valor" :value="unit.valor">{{ unit.etiqueta }}</option>
            </select>
            <small v-if="errors.unidad_medida" class="form-error">{{ errors.unidad_medida }}</small>
            <small v-else class="form-hint">{{ quantityHint }}</small>
          </label>

          <label class="form-field" :class="{ invalid: errors.precio_venta }">
            <span>Precio de venta (USD) <b aria-hidden="true">*</b></span>
            <input v-model="form.precio_venta" type="number" min="0.01" max="999999.99" step="0.01" inputmode="decimal" required
              placeholder="0.00" :aria-invalid="Boolean(errors.precio_venta)" @input="clearError('precio_venta')">
            <small v-if="errors.precio_venta" class="form-error">{{ errors.precio_venta }}</small>
          </label>

          <label v-if="isEdit" class="form-field">
            <span>Existencia actual</span>
            <input :value="product.existencia_actual" type="text" readonly class="readonly">
            <small class="form-hint">Solo cambia mediante entradas y ventas (RN-02).</small>
          </label>
          <label v-else class="form-field" :class="{ invalid: errors.existencia_inicial }">
            <span>Existencia inicial</span>
            <input v-model="form.existencia_inicial" type="number" min="0" :max="MAX_QUANTITY" :step="quantityStep" inputmode="decimal"
              placeholder="0" :aria-invalid="Boolean(errors.existencia_inicial)" @input="clearError('existencia_inicial')">
            <small v-if="errors.existencia_inicial" class="form-error">{{ errors.existencia_inicial }}</small>
            <small v-else class="form-hint">Si es mayor que 0 se registra el movimiento de apertura.</small>
          </label>

          <label class="form-field" :class="{ invalid: errors.existencia_minima }">
            <span>Existencia mínima <b aria-hidden="true">*</b></span>
            <input v-model="form.existencia_minima" type="number" min="0" :max="MAX_QUANTITY" :step="quantityStep" inputmode="decimal"
              required placeholder="0" :aria-invalid="Boolean(errors.existencia_minima)"
              @input="clearError('existencia_minima')">
            <small v-if="errors.existencia_minima" class="form-error">{{ errors.existencia_minima }}</small>
            <small v-else class="form-hint">Nivel que activa la alerta de reposición.</small>
          </label>
        </div>

        <footer class="form-actions">
          <button type="button" class="button button-light" :disabled="saving" @click="close">Cancelar</button>
          <button type="submit" class="button button-primary" :disabled="saving || !categoryOptions.length">
            <i v-if="saving" class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
            {{ saving ? 'Guardando...' : (isEdit ? 'Guardar cambios' : 'Registrar producto') }}
          </button>
        </footer>
      </form>
    </div>
  </div>
</template>

<style scoped>
.product-form-box { width: min(640px, 100%); max-height: calc(100vh - 40px); overflow-y: auto; }
.product-form { display: grid; gap: 16px; padding: 22px 24px 24px; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 16px; }
.form-field { display: grid; gap: 5px; color: #334155; font-size: 12px; }
.form-field-full { grid-column: 1 / -1; }
.form-field > span { color: #3d536d; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.form-field b { color: #dc2626; }
.form-field input,
.form-field select,
.form-field textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  color: #172033;
  background: #fff;
  font: inherit;
  font-size: 13px;
}
.form-field textarea { min-height: 56px; resize: vertical; }
.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus { border-color: #f28c28; outline: 2px solid #f28c28; outline-offset: 1px; }
.form-field.invalid input,
.form-field.invalid select,
.form-field.invalid textarea { border-color: #dc2626; background: #fff7f7; }
.readonly { color: #475569 !important; background: #f1f4f8 !important; font-family: 'Courier New', monospace; font-weight: 700; letter-spacing: .5px; }
.muted-input { color: #64748b !important; font-family: inherit; font-style: italic; font-weight: 500; letter-spacing: 0; }
.form-hint { color: #718096; font-size: 11px; }
.form-notice { display: flex; align-items: center; gap: 8px; margin: 0; padding: 10px 12px; border-radius: 8px; color: #8a4b08; background: #fff4e5; font-size: 12px; }
.form-error { color: #b42318; font-size: 11px; font-weight: 600; }
.form-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 6px; border-top: 1px solid #e2e8f0; }
.form-actions .button { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; }
.form-actions .button:disabled { cursor: not-allowed; opacity: .6; }

@media (max-width: 560px) {
  .form-grid { grid-template-columns: 1fr; }
}
</style>
