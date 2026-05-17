<script setup lang="ts">
import { onMounted, ref } from 'vue';
import L from 'leaflet';

const mapContainer = ref<HTMLElement | null>(null);
const map = ref<L.Map | null>(null);

onMounted(()=>{
    if (mapContainer.value) {
        // Haritayı Türkiye merkezli (Kayseri) başlatıyoruz
       map.value = L.map(mapContainer.value).setView([38.72, 35.48], 6);
       
       // OpenStreetMap harita katmanını ekliyoruz
       L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
       }).addTo(map.value);

       // Haritaya tıklama olayını yakalayıp konsola basalım (İleride API'ye bağlanacak)
       map.value.on('click', (e: L.LeafletMouseEvent) => {
        const {lat, lng} = e.latlng;
        console.log(`Tıklanan Koordinat -> Enlem: ${lat}, Boylam: ${lng}`);
       });
    }
});
</script>

<template>
    <div ref="mapContainer" class="w-full h-full z-10"></div>
</template>

<style scoped>
/* Harita altındaki Leaflet yazılarının karanlık temaya uyumu */
:deep(.leaflet-control-attribution){
    background: rgba(17, 24, 39, 0.8) !important;
    color: #9ca3af !important;
}
</style>