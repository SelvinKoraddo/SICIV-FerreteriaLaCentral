<script setup>
/**
 * Pantalla de inicio de sesión.
 *
 * - Valida en el cliente antes de enviar y muestra los errores junto a cada campo.
 * - Interpreta las respuestas del servidor: credenciales incorrectas (422), bloqueo por
 *   intentos (429), sesión/CSRF caducados (419) y errores de red.
 * - Evita envíos duplicados mientras hay una petición en curso.
 *
 * @see HU-01 Inicio de sesión
 * @emits authenticated  Usuario autenticado devuelto por el servidor.
 */
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../../services/api';

const props = defineProps({
  /** Aviso contextual, por ejemplo cuando la sesión anterior expiró. */
  notice: { type: String, default: '' },
});
const emit = defineEmits(['authenticated']);

const form = reactive({ usuario: '', password: '' });
const errors = reactive({ usuario: '', password: '' });
const generalError = ref('');
const loading = ref(false);
const showPassword = ref(false);
const usuarioInput = ref(null);

const currentYear = new Date().getFullYear();
const canSubmit = computed(() => !loading.value && form.usuario.trim() !== '' && form.password !== '');

const clearErrors = () => {
  errors.usuario = '';
  errors.password = '';
  generalError.value = '';
};

/** Validación mínima en el cliente para dar respuesta inmediata. */
const validate = () => {
  clearErrors();
  if (form.usuario.trim() === '') errors.usuario = 'Ingresa tu usuario.';
  if (form.password === '') errors.password = 'Ingresa tu contraseña.';
  return !errors.usuario && !errors.password;
};

/** Traduce la respuesta de error del servidor a mensajes para la persona usuaria. */
const handleError = (exception) => {
  const { status, data } = exception.response ?? {};
  const firstError = (field) => data?.errors?.[field]?.[0];

  if (status === 422 || status === 429) {
    errors.usuario = firstError('usuario') ?? '';
    errors.password = firstError('password') ?? '';
    generalError.value = errors.usuario || errors.password ? '' : (data?.message || 'Usuario o contraseña incorrectos.');
    if (status === 429 && !errors.usuario) generalError.value = data?.message || 'Demasiados intentos. Espera un momento e inténtalo de nuevo.';
    form.password = '';
    return;
  }

  if (status === 419) {
    generalError.value = 'La página estuvo inactiva demasiado tiempo. Recárgala e inténtalo de nuevo.';
    return;
  }

  generalError.value = exception.response
    ? 'Ocurrió un error en el servidor. Inténtalo de nuevo en unos minutos.'
    : 'No hay conexión con el servidor. Verifica tu red e inténtalo de nuevo.';
};

const submit = async () => {
  if (loading.value || !validate()) return;
  loading.value = true;

  try {
    const { data } = await api.post('/api/login', {
      usuario: form.usuario.trim(),
      password: form.password,
    });
    emit('authenticated', data.user);
  } catch (exception) {
    handleError(exception);
  } finally {
    loading.value = false;
  }
};

onMounted(() => usuarioInput.value?.focus());
</script>

