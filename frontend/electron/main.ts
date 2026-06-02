import { app, BrowserWindow } from 'electron';
import path from 'path';
import { pathToFileURL } from 'url';

let mainWindow: BrowserWindow | null = null;

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1200,
    height: 800,
    minWidth: 1024,
    minHeight: 700,
    title: 'GeoShield - Sismik Risk Analiz Dashboard',
    backgroundColor: '#030712', // Akıcı açılış için Gray-950 arka plan rengi
    autoHideMenuBar: true, // Üstteki çirkin Windows menüsünü gizler (Alt tuşuyla açılır)
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
    },
  });

  // Geliştirme aşamasındaysak (Vite dev server çalışıyorsa) local url'i yükle
  if (process.env.VITE_DEV_SERVER_URL) {
    mainWindow.loadURL(process.env.VITE_DEV_SERVER_URL);
    // İstersen geliştirme aşamasında DevTools'u otomatik açabilirsin:
    // mainWindow.webContents.openDevTools();
  } else {
    // Üretim aşamasında (Build alındığında) derlenmiş index.html dosyasını yükle
    const indexPath = path.join(app.getAppPath(), 'dist', 'index.html');
    mainWindow.loadURL(pathToFileURL(indexPath).href);
  }

  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

app.whenReady().then(() => {
  createWindow();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow();
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});
