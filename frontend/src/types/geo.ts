// src/types/geo.ts

// Backend'deki BBox API'sinden dönecek fay hattı modeli
export interface FaultLine {
  id: number;
  name: string;
  type: string;
  coordinates: string; // LINESTRING(...) formatındaki metin
}

// Backend'deki analyze-risk API'sinden dönecek analiz sonucu modeli
export interface RiskAnalysisResult {
  input_coords: [number, number];
  fault_id: number;
  fault_name: string;
  fault_type: string;
  distance_m: number;
  distance_km: number;
  risk_level: 'Kritik' | 'Yüksek' | 'Orta' | 'Düşük';
  risk_color: string;
}

export interface UserLocation {
  id: number;
  user_id: number | null;
  title: string;
  risk_level: 'Kritik' | 'Yüksek' | 'Orta' | 'Düşük';
  distance_km: number;
  closest_fault_name: string;
  lat: number;
  lng: number;
  created_at: string;
}