<template>
  <main class="login-page">
    <section class="login-card" aria-labelledby="login-title">
      <header class="login-header">
        <div class="brand-mark" aria-hidden="true"><i class="fa-solid fa-screwdriver-wrench"></i></div>
        <h1 id="login-title">SICIV Ferretería La Central</h1>
        <p class="login-subtitle">Sistema de Control de Inventario y Ventas</p>
      </header>

      <div class="login-content">
        <p v-if="notice && !generalError" class="login-notice" role="status">
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ notice }}
        </p>
        <p v-if="generalError" class="alert alert-error" role="alert">
          <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ generalError }}
        </p>

        <form class="login-form" novalidate @submit.prevent="submit">
          <div class="field" :class="{ invalid: errors.usuario }">
            <label for="login-usuario">Usuario</label>
            <div class="input-wrap">
              <i class="fa-solid fa-user" aria-hidden="true"></i>
              <input id="login-usuario" ref="usuarioInput" v-model="form.usuario" type="text" autocomplete="username"
                autocapitalize="none" spellcheck="false" maxlength="50" placeholder="Tu nombre de usuario"
                :aria-invalid="Boolean(errors.usuario)" :aria-describedby="errors.usuario ? 'login-usuario-error' : undefined"
                :disabled="loading" @input="errors.usuario = ''">
            </div>
            <small v-if="errors.usuario" id="login-usuario-error" class="field-error">{{ errors.usuario }}</small>
          </div>

          <div class="field" :class="{ invalid: errors.password }">
            <label for="login-password">Contraseña</label>
            <div class="input-wrap">
              <i class="fa-solid fa-lock" aria-hidden="true"></i>
              <input id="login-password" v-model="form.password" :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password" maxlength="255" placeholder="Tu contraseña"
                :aria-invalid="Boolean(errors.password)"
                :aria-describedby="errors.password ? 'login-password-error' : undefined" :disabled="loading"
                @input="errors.password = ''">
              <button type="button" class="toggle-password" :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                :aria-pressed="showPassword" :disabled="loading" @click="showPassword = !showPassword">
                <i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
              </button>
            </div>
            <small v-if="errors.password" id="login-password-error" class="field-error">{{ errors.password }}</small>
          </div>

          <button type="submit" class="button button-primary button-wide submit-button" :disabled="!canSubmit">
            <i v-if="loading" class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
            <i v-else class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
            {{ loading ? 'Verificando...' : 'Ingresar al sistema' }}
          </button>
        </form>

        <footer class="login-footer">
          <span>Ferretería La Central</span>
          <span>{{ currentYear }}</span>
        </footer>
      </div>
    </section>
  </main>
</template>

<style scoped>
.login-card { display: grid; gap: 0; overflow: hidden; padding: 0; border-radius: 10px; }
.login-header { display: grid; justify-items: center; gap: 6px; padding: 24px 32px; color: #fff; background: #203d62; text-align: center; }
.login-header .brand-mark { margin-bottom: 8px; border: 1px solid #ffffff1f; border-radius: 50%; color: #f28c28; background: #193452; font-size: 22px; }
.login-header h1 { margin: 0; color: #fff; font-size: 24px; line-height: 1.2; }
.login-subtitle { margin: 0; color: #d7e2ee; font-size: 13px; }
.login-content { display: grid; gap: 22px; padding: 32px; }
.login-notice { display: flex; align-items: center; gap: 8px; margin: 0; padding: 11px 13px; border-radius: 8px; color: #1f3a5f; background: #eef4fb; font-size: 13px; }
.alert-error { display: flex; align-items: center; gap: 8px; margin: 0; }
.login-form { display: grid; gap: 16px; }
.field { display: grid; gap: 6px; }
.field label { color: #445168; font-size: 13px; font-weight: 700; }
.input-wrap { position: relative; }
.input-wrap > i { position: absolute; top: 50%; left: 13px; color: #8ba0bc; transform: translateY(-50%); pointer-events: none; }
.input-wrap input { padding-left: 38px; }
.input-wrap input:disabled { background: #f8fafc; color: #94a3b8; }
.field.invalid input { border-color: #dc2626; background: #fff7f7; }
.field.invalid input:focus { box-shadow: 0 0 0 3px #dc262622; }
.field-error { color: #b42318; font-size: 12px; font-weight: 600; }
.toggle-password { position: absolute; top: 50%; right: 6px; display: grid; width: 32px; height: 32px; place-items: center; border: 0; border-radius: 6px; color: #64748b; background: transparent; transform: translateY(-50%); }
.toggle-password:hover:not(:disabled) { color: #1f3a5f; background: #eef2f7; }
.toggle-password:focus-visible { outline: 2px solid #f28c28; outline-offset: 1px; }
.input-wrap:has(.toggle-password) input { padding-right: 44px; }
.submit-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; font-size: 14px; }
.submit-button:disabled { cursor: not-allowed; opacity: .6; }
.login-footer { display: flex; justify-content: center; gap: 4px; color: #94a3b8; font-size: 11px; }

@media (max-width: 480px) {
  .login-content { padding: 28px 24px; }
}
</style>
