<script setup lang="ts">
import { onMounted, ref } from 'vue';
import L from 'leaflet';
import type { FaultLine } from '../types/geo';

// Tıklama olayını dışarıya (App.vue'ya) paslamak için emit tanımlıyoruz
const emit = defineEmits<{
    (e: 'map-click', payload: {lat: number; lng: number}): void
}>();

const mapContainer = ref<HTMLElement | null>(null);
const map = ref<L.Map | null>(null);

// Haritadaki çizgileri mükemmel bir performansla yönetmek için Leaflet FeatureGroup kullanıyoruz
const faultLayerGroup = L.featureGroup();

// Aktif tıklama marker'ını hafızada tutuyoruz ki her tıklandığında eskisi silinsin
let activeMarker: L.Marker | null = null;

// API'den dinamik veri çeken fonksiyon
const fetchFaultLines = async () => {
    if(!map.value) return;

    // Haritanın o anki ekran sınırlarını (Bounding Box) alıyoruz
    const bounds = map.value.getBounds();
    const sw = bounds.getSouthWest(); // Güneybatı koordinatları
    const ne = bounds.getNorthEast(); // Kuzeydoğu koordinatları

    try {
        // CodeIgniter 4 API'mize sınır koordinatlarını parametre olarak geçiyoruz
        const response = await fetch(
      `http://localhost:8080/api/fault-lines?swLng=${sw.lng}&swLat=${sw.lat}&neLng=${ne.lng}&neLat=${ne.lat}`
    );

        if(!response.ok) throw new Error('API hatası oluştu.');

        const data: FaultLine[] = await response.json();

        // Yeni çizgileri çizmeden önce eski çizgileri haritadan temizliyoruz
        faultLayerGroup.clearLayers();

        // Gelen her bir fay hattını parse edip haritaya çiziyoruz
        data.forEach((line) => {
            // Backend'den gelen format: LINESTRING(26.91 40.62, 26.90 40.62)
            // Bunu Leaflet'in anlayacağı [[40.62, 26.91], [40.62, 26.90]] (Lat, Lng) formatına çeviriyoruz
            const rawCoords = line.coordinates
            .replace('LINESTRING(', '')
            .replace(')', '')
            .split(',');

            const leafletCoords = rawCoords.map((pair) => {
                const [lat, lng] = pair.trim().split(' ');
                return [parseFloat(lat), parseFloat(lng)] as [number, number];
            });

            // Çizgiyi oluşturup rengini ve kalınlığını ayarlıyoruz
            const polyline = L.polyline(leafletCoords, {
                color: line.type === 'Diri Fay' ? '#ef4444' : '#f97316', // Tailwind red-500
                weight: 3,
                opacity: 0.85
            });

            // Çizgiye tıklandığında popup penceresinde fayın adını ve tipini gösteriyoruz
            polyline.bindPopup(`
            <div class="text-gray-900 font-sans p-1">
            <strong class="text-red-600 block text-sm border-b pb-1 mb-1">🌋 ${line.type}</strong>
            <p class="text-xs font-semibold m-0">Adı: ${line.name}</p>
            </div>
            `);

            // Çizgiyi grubumuza ekliyoruz
            polyline.addTo(faultLayerGroup);
        });
        
    } catch (error) {
        console.error('Fay hatları yüklenirken hata oluştu:', error);
    }
};


onMounted(()=>{
    if (mapContainer.value) {
        // Haritayı Ganos Segmenti (Marmara/Çanakkale civarı) yakınlarında başlatıyoruz ki veriyi hemen görelim
       map.value = L.map(mapContainer.value).setView([38.72, 35.48], 6);
       
       // OpenStreetMap harita katmanını ekliyoruz
       L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
       }).addTo(map.value);

       // Çizgi grubunu haritaya bağlıyoruz
       faultLayerGroup.addTo(map.value);

       // Harita ilk yüklendiğinde fayları getir
       fetchFaultLines();

       // HARİKA UX: Kullanıcı haritayı sürüklemeyi (moveend) veya yakınlaştırmayı (zoomend) 
       // bitirdiği an yeni ekran sınırlarına göre API tetiklenir
       map.value.on('moveend', fetchFaultLines);

       // Haritaya tıklama olayını yakalayıp konsola basalım (İleride API'ye bağlanacak)
       map.value.on('click', (e: L.LeafletMouseEvent) => {
        const {lat, lng} = e.latlng;
        
        // Eğer ekranda eski bir marker varsa önce onu haritadan siliyoruz
        if (activeMarker && map.value) {
            map.value.removeLayer(activeMarker);
        }

        // Tıklanan yere yeni, şık bir marker bırakıyoruz
        if (map.value) {
            activeMarker = L.marker([lat, lng]).addTo(map.value);
        }

        // Üst katmana koordinatları fırlatıyoruz
        emit('map-click', {lat, lng});
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