<script setup lang="ts">
import { ref } from 'vue';
import Map from './components/Map.vue'

// Sol menünün açık/kapalı durumunu tutan reaktif state
const isSidebarOpen = ref(true);

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value;
};
</script>

<template>
  <div class="flex h-screen w-screen overflow-hidden bg-gray-950 font-sans antialiased text-gray-100">

    <div class="fixed inset-y-0 left-0 z-20 flex flex-col bg-gray-900 border-r border-gray-800 transition-all duration-300 ease-in-out md:relative" :class="isSidebarOpen ? 'w-80 translate-x-0' : 'w-0 -translate-x-full md:w-16 md:translate-x-0'">

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
            Harita üzerinde risk analizi yapmak istediğiniz binanın veya arsanın üzerine tıklayarak konum ekleyin.
          </p>
        </div>

        <div class="text-xs text-gray-500 text-center py-8 border border-dashed border-gray-800 rounded-xl">
          Henüz kaydedilmiş bir mülk yok.
        </div>
      </div>
    </div>

    <div class="flex-1 relative flex flex-col h-full w-full min-w-0 overflow-hidden">
      <Map/>
    </div>

  </div>
</template>
