// frontend/vite.config.ts
import { defineConfig, type PluginOption } from 'vite';
import { fileURLToPath, URL } from 'node:url';
import vue from '@vitejs/plugin-vue';
import electron from 'vite-plugin-electron'; // 🔥 Yeni eklendi

export default defineConfig(({ mode }) => {
  const plugins: PluginOption[] = [vue()];

  // Eğer derleme/çalıştırma modumuz "desktop" ise Electron plugin'ini aktif et
  if (mode === 'desktop') {
    plugins.push(
      electron({
        entry: 'electron/main.ts',
      })
    );
  }

  return {
    plugins,
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      port: 5173,
    },
  };
});
