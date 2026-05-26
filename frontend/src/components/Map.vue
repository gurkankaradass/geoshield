<script setup lang="ts">
import { onMounted, ref, shallowRef } from 'vue';
import L from 'leaflet';
import type { FaultLine, UserLocation } from '../types/geo';

// 🚨 LEAFLET + VUE 3 ZOOM ANIMATION SAFETY PATCH
const originalAnimateZoom = (L.Marker.prototype as any)._animateZoom;
(L.Marker.prototype as any)._animateZoom = function (this: any, opt: any) {
  if (!this._map) return;
  originalAnimateZoom.call(this, opt);
};

// 🚨 VITE + LEAFLET IKON YOLU DÜZELTMESİ
const defaultIcon = L.icon({
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
  iconSize: [25, 41], // İkonun gerçek piksel boyutu
  iconAnchor: [12, 41], // İkonun tam olarak hangi noktasının koordinata çakılacağı (En alt orta nokta)
  popupAnchor: [1, -34], // Açılacak popup'ın ikona göre konumu
  shadowSize: [41, 41],
  shadowAnchor: [12, 41], // Gölge ikonun çakılma noktası (ikon ile aynı)
});

// Tıklama olayını dışarıya (App.vue'ya) paslamak için emit tanımlıyoruz
const emit = defineEmits<{
  (e: 'map-click', payload: { lat: number; lng: number }): void;
}>();

const mapContainer = ref<HTMLElement | null>(null);
// Vue 3 reactivity sisteminin Leaflet nesnelerini bozmasını önlemek için shallowRef kullanıyoruz
const map = shallowRef<L.Map | null>(null);

// Haritadaki çizgileri mükemmel bir performansla yönetmek için Leaflet FeatureGroup kullanıyoruz
const faultLayerGroup = L.featureGroup();

// Aktif tıklama marker'ını güvenli ve temiz yönetmek için Leaflet LayerGroup kullanıyoruz
const markerLayerGroup = L.layerGroup();

const userLocationsLayerGroup = L.layerGroup(); // Mülklerin haritada kalıcı durması için grup

// Risk seviyelerine göre dinamik arka plan ve sınır CSS sınıfları
const getRiskColorClass = (riskLevel: string) => {
  switch (riskLevel) {
    case 'Kritik':
      return 'bg-red-600 border-red-400 text-white shadow-red-600/50';
    case 'Yüksek':
      return 'bg-orange-500 border-orange-300 text-white shadow-orange-500/50';
    case 'Kritik':
      return 'bg-yellow-500 border-yellow-500 text-white shadow-yellow-500/50';
    default:
      return 'bg-green-600 border-green-400 text-white shadow-green-600/50';
  }
};

// Giriş Yapmış Kullanıcının Tüm Mülklerini Haritaya Çizen Fonksiyon
const renderUserLocations = (location: UserLocation[]) => {
  userLocationsLayerGroup.clearLayers(); // Yeniden çizilmeden önce eski mülk marker'larını temizle

  location.forEach((loc) => {
    const colorClass = getRiskColorClass(loc.risk_level);

    // FontAwesome İkonunu pürüzsüzce Leaflet DivIcon içerisine Tailwind ile giydiriyoruz
    const customIcon = L.divIcon({
      html: `<div class="flex items-center justify-center w-8 h-8 rounded-full border-2 shadow-lg transition-all duration-200 hover:scale-110 ${colorClass}">
      <i class="fa-solid fa-${loc.property_type || 'location_dot'} text-xs"></i>
      </div>`,
      className: 'custom-property-marker-wrapper', // Leaflet'in varsayılan beyaz kare stilini sıfırlamak için boş wrapper class'ı
      iconSize: [32, 32],
      iconAnchor: [16, 16], // Merkeze tam oturması için
      popupAnchor: [0, -16],
    });

    const marker = L.marker([loc.lat, loc.lng], { icon: customIcon });

    marker.on('click', () => {
      focusOnLocation(loc.lat, loc.lng);
    });

    marker.bindPopup(`
    <div class="text-gray-900 font-sans p-1">
    <strong class="text-emerald-500 block text-sm border-b pb-1 mb-1">🏢 ${loc.title}</strong>
    <p class="text-[11px] m-0"><span class="text-gray-500">En Yakın Fay Hattı:</span> ${loc.closest_fault_name}</p>
    <p class="text-[11px] m-0"><span class="text-gray-500">Mesafe:</span><b>${loc.distance_km} km</b></p>
    </div>
    `);

    userLocationsLayerGroup.addLayer(marker);
  });
};

// Mülk Katman Grubunu Haritadan Kaldırma / Ekleme (Switch için)
const toggleUserLocationsLayer = (visible: boolean) => {
  if (!map.value) return;
  if (visible) {
    map.value.addLayer(userLocationsLayerGroup);
  } else {
    map.value.removeLayer(userLocationsLayerGroup);
  }
};

