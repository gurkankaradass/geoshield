import { computed, ref } from 'vue';

// Tarayıcı hafızasında önceden kalan bir session var mı kontrol ediyoruz
const token = ref<string | null>(localStorage.getItem('geoshield_token'));
const user = ref<{ username: string; email: string } | null>(
  localStorage.getItem('geoshield_user')
    ? JSON.parse(localStorage.getItem('geoshield_user')!)
    : null
);

export function useAuth() {
  // Kullanıcı giriş yapmış mı? (Geriye true/false döner)
  const isAuthenticated = computed(() => !!token.value);

  // Giriş Başarılı Olduğunda Session'ı Hafızaya Alan Fonksiyon
  const setSession = (jwtToken: string, userData: { username: string; email: string }) => {
    token.value = jwtToken;
    user.value = userData;
    localStorage.setItem('geoshield_token', jwtToken);
    localStorage.setItem('geoshield_user', JSON.stringify(userData));
  };

  // Çıkış Yapıldığında Hafızayı Temizleyen Fonksiyon
  const logout = () => {
    token.value = null;
    user.value = null;
    localStorage.removeItem('geoshield_token');
    localStorage.removeItem('geoshield_user');
    // Çıkış yapınca sayfayı yenileyip haritayı misafir moduna çekiyoruz
    window.location.reload();
  };

  return {
    token,
    user,
    isAuthenticated,
    setSession,
    logout,
  };
}
