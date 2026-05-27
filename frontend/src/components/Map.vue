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
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41],
  shadowAnchor: [12, 41],
});

const emit = defineEmits<{
  (e: 'map-click', payload: { lat: number; lng: number }): void;
}>();

const mapContainer = ref<HTMLElement | null>(null);
const map = shallowRef<L.Map | null>(null);

// Katman Grupları
const faultLayerGroup = L.featureGroup();
const markerLayerGroup = L.layerGroup();
const userLocationsLayerGroup = L.layerGroup();

// Risk seviyelerine göre dinamik arka plan ve sınır CSS sınıfları
const getRiskColorClass = (riskLevel: string) => {
  switch (riskLevel) {
    case 'Kritik':
      return 'bg-red-600 border-red-400 text-white shadow-red-600/50';
    case 'Yüksek':
      return 'bg-orange-500 border-orange-300 text-white shadow-orange-500/50';
    case 'Orta':
      return 'bg-yellow-500 border-yellow-300 text-gray-950 shadow-yellow-500/50';
    default:
      return 'bg-green-600 border-green-400 text-white shadow-green-600/50';
  }
};

// Giriş Yapmış Kullanıcının Tüm Mülklerini Haritaya Çizen Fonksiyon
const renderUserLocations = (location: UserLocation[]) => {
  userLocationsLayerGroup.clearLayers();

  location.forEach((loc) => {
    const colorClass = getRiskColorClass(loc.risk_level);

    const customIcon = L.divIcon({
      html: `<div class="flex items-center justify-center w-8 h-8 rounded-full border-2 shadow-lg transition-all duration-200 hover:scale-110 ${colorClass}">
              <i class="fa-solid fa-${loc.property_type || 'location-dot'} text-xs"></i>
             </div>`,
      className: 'custom-property-marker-wrapper',
      iconSize: [32, 32],
      iconAnchor: [16, 16],
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
        <p class="text-[11px] m-0"><span class="text-gray-500">Mesafe:</span> <b>${loc.distance_km} km</b></p>
      </div>
    `);

    userLocationsLayerGroup.addLayer(marker);
  });
};

const toggleUserLocationsLayer = (visible: boolean) => {
  if (!map.value) return;
  if (visible) {
    map.value.addLayer(userLocationsLayerGroup);
  } else {
    map.value.removeLayer(userLocationsLayerGroup);
  }
};

const fetchFaultLines = async () => {
  if (!map.value) return;
  const bounds = map.value.getBounds();
  const sw = bounds.getSouthWest();
  const ne = bounds.getNorthEast();

  try {
    const response = await fetch(
      `http://localhost:8080/api/fault-lines?swLng=${sw.lng}&swLat=${sw.lat}&neLng=${ne.lng}&neLat=${ne.lat}`
    );
    if (!response.ok) throw new Error('API hatası oluştu.');
    const data: FaultLine[] = await response.json();

    faultLayerGroup.clearLayers();

    data.forEach((line) => {
      const rawCoords = line.coordinates.replace('LINESTRING(', '').replace(')', '').split(',');
      const leafletCoords = rawCoords.map((pair) => {
        const [lat, lng] = pair.trim().split(' ');
        return [parseFloat(lat), parseFloat(lng)] as [number, number];
      });

      const polyline = L.polyline(leafletCoords, {
        color: line.type === 'Diri Fay' ? '#c10000' : '#ff5000',
        weight: 2,
        opacity: 0.75,
      });

      polyline.bindPopup(`
        <div class="text-gray-900 font-sans p-1">
          <strong class="text-red-600 block text-sm border-b pb-1 mb-1">🌋 ${line.type}</strong>
          <p class="text-xs font-semibold m-0">Adı: ${line.name}</p>
        </div>
      `);

      polyline.addTo(faultLayerGroup);
    });
  } catch (error) {
    console.error('Fay hatları yüklenirken hata oluştu:', error);
  }
};

// 🎯 Haritayı Varsayılan Türkiye Odağına Döndüren Metot
const resetMapView = () => {
  if (map.value) {
    map.value.setView([38.72, 35.48], 6);
  }
};

// 📍 Kullanıcının GPS Konumunu Bulan Metot
const locateUser = () => {
  if (!map.value) return;

  if (!navigator.geolocation) {
    alert('Tarayıcınız konum servislerini desteklemiyor.');
    return;
  }

  navigator.geolocation.getCurrentPosition(
    (position) => {
      const { latitude, longitude } = position.coords;

      if (map.value) {
        map.value.flyTo([latitude, longitude], 13, { animate: true, duration: 1.5 });

        // Konumun üzerine geçici bir mavi daire ve marker çakalım
        markerLayerGroup.clearLayers();
        L.circle([latitude, longitude], {
          radius: 300,
          color: '#10b981',
          fillColor: '#10b981',
          fillOpacity: 0.15,
        }).addTo(markerLayerGroup);
        L.marker([latitude, longitude], { icon: defaultIcon }).addTo(markerLayerGroup);

        // Üst katmana da bildirip analizi tetikleyelim
        emit('map-click', { lat: latitude, lng: longitude });
      }
    },
    (error) => {
      console.error('Konum alınamadı:', error);
      alert('Konumunuza erişim izni verilemedi.');
    },
    { enableHighAccuracy: true }
  );
};

onMounted(() => {
  if (mapContainer.value) {
    map.value = L.map(mapContainer.value, {
      zoomControl: false, // Varsayılan çirkin +/- butonunu kapatıp kendimiz yönetiyoruz
    }).setView([38.72, 35.48], 6);

    // 🌍 KATMAN TANIMLAMALARI (İstediğin Tam Liste)
    const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors',
    });

    const topo = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenTopoMap contributors',
    });

    const cartoLight = L.tileLayer(
      'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
      {
        attribution: '© Carto contributors',
      }
    );

    const googleSat = L.tileLayer('http://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
      subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
      attribution: '© Google Maps',
    });

    const googleHybrid = L.tileLayer('http://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
      subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
      attribution: '© Google Maps',
    });

    const googleTerrain = L.tileLayer('http://{s}.google.com/vt/lyrs=p&x={x}&y={y}&z={z}', {
      subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
      attribution: '© Google Maps',
    });

    const esriSat = L.tileLayer(
      'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      {
        attribution: 'Tiles © Esri',
      }
    );

    // Varsayılan olarak Google Hybrid ile başlasın (Faylar ve yollar en net bunda görünür)
    googleHybrid.addTo(map.value);

    // 🎛️ Leaflet Yerel Katman Seçici (BaseMaps Kontrolü)
    const baseMaps = {
      'Google Uydu Karışık': googleHybrid,
      'Google Saf Uydu': googleSat,
      'Google Arazi': googleTerrain,
      'Esri Dünya Uydu': esriSat,
      'OpenStreetMap Standart': osm,
      'Açık Topo Haritası': topo,
      'Carto Açık Tema': cartoLight,
    };

    // Sağ üst köşeye şık katman kutusunu yerleştiriyoruz
    L.control
      .layers(baseMaps, undefined, { position: 'topright', collapsed: true })
      .addTo(map.value);

    // Yerel yakınlaştırma (+/-) butonunu sağ alta şıkça koyalım
    L.control.zoom({ position: 'topleft' }).addTo(map.value);

    // Grupları harita nesnesine bağlıyoruz
    faultLayerGroup.addTo(map.value);
    markerLayerGroup.addTo(map.value);
    userLocationsLayerGroup.addTo(map.value);

    fetchFaultLines();

    map.value.on('moveend', fetchFaultLines);

    map.value.on('click', (e: L.LeafletMouseEvent) => {
      const { lat, lng } = e.latlng;
      markerLayerGroup.clearLayers();
      L.marker([lat, lng], { icon: defaultIcon }).addTo(markerLayerGroup);
      emit('map-click', { lat, lng });
    });

    setTimeout(() => {
      map.value?.invalidateSize();
    }, 100);
  }
});

