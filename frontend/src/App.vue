<script setup lang="ts">
import { onMounted, ref } from 'vue';
import Map from './components/Map.vue';
import type { RiskAnalysisResult, UserLocation } from './types/geo';

const mapRef = ref<InstanceType<typeof Map> | null>(null);

// Sol menünün açık/kapalı durumunu tutan reaktif state
const isSidebarOpen = ref(true);
const analysisResult = ref<RiskAnalysisResult | null>(null);
const isLoading = ref(false);

// Mülklerim CRUD State'leri
const savedLocations = ref<UserLocation[]>();
const propertyTitle = ref('');
const isSaving = ref(false);

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value;
};

// 1. READ: Kayıtlı konumları API'den çeken fonksiyon
const fetchSavedLocations = async () => {
  try {
    const response = await fetch('http://localhost:8080/api/user_locations');
    if (response.ok) {
      savedLocations.value = await response.json();
    }
  } catch (error) {
    console.error('Konumlar listesi yüklenirken hata oluştu:', error);
  }
};

// Haritaya tıklandığında tetiklenecek analiz fonksiyonu
const handleMapClick = async (coords: { lat: number; lng: number }) => {
  isSidebarOpen.value = true; // Kullanıcı tıkladığında analiz sonucunu görsün diye sidebar'ı zorla açıyoruz
  isLoading.value = true;
  analysisResult.value = null;
  propertyTitle.value = ''; // Form input'unu temizle

  try {
    const response = await fetch(
      `http://localhost:8080/api/analyze-risk?lat=${coords.lat}&lng=${coords.lng}`
    );

    if (!response.ok) throw new Error('Analiz motoru yanıt vermedi.');

    const data: RiskAnalysisResult = await response.json();
    analysisResult.value = data;
  } catch (error) {
    console.error('Risk analizi yapılırken hata oluştu.', error);
  } finally {
    isLoading.value = false;
  }
};

