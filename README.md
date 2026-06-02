# 🌍 GeoShield

GeoShield is a desktop application built with Vue 3, Electron, and TypeScript, backed by a robust CodeIgniter 4 (PHP) backend. It features interactive mapping capabilities using Leaflet.

## 🚀 Features

- **Interactive Maps**: Powered by Leaflet to visualize and interact with geospatial data.
- **Modern Desktop App**: Built using Electron for a seamless native desktop experience.
- **Robust Backend**: Fast and secure backend built with CodeIgniter 4.
- **Modern Frontend**: Vue 3 and Vite provide lightning-fast development and optimized builds.
- **Responsive UI**: Styled elegantly using Tailwind CSS.

## 🛠️ Tech Stack

### Frontend (Desktop Client)
- **Core**: Vue 3, TypeScript, Vite
- **Desktop Environment**: Electron
- **Mapping**: Leaflet
- **Styling**: Tailwind CSS, PostCSS

### Backend (API)
- **Framework**: CodeIgniter 4 (PHP 8.2+)
- **Security**: JWT Authentication (firebase/php-jwt)

## 📁 Project Structure

The repository is modular and divided into two main environments:

- [`/frontend`](./frontend/): Contains the Vue 3 + Electron application.
- [`/backend`](./backend/): Contains the CodeIgniter 4 backend service.

---

## ⚙️ Setup & Installation

### Prerequisites
- **Node.js** (v18+ recommended)
- **PHP** (v8.2 or higher)
- **Composer** (PHP package manager)

### Backend Setup

1. Navigate to the backend directory:
   ```bash
   cd backend
   ```
2. Install PHP dependencies:
   ```bash
   composer install
   ```
3. Environment Configuration:
   Copy the `env` file to `.env` and configure your database and base URL settings.
   ```bash
   cp env .env
   ```
4. Start the development server:
   ```bash
   php spark serve
   ```

### Frontend Setup

1. Navigate to the frontend directory:
   ```bash
   cd frontend
   ```
2. Install Node.js dependencies:
   ```bash
   npm install
   ```
3. Run the development server (Web mode):
   ```bash
   npm run dev
   ```
4. Run the desktop app development mode (Electron):
   ```bash
   npm run desktop:dev
   ```

## 📦 Building for Production

To build and package the desktop application (e.g., for Windows `.exe`):

```bash
cd frontend
npm run desktop:package
```
The packaged installer will be generated and available in the `frontend/release` directory.

## 📄 License

This project is licensed under the MIT License. See the `LICENSE` file for details.