const focusOnLocation = (lat: number, lng: number) => {
  if (map.value) {
    map.value.flyTo([lat, lng], 14, { animate: true, duration: 1.5 });
  }
};

const clearTempMarker = () => {
  markerLayerGroup.clearLayers();
};

// Fay hatlarını haritadan kaldıran veya ekleyen fonksiyon
const toggleFaultLinesLayer = (visible: boolean) => {
  if (!map.value) return;
  if (visible) {
    map.value.addLayer(faultLayerGroup);
  } else {
    map.value.removeLayer(faultLayerGroup);
  }
};

defineExpose({
  focusOnLocation,
  renderUserLocations,
  toggleUserLocationsLayer,
  clearTempMarker,
  toggleFaultLinesLayer,
});
</script>

<template>
  <div class="w-full h-full relative">
    <div ref="mapContainer" class="w-full h-full z-10"></div>

    <div class="absolute bottom-4 left-4 z-[500] flex flex-col gap-2 select-none">
      <button
        @click="resetMapView"
        class="w-10 h-10 rounded-xl bg-gray-900/90 hover:bg-gray-800 border border-gray-800 text-emerald-400 hover:text-emerald-300 transition-all duration-200 shadow-2xl flex items-center justify-center cursor-pointer group backdrop-blur-sm"
        title="Haritayı Türkiye Odağına Sıfırla"
      >
        <i class="fa-solid fa-rotate text-base group-hover:scale-110 transition-transform"></i>
      </button>

      <button
        @click="locateUser"
        class="w-10 h-10 rounded-xl bg-gray-900/90 hover:bg-gray-800 border border-gray-800 text-emerald-400 hover:text-emerald-300 transition-all duration-200 shadow-2xl flex items-center justify-center cursor-pointer group backdrop-blur-sm"
        title="Mevcut Konumumu Bul"
      >
        <i
          class="fa-solid fa-location-crosshairs text-base group-hover:rotate-45 transition-transform"
        ></i>
      </button>
    </div>
  </div>