// 2. CREATE: Yeni mülkü veritabanına kaydeden fonksiyon
const saveCurrentLocation = async () => {
  if (!propertyTitle.value.trim() || !analysisResult.value) return;

  isSaving.value = true;
  const payload = {
    title: propertyTitle.value.trim(),
    lat: analysisResult.value.input_coords[0],
    lng: analysisResult.value.input_coords[1],
    risk_level: analysisResult.value.risk_level,
    distance_km: analysisResult.value.distance_km,
    closest_fault_name: analysisResult.value.fault_name,
  };

  try {
    const response = await fetch('http://localhost:8080/api/user_locations', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    if (response.ok) {
      analysisResult.value = null; // Formu kapat
      propertyTitle.value = '';
      await fetchSavedLocations(); // Listeyi anlık olarak güncelle
    }
  } catch (error) {
    console.error('Konum kaydedilirken hata oluştu:', error);
  } finally {
    isSaving.value = false;
  }
};

// 3. DELETE: Kayıtlı mülkü sistemden silen fonksiyon
const deleteLocation = async (id: number, event: Event) => {
  event.stopPropagation(); // Karta tıklama (odaklanma) olayını engellemek için
  if (!confirm('Bu mülk kaydını silmek istediğinize emin misiniz?')) return;

  try {
    const response = await fetch(`http://localhost:8080/api/user_locations/${id}`, {
      method: 'DELETE',
    });
    if (response.ok) {
      await fetchSavedLocations(); // Listeyi yenile
    }
  } catch (error) {
    console.error('Konum silinirken hata oluştu:', error);
  }
};

// Harita listedeki mülke yumuşakça uçsun
const clickLocationItem = (loc: UserLocation) => {
  mapRef.value?.focusOnLocation(loc.lat, loc.lng);
};

// Sayfa ilk yüklendiğinde kayıtlı konumları listele
onMounted(() => {
  fetchSavedLocations();
});
</script>

<template>
  <div
    class="flex h-screen w-screen overflow-hidden bg-gray-950 font-sans antialiased text-gray-100"
  >
    <div
      class="fixed inset-y-0 left-0 z-20 flex flex-col bg-gray-900 border-r border-gray-800 transition-all duration-300 ease-in-out md:relative"
      :class="
        isSidebarOpen ? 'w-80 translate-x-0' : 'w-0 -translate-x-full md:w-16 md:translate-x-0'
      "
    >
      <div
        class="flex items-center justify-between h-16 px-4 border-b border-gray-800 overflow-hidden shrink-0"
      >
        <span
          v-if="isSidebarOpen"
          class="text-lg font-bold tracking-wider text-emerald-400 uppercase"
        >
          🛡️ GeoShield
        </span>
        <button
          @click="toggleSidebar"
          class="p-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 focus:outline-none transition-colors border border-gray-700"
        >
          <span>{{ isSidebarOpen ? '◀' : '▶' }}</span>
        </button>
      </div>

      <div class="flex-1 overflow-y-auto p-4 space-y-4" v-if="isSidebarOpen">
        <div
          class="bg-gray-800/60 border border-gray-700 rounded-xl p-4"
          v-if="!analysisResult && !isLoading"
        >
          <h3 class="font-semibold text-sm text-emerald-400 mb-1">Mekansal Analiz Motoru</h3>
          <p class="text-xs text-gray-400 leading-relaxed">
            Harita üzerinde herhangi bir noktaya tıklayarak en yakın aktif fay hattına olan mesafeyi
            ve sismik risk düzeyini anlık olarak hesaplayabilirsiniz.
          </p>
        </div>

        <div
          v-if="isLoading"
          class="bg-gray-800 border border-gray-700 rounded-xl p-4 animate-pulse space-y-3"
        >
          <div class="h-4 bg-gray-700 rounded w-2/3"></div>
          <div class="h-8 bg-gray-700 rounded"></div>
          <div class="h-4 bg-gray-700 rounded w-1/2"></div>
        </div>

        <div v-if="analysisResult && !isLoading" class="space-y-3">
          <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 space-y-3">
            <div class="flex items-center justify-between border-b border-gray-700 pb-2">
              <span class="text-xs font-medium text-gray-400">Sismik Risk Seviyesi</span>
              <span
                class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase"
                :class="{
                  'bg-red-500/20 text-red-400 border border-red-500/30':
                    analysisResult.risk_level === 'Kritik',
                  'bg-orange-500/20 text-orange-400 border border-orange-500/30':
                    analysisResult.risk_level === 'Yüksek',
                  'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30':
                    analysisResult.risk_level === 'Orta',
                  'bg-green-500/20 text-green-400 border border-green-500/30':
                    analysisResult.risk_level === 'Düşük',
                }"
              >
                {{ analysisResult.risk_color }} {{ analysisResult.risk_level }}
              </span>
            </div>

            <div class="text-xs space-y-2 border-b border-gray-700/50 pb-3">
              <p>
                <span class="text-gray-500">En Yakın Fay:</span>
                <span class="font-semibold text-gray-200">{{ analysisResult.fault_name }}</span>
              </p>
              <p>
                <span class="text-gray-500">Mesafe:</span>
                <span class="font-bold text-emerald-400 text-sm"
                  >{{ analysisResult.distance_km }} km</span
                >
              </p>
            </div>

            <div class="space-y-2 pt-1">
              <label class="text-[11px] uppercase tracking-wider text-gray-400 font-semibold block"
                >Bu Konumu Kaydet</label
              >
              <input
                v-model="propertyTitle"
                type="text"
                placeholder="Örn: Evim, Merkez Ofis, Arsa..."
                class="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-emerald-500 transition-colors placeholder-gray-600"
              />
              <button
                @click="saveCurrentLocation"
                :disabled="isSaving || !propertyTitle.trim()"
                class="w-full bg-emerald-600 hover:bg-emerald-500 disabled:bg-gray-800 disabled:text-gray-600 disabled:border-gray-700 border border-transparent text-gray-950 font-bold py-2 px-4 rounded-lg text-xs transition-all flex items-center justify-center gap-1 cursor-pointer"
              >
                <span>{{ isSaving ? 'Kaydediliyor...' : '💾 Listeme Ekle' }}</span>
              </button>
            </div>
          </div>
        </div>

        <div class="space-y-2 shrink-0">
          <h3
            class="text-xs font-bold uppercase tracking-widest text-gray-500 px-1 flex items-center justify-between"
          >
            <span>📋 Kayıtlı Mülklerim</span>
            <span
              class="text-[10px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded-md font-mono"
              >{{ savedLocations?.length || 0 }}</span
            >
          </h3>

          <div class="space-y-2 max-h-[40vh] overflow-y-auto pr-1 select-none">
            <div
              v-for="loc in savedLocations"
              :key="loc.id"
              @click="clickLocationItem(loc)"
              class="bg-gray-800/40 hover:bg-gray-800 border border-gray-800 hover:border-gray-700 rounded-xl p-3 flex items-center justify-between gap-2 transition-all cursor-pointer group"
            >
              <div class="space-y-1 min-w-0">
                <h4 class="text-xs font-bold text-gray-200 truncate">{{ loc.title }}</h4>
                <p class="text-[10px] text-gray-500 truncate flex items-center gap-1">
                  <span>💥 {{ loc.closest_fault_name }}</span>
                  <span>•</span>
                  <span class="font-semibold text-gray-400">{{ loc.distance_km }}</span>
                </p>
              </div>

              <div class="flex items-center gap-2 shrink-0">
                <span
                  class="w-2 h-2 rounded-full shadow-lg"
                  :class="{
                    'bg-red-500 shadow-red-500/50': loc.risk_level === 'Kritik',
                    'bg-orange-500 shadow-orange-500/50': loc.risk_level === 'Yüksek',
                    'bg-yellow-500 shadow-yellow-500/50': loc.risk_level === 'Orta',
                    'bg-green-500 shadow-yellow-500/50': loc.risk_level === 'Düşük',
                  }"
                ></span>
                <button
                  @click="deleteLocation(loc.id, $event)"
                  class="text-gray-600 hover:text-red-400 p-1 rounded hover:bg-gray-700/50 transition-all opacity-0 group-hover:opacity-100"
                  title="Kaydı Sil"
                >
                  🗑️
                </button>
              </div>
            </div>

            <div
              v-if="savedLocations?.length === 0"
              class="text-[11px] text-gray-600 text-center py-8 border border-dashed border-gray-800 rounded-xl"
            >
              Henüz kayıtlı bir mülk yok.
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="flex-1 relative flex flex-col h-full w-full min-w-0 overflow-hidden">
      <Map ref="mapRef" @map-click="handleMapClick" />
    </div>
  </div>
</template>

<style>
/* Listenin scroll barını daha şık ve koyu yapalım */
::-webkit-scrollbar {
  width: 4px;
}
::-webkit-scrollbar-track {
  background: #374151;
  border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
  background: #4b5563;
}
</style>
