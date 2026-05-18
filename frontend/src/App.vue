<script setup lang="ts">
import { ref } from 'vue';
import Map from './components/Map.vue'
import type { RiskAnalysisResult } from './types/geo';

// Sol menünün açık/kapalı durumunu tutan reaktif state
const isSidebarOpen = ref(true);
const analysisResult = ref<RiskAnalysisResult | null>(null);
const isLoading = ref(false);

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value;
};

// Haritaya tıklandığında tetiklenecek analiz fonksiyonu
const handleMapClick = async (coords: {lat: number; lng:number}) => {
  isSidebarOpen.value = true; // Kullanıcı tıkladığında analiz sonucunu görsün diye sidebar'ı zorla açıyoruz
  isLoading.value = true;
  analysisResult.value = null;

  try {
    const response = await fetch(`http://localhost:8080/api/analyze-risk?lat=${coords.lat}&lng=${coords.lng}`);

    if(!response.ok) throw new Error('Analiz motoru yanıt vermedi.');

    const data: RiskAnalysisResult = await response.json();
    analysisResult.value = data;

  } catch (error) {
    console.error('Risk analizi yapılırken hata oluştu.', error);
  } finally{
    isLoading.value = false;
  }
};
</script>

<template>
  <div class="flex h-screen w-screen overflow-hidden bg-gray-950 font-sans antialiased text-gray-100">

    <div class="fixed inset-y-0 left-0 z-20 flex flex-col bg-gray-900 border-r border-gray-800 transition-all duration-300 ease-in-out md:relative"
    :class="isSidebarOpen ? 'w-80 translate-x-0' : 'w-0 -translate-x-full md:w-16 md:translate-x-0'">

      <div class="flex items-center justify-between h-16 px-4 border-b border-gray-800 overflow-hidden shrink-0">
        <span v-if="isSidebarOpen" class="text-lg font-bold tracking-wider text-emerald-400 uppercase">
          🛡️ GeoShield
        </span>
        <button @click="toggleSidebar" class="p-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 focus:outline-none transition-colors border border-gray-700">
          <span>{{ isSidebarOpen ?  '◀' : '▶'  }}</span>
        </button>
      </div>


      <div class="flex-1 overflow-y-auto p-4 space-y-4" v-if="isSidebarOpen">

        <div class="bg-gray-800/60 border border-gray-700 rounded-xl p-4">
          <h3 class="font-semibold text-sm text-emerald-400 mb-1">Mekansal Analiz Motoru</h3>
          <p class="text-xs text-gray-400 leading-relaxed">
            Harita üzerinde herhangi bir noktaya tıklayarak en yakın aktif fay hattına olan mesafeyi ve sismik risk düzeyini anlık olarak hesaplayabilirsiniz.
          </p>
        </div>

        <div v-if="isLoading" class="bg-gray-800 border border-gray-700 rounded-xl p-4 animate-pulse space-y-3">
          <div class="h-4 bg-gray-700 rounded w-2/3"></div>
          <div class="h-8 bg-gray-700 rounded"></div>
          <div class="h-4 bg-gray-700 rounded w-1/2"></div>
        </div>

        <div v-if="analysisResult" class="space-y-3">
          <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 space-y-3">
            <div class="flex items-center justify-between border-b border-gray-700 pb-2">
              <span class="text-xs font-medium text-gray-400">Sismik Risk Seviyesi</span>
              <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase"
              :class="{
                'bg-red-500/20 text-red-400 border border-red-500/30': analysisResult.risk_level === 'Kritik',
                'bg-orange-500/20 text-orange-400 border border-orange-500/30': analysisResult.risk_level === 'Yüksek',
                'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30': analysisResult.risk_level === 'Orta',
                'bg-green-500/20 text-green-400 border border-green-500/30': analysisResult.risk_level === 'Düşük',
              }"
              >
            {{ analysisResult.risk_color }} {{ analysisResult.risk_level }}
            </span>
            </div>

            <div class="space-y-2">
              <div>
                <label class="text-[10px] uppercase tracking-wider text-gray-500 block">En Yakın Fay Hattı</label>
                <span class="text-sm font-semibold text-gray-200">{{ analysisResult.fault_name }}</span>
              </div>
              <div class="grid grid-cols-2 gap-2 pt-1">
                <div>
                  <label class="text-[10px] uppercase tracking-wider text-gray-500 block">Mesafe (Kilometre)</label>
                  <span class="text-base font-bold text-emerald-400">{{ analysisResult.distance_km }} km</span>
                </div>
                <div>
                  <label class="text-[10px] uppercase tracking-wider text-gray-500 block">Fay Karakteri</label>
                  <span class="text-xs font-medium text-gray-300 block mt-0.5">{{ analysisResult.fault_type }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="!analysisResult && !isLoading" class="text-xs text-gray-500 text-center py-8 border border-dashed border-gray-800 rounded-xl">
          Analiz başlatmak için haritada bir konuma tıklayın.
        </div>
      </div>
    </div>

    <div class="flex-1 relative flex flex-col h-full w-full min-w-0 overflow-hidden">
      <Map @map-click="handleMapClick" />
    </div>

  </div>
</template>