// API'den dinamik veri çeken fonksiyon
const fetchFaultLines = async () => {
  if (!map.value) return;

  // Haritanın o anki ekran sınırlarını (Bounding Box) alıyoruz
  const bounds = map.value.getBounds();
  const sw = bounds.getSouthWest(); // Güneybatı koordinatları
  const ne = bounds.getNorthEast(); // Kuzeydoğu koordinatları

  try {
    // CodeIgniter 4 API'mize sınır koordinatlarını parametre olarak geçiyoruz
    const response = await fetch(
      `http://localhost:8080/api/fault-lines?swLng=${sw.lng}&swLat=${sw.lat}&neLng=${ne.lng}&neLat=${ne.lat}`
    );

    if (!response.ok) throw new Error('API hatası oluştu.');

    const data: FaultLine[] = await response.json();

    // Yeni çizgileri çizmeden önce eski çizgileri haritadan temizliyoruz
    faultLayerGroup.clearLayers();

    // Gelen her bir fay hattını parse edip haritaya çiziyoruz
    data.forEach((line) => {
      // Backend'den gelen format: LINESTRING(26.91 40.62, 26.90 40.62)
      // Bunu Leaflet'in anlayacağı [[40.62, 26.91], [40.62, 26.90]] (Lat, Lng) formatına çeviriyoruz
      const rawCoords = line.coordinates.replace('LINESTRING(', '').replace(')', '').split(',');

      const leafletCoords = rawCoords.map((pair) => {
        const [lat, lng] = pair.trim().split(' ');
        return [parseFloat(lat), parseFloat(lng)] as [number, number];
      });

      // Çizgiyi oluşturup rengini ve kalınlığını ayarlıyoruz
      const polyline = L.polyline(leafletCoords, {
        color: line.type === 'Diri Fay' ? '#c10000' : '#ff5000', // Tailwind red-500
        weight: 3,
        opacity: 1,
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

onMounted(() => {
  if (mapContainer.value) {
    // Haritayı Ganos Segmenti (Marmara/Çanakkale civarı) yakınlarında başlatıyoruz ki veriyi hemen görelim
    map.value = L.map(mapContainer.value).setView([38.72, 35.48], 6);

    // OpenStreetMap harita katmanını ekliyoruz
    L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
      maxZoom: 20,
      subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
      attribution: '&copy; Google Maps',
    }).addTo(map.value);

    // Çizgi grubunu haritaya bağlıyoruz
    faultLayerGroup.addTo(map.value);

    // Aktif marker grubunu haritaya bağlıyoruz
    markerLayerGroup.addTo(map.value);

    // Mülk grubu ilk açılışta haritada aktif gelsin
    userLocationsLayerGroup.addTo(map.value);

    // Harita ilk yüklendiğinde fayları getir
    fetchFaultLines();

    // HARİKA UX: Kullanıcı haritayı sürüklemeyi (moveend) veya yakınlaştırmayı (zoomend)
    // bitirdiği an yeni ekran sınırlarına göre API tetiklenir
    map.value.on('moveend', fetchFaultLines);

    // Haritaya tıklama olayını yakalayıp konsola basalım (İleride API'ye bağlanacak)
    map.value.on('click', (e: L.LeafletMouseEvent) => {
      const { lat, lng } = e.latlng;

      // Eski marker'ları gruptan temizliyoruz
      markerLayerGroup.clearLayers();

      // Tıklanan yere yeni bir marker oluşturup gruba ekliyoruz
      L.marker([lat, lng], { icon: defaultIcon }).addTo(markerLayerGroup);

      // Üst katmana koordinatları fırlatıyoruz
      emit('map-click', { lat, lng });
    });

    // SİHİRLİ SATIR: Harita ilk yüklendiğinde boyut hesaplamalarını milisaniyelik olarak tetikler
    setTimeout(() => {
      map.value?.invalidateSize();
    }, 100);
  }
});

// Dışarıdan (App.vue'dan) çağrılacak olan harita odaklama fonksiyonu
const focusOnLocation = (lat: number, lng: number) => {
  if (map.value) {
    // Haritayı mülkün koordinatına 14 yakınlık derecesiyle yumuşakça uçurur
    map.value.flyTo([lat, lng], 14, {
      animate: true,
      duration: 1.5, // Saniye cinsinden animasyon süresi
    });
  }
};

const clearTempMarker = () => {
  markerLayerGroup.clearLayers();
};

// Fonksiyonu ana bileşenin (App.vue) erişimine açıyoruz
defineExpose({
  focusOnLocation,
  renderUserLocations,
  toggleUserLocationsLayer,
  clearTempMarker,
});
</script>

<template>
  <div ref="mapContainer" class="w-full h-full z-10"></div>
</template>

<style scoped>
/* Harita altındaki Leaflet yazılarının karanlık temaya uyumu */
:deep(.leaflet-control-attribution) {
  background: rgba(17, 24, 39, 0.8) !important;
  color: #9ca3af !important;
}
/* Leaflet'in divIcon için zorla enjekte ettiği beyaz arka plan kare kutusunu ve kenarlıkları sıfırlıyoruz */
.custom-property-marker-wrapper {
  background: transparent !important;
  border: none !important;
}
</style>
