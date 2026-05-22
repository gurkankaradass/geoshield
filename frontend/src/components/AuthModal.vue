<script setup lang="ts">
import { ref } from 'vue';
import { useAuth } from '../composables/useAuth';

const emit = defineEmits(['close', 'auth-success']);
const { setSession } = useAuth();

const isLoginMode = ref(true); // Giriş modu mu, Kayıt modu mu?
const email = ref('');
const username = ref('');
const password = ref('');
const errorMessage = ref('');
const isLoading = ref(false);

const toggleMode = () => {
  isLoginMode.value = !isLoginMode.value;
  errorMessage.value = '';
};

const handleSubmit = async () => {
  errorMessage.value = '';
  isLoading.value = true;

  const endpoint = isLoginMode.value ? 'login' : 'register';
  const payload = isLoginMode.value
    ? { email: email.value, password: password.value }
    : { username: username.value, email: email.value, password: password.value };

  try {
    const response = await fetch(`http://localhost:8080/api/${endpoint}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message?.error || data.message || 'Bir hata oluştu.');
    }

    if (isLoginMode.value) {
      // Giriş başarılıysa session'ı set et
      setSession(data.token, data.user);
      emit('auth-success');
      emit('close');
    } else {
      // Kayıt başarılıysa kullanıcıyı doğrudan giriş moduna geçir
      alert('Kaydınız başarıyla oluşturuldu! Şimdi giriş yapabilirsiniz.');
      isLoginMode.value = true;
      password.value = '';
    }
  } catch (error: any) {
    errorMessage.value = error.message;
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <div
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/80 backdrop-blur-sm"
  >
    <div
      class="w-full max-w-md bg-gray-900 border border-gray-800 rounded-2xl shadow-2xl p-6 relative space-y-4"
    >
      <button
        @click="emit('close')"
        class="absolute top-4 right-4 text-gray-500 hover:text-gray-300 text-sm cursor-pointer"
      >
        ✕
      </button>

      <div class="text-center">
        <h3 class="text-xl font-bold tracking-wide text-emerald-400">
          {{ isLoginMode ? "🛡️ GeoShield'a Giriş Yap" : '📝 Yeni Hesap Oluştur' }}
        </h3>
        <p class="text-xs text-gray-400 mt-1">
          {{
            isLoginMode
              ? 'Mülklerinizi kaydetmek ve sismik risk takibi yapmak için giriş yapın.'
              : 'Güvenli bölge analizinizi kişiselleştirmek için hemen katılın.'
          }}
        </p>
      </div>

      <div
        v-if="errorMessage"
        class="bg-red-500/10 border border-red-500/20 text-red-400 text-xs p-3 rounded-lg text-center"
      >
        ⚠️ {{ errorMessage }}
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-3">
        <div v-if="!isLoginMode" class="space-y-1">
          <label class="text-[10px] font-bold uppercase text-gray-400 tracking-wider"
            >Kullanıcı Adı</label
          >
          <input
            v-model="username"
            type="text"
            required
            placeholder="Kullanıcı Adı"
            class="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2 text-xs text-gray-200 focus:outline-none focus:border-emerald-500 transition-colors"
          />
        </div>

        <div class="space-y-1">
          <label class="text-[10px] font-bold uppercase text-gray-400 tracking-wider"
            >E-Posta Adresi</label
          >
          <input
            v-model="email"
            type="text"
            required
            placeholder="ornek@email.com"
            class="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2 text-xs text-gray-200 focus:outline-none focus:border-emerald-500 transition-colors"
          />
        </div>

        <div class="space-y-1">
          <label class="text-[10px] font-bold uppercase text-gray-400 tracking-wider">Şifre</label>
          <input
            v-model="password"
            type="password"
            required
            placeholder="••••••••"
            class="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2 text-xs text-gray-200 focus:outline-none focus:border-emerald-500 transition-colors"
          />
        </div>

        <button
          :disabled="isLoading"
          type="submit"
          class="w-full bg-emerald-600 hover:bg-emerald-500 disabled:bg-gray-800 disabled:text-gray-600 font-bold py-2.5 rounded-lg text-xs tracking-wide text-gray-950 transition-all mt-2 cursor-pointer"
        >
          {{ isLoading ? 'İşlem yapılıyor...' : isLoginMode ? 'Giriş Yap' : 'Kayıt Ol' }}
        </button>
      </form>

      <div class="text-center pt-2 border-t border-gray-800">
        <button
          @click="toggleMode"
          class="text-xs text-emerald-400 hover:underline bg-transparent border-none cursor-pointer"
        >
          {{ isLoginMode ? 'Hesabınız yok mu? Kayıt Olun' : 'Zaten hesabınız var mı? Giriş Yapın' }}
        </button>
      </div>
    </div>
  </div>
</template>