</template>

<style scoped>
:deep(.leaflet-control-attribution) {
  background: rgba(11, 17, 31, 0.8) !important;
  color: #9ca3af !important;
  backdrop-blur: 4px;
}

/* Leaflet Katman Kontrol Kutusunu Koyu Temaya Giydiriyoruz */
:deep(.leaflet-control-layers) {
  background-color: rgba(17, 24, 39, 0.95) !important;
  border: 1px solid #1f2937 !important;
  border-radius: 12px !important;
  color: #10b981 !important;
  font-family: sans-serif;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5) !important;
  backdrop-filter: blur(4px);
}
:deep(.leaflet-control-layers-toggle) {
  filter: brightness(0) saturate(100%) invert(64%) sepia(34%) saturate(1142%) hue-rotate(124deg)
    brightness(94%) contrast(92%) !important;
  transition: filter 0.15s ease;
}
:deep(.leaflet-control-layers-toggle:hover) {
  filter: brightness(0) saturate(100%) invert(75%) sepia(35%) saturate(1200%) hue-rotate(120deg)
    brightness(95%) contrast(90%) !important;
}
:deep(.leaflet-control-layers-list) {
  font-size: 11px !important;
  padding: 4px;
}
:deep(.leaflet-control-layers-base label) {
  margin-bottom: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 2px 4px;
  border-radius: 6px;
  transition: background-color 0.15s;
}
:deep(.leaflet-control-layers-base label:hover) {
  background-color: #1f2937;
}

/* Zoom Butonlarını Koyu Temaya Giydiriyoruz */
:deep(.leaflet-bar) {
  border: 1px solid #1f2937 !important;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5) !important;
}
:deep(.leaflet-bar a) {
  background-color: rgba(17, 24, 39, 0.95) !important;
  color: #10b981 !important;
  border-bottom: 1px solid #1f2937 !important;
  transition: background-color 0.15s;
}
:deep(.leaflet-bar a:hover) {
  background-color: #1f2937 !important;
  color: #34d399 !important;
}
</style>
