<template><div class="space-y-6"><div><h1 class="text-xl font-bold text-slate-900">Registrar Entrada de Inventario</h1><p class="text-xs text-slate-500">Recepción de mercadería de proveedores</p></div><div v-if="error" role="alert" class="rounded-lg bg-red-50 border border-red-200 text-red-700 p-3 text-xs">{{error}}</div><div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm grid grid-cols-3 gap-4"><label>Proveedor Activo<select v-model="supplier"><option value="">Seleccione un proveedor</option><option v-for="s in suppliers" :key="s.id_proveedor" :value="s.id_proveedor">{{s.nombre}}</option></select></label><label>Fecha de Recepción<input v-model="date" type="date"></label><label>Documento de Referencia<input v-model="doc" placeholder="Ej: FAC-99082"></label></div><div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3"><h3 class="text-xs font-bold uppercase text-slate-600">Agregar Producto a la Entrada</h3><div class="grid grid-cols-12 gap-3 items-end"><label class="col-span-5 normal">Producto<select v-model="line.code"><option v-for="p in products" :key="p.id_producto" :value="p.id_producto">{{p.codigo}} - {{p.nombre}}</option></select></label><label class="col-span-2 normal">Cantidad<input v-model.number="line.qty" type="number" :min="quantityStep(line.code)" :step="quantityStep(line.code)"></label><label class="col-span-3 normal">Costo Unitario ($)<input v-model.number="line.cost" type="number" min="0" step="0.01"></label><button :disabled="submitting" @click="add" class="col-span-2 w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold">+ Agregar Línea</button></div></div><div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4"><table class="w-full text-left"><thead><tr class="bg-slate-100 text-slate-600 text-xs uppercase border-b"><th class="p-3">Código</th><th class="p-3">Producto</th><th class="p-3 text-center">Cantidad</th><th class="p-3 text-right">Costo U.</th><th class="p-3 text-right">Subtotal</th><th class="p-3 text-center">Acciones</th></tr></thead><tbody class="text-xs"><tr v-for="i in cart" :key="i.code"><td class="p-3 font-mono">{{product(i.code).codigo}}</td><td class="p-3 font-semibold">{{product(i.code).nombre}}</td><td class="p-3 text-center"><input v-model="i.qty" type="number" :min="quantityStep(i.code)" :step="quantityStep(i.code)" :disabled="submitting" :aria-label="`Cantidad de ${product(i.code).nombre}`" :aria-invalid="Boolean(quantityError(i.code, i.qty))" @input="error = ''" class="text-center"><p v-if="quantityError(i.code, i.qty)" class="mt-1 text-red-700">{{quantityError(i.code, i.qty)}}</p></td><td class="p-3 text-right">{{money(i.cost)}}</td><td class="p-3 text-right font-bold">{{money(i.qty*i.cost)}}</td><td class="p-3 text-center"><button type="button" :disabled="submitting" @click="remove(i.code)" :aria-label="`Eliminar ${product(i.code).nombre}`" class="text-red-700 hover:text-red-900 disabled:opacity-50"><i class="fa-solid fa-trash" aria-hidden="true"></i> Eliminar</button></td></tr><tr v-if="!cart.length"><td colspan="6" class="p-6 text-center text-slate-400">Sin ítems agregados a esta entrada.</td></tr></tbody></table><div class="flex justify-between items-center pt-4 border-t"><span class="text-sm font-bold">TOTAL ENTRADA: {{money(total)}}</span><div class="flex gap-3"><button type="button" @click="cancel" :disabled="submitting" class="px-6 py-2.5 border border-slate-300 text-slate-600 rounded-lg text-xs font-bold disabled:opacity-50">Cancelar entrada</button><button @click="confirm" :disabled="!cart.length || submitting || hasInvalidQuantity" class="px-6 py-2.5 bg-[#F28C28] text-white rounded-lg text-xs font-bold disabled:opacity-50">{{ submitting ? 'Registrando...' : 'Confirmar Entrada' }}</button></div></div></div><div v-if="modal" class="modal"><div class="modal-card max-w-lg"><div class="modal-head"><h3>ENTRADA REGISTRADA</h3><button @click="modal=false"><i class="fa-solid fa-xmark text-lg"></i></button></div><div class="p-6 text-center space-y-3"><div class="inline-flex p-3 bg-emerald-100 text-emerald-600 rounded-full"><i class="fa-solid fa-truck-ramp-box text-3xl"></i></div><h3 class="font-bold">Entrada procesada con Correlativo: <span class="font-mono text-[#1F3A5F]">{{correlativo}}</span></h3><p class="text-xs text-slate-600">El stock de los productos ha sido incrementado debidamente en el sistema.</p><button @click="modal=false" class="w-full py-2 bg-[#1F3A5F] text-white text-xs rounded-lg">Aceptar</button></div></div></div></div></template>
<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../../services/api';

const today = () => new Date().toLocaleDateString('sv-SE', { timeZone: 'America/El_Salvador' });
const suppliers = ref([]), products = ref([]), units = ref([]);
const supplier = ref(''), date = ref(today()), doc = ref('');
const modal = ref(false), correlativo = ref(''), error = ref(''), submitting = ref(false);
const line = reactive({ code: '', qty: 10, cost: 5 });
const cart = ref([]);
const product = id => products.value.find(p => String(p.id_producto) === String(id)) || {};
const total = computed(() => cart.value.reduce((sum, item) => sum + (Number(item.qty) || 0) * Number(item.cost), 0));
const money = value => '$' + (Number(value) || 0).toFixed(2);
const unit = id => units.value.find(u => u.valor === product(id).unidad_medida);
const quantityStep = id => unit(id)?.permite_fracciones === false ? '1' : '0.01';

// Revisa la cantidad sin quitar los demás productos de la entrada.
function quantityError(id, value) {
  const quantity = Number(value);
  if (value === '' || !Number.isFinite(quantity) || quantity <= 0) return 'La cantidad debe ser mayor que cero.';
  if (!/^\d+(?:\.\d{1,2})?$/.test(String(value))) return 'La cantidad debe tener como máximo 2 decimales.';
  const selectedUnit = unit(id);
  if (selectedUnit?.permite_fracciones === false && !Number.isInteger(quantity)) {
    return `La unidad del producto '${selectedUnit.etiqueta}' no admite cantidades fraccionarias.`;
  }
  return '';
}
const hasInvalidQuantity = computed(() => cart.value.some(item => quantityError(item.code, item.qty)));

async function load() {
  try {
    const [catalogs, measures] = await Promise.all([
      api.get('/api/entradas/catalogos'),
      api.get('/api/productos/unidades-medida'),
    ]);
    suppliers.value = catalogs.data.proveedores || [];
    products.value = catalogs.data.productos || [];
    units.value = measures.data;
    if (suppliers.value.length && !supplier.value) supplier.value = suppliers.value[0].id_proveedor;
    if (products.value.length && !line.code) line.code = products.value[0].id_producto;
  } catch (e) {
    error.value = e.response?.data?.message || 'No fue posible cargar los datos.';
  }
}

function add() {
  if (submitting.value) return;
  error.value = quantityError(line.code, line.qty);
  if (error.value) return;
  if (!line.code || !Number.isFinite(Number(line.cost)) || line.cost === '' || Number(line.cost) < 0) {
    error.value = 'Selecciona un producto y un costo unitario válido.';
    return;
  }
  const existing = cart.value.find(item => String(item.code) === String(line.code));
  if (existing) {
    const quantity = Math.round((Number(existing.qty) + Number(line.qty)) * 100) / 100;
    error.value = quantityError(line.code, quantity);
    if (error.value) return;
    existing.qty = quantity;
    existing.cost = Number(line.cost);
  } else {
    cart.value.push({ code: line.code, qty: Number(line.qty), cost: Number(line.cost) });
  }
}

// Quita solo la fila elegida; todavía no se guarda nada en el sistema.
function remove(code) {
  if (submitting.value) return;
  cart.value = cart.value.filter(item => item.code !== code);
  error.value = '';
}

// Pide permiso antes de vaciar la entrada y dejar el formulario como al inicio.
function cancel() {
  if (submitting.value) return;
  const hasData = cart.value.length || doc.value || supplier.value !== (suppliers.value[0]?.id_proveedor ?? '')
    || date.value !== today() || line.code !== (products.value[0]?.id_producto ?? '') || line.qty !== 10 || line.cost !== 5;
  if (hasData && !window.confirm('¿Deseas cancelar la entrada? Se perderán los datos ingresados.')) return;
  cart.value = [];
  supplier.value = suppliers.value[0]?.id_proveedor ?? '';
  date.value = today();
  doc.value = '';
  Object.assign(line, { code: products.value[0]?.id_producto ?? '', qty: 10, cost: 5 });
  error.value = '';
  modal.value = false;
  correlativo.value = '';
}

async function confirm() {
  if (!cart.value.length || submitting.value) return;
  error.value = cart.value.map(item => quantityError(item.code, item.qty)).find(Boolean) || '';
  if (error.value) return;
  submitting.value = true;
  try {
    const { data } = await api.post('/api/entradas', {
      id_proveedor: supplier.value, fecha: date.value, referencia: doc.value || null,
      detalles: cart.value.map(item => ({ id_producto: item.code, cantidad: Number(item.qty), costo_unitario: item.cost })),
    });
    correlativo.value = data.numero;
    cart.value = [];
    doc.value = '';
    modal.value = true;
    await load();
  } catch (e) {
    error.value = Object.values(e.response?.data?.errors || {}).flat()[0] || e.response?.data?.message || 'No fue posible registrar la entrada.';
  } finally {
    submitting.value = false;
  }
}
onMounted(load);
</script>
<style scoped>label{display:flex;flex-direction:column;gap:.25rem;font-size:.75rem;font-weight:600;text-transform:uppercase;color:#334155}.normal{font-size:.6875rem;text-transform:none;font-weight:400;color:#64748b}input,select{width:100%;padding:.625rem;border:1px solid #cbd5e1;border-radius:.5rem;font-size:.75rem;background:white;text-transform:none;font-weight:400}.modal{position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:#0f172a99;backdrop-filter:blur(3px);padding:1rem}.modal-card{width:100%;overflow:hidden;border-radius:.75rem;background:white;box-shadow:0 25px 50px #0004}.modal-head{display:flex;justify-content:space-between;align-items:center;background:#1F3A5F;padding:1rem 1.5rem;color:white;font-size:.875rem;font-weight:700}</style>