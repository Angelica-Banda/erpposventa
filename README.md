# 🛒 PosVenta - Sistema POS & ERP

Sistema integral de gestión comercial, punto de venta (POS), control de inventario y administración de roles y usuarios, desarrollado con **Laravel**, **Vue 3**, **Inertia.js** y **Tailwind CSS**.

---

## 📋 Requisitos del Sistema

Antes de iniciar la instalación en cualquier computadora, asegúrate de contar con los siguientes programas y versiones instaladas:

| Software | Versión Recomendada | Comando para verificar |
| :--- | :--- | :--- |
| **PHP** | **8.2** o **8.3** | `php -v` |
| **Composer** | **2.2+** | `composer -V` |
| **Node.js** | **18.x**, **20.x** o superior | `node -v` |
| **NPM** | **9.x** o **10.x** | `npm -v` |
| **MySQL / MariaDB** | **8.0+** / **10.4+** (XAMPP, Laragon o nativo) | Corriendo en puerto `3306` |

### Extensiones PHP Necesarias
Asegúrate de que en tu archivo `php.ini` estén habilitadas (descomentadas) las siguientes extensiones:
- `pdo_mysql`
- `mbstring`
- `openssl`
- `tokenizer`
- `xml`
- `ctype`
- `json`
- `bcmath`
- `fileinfo`
- `curl`

---

## 🚀 Guía de Instalación Paso a Paso

Sigue estos pasos ordenados en tu terminal (PowerShell o Git Bash):

### 1. Clonar o Ubicar el Proyecto
Abre tu terminal y ubícate en la carpeta del proyecto:
```bash
cd C:\ruta\hacia\posventa\proyecto
```

---

### 2. Instalar las dependencias de PHP (Composer)
Descarga todos los paquetes necesarios del backend:
```bash
composer install
```

---

### 3. Instalar las dependencias de Frontend (NPM)
Descarga las librerías de Vue 3, Inertia y Tailwind CSS:
```bash
npm install
```

---

### 4. Configurar el archivo de entorno (`.env`)
Copia el archivo de plantilla `.env.example` a `.env`:

* **En Windows (PowerShell):**
  ```powershell
  copy .env.example .env
  ```
* **En Git Bash / Linux / Mac:**
  ```bash
  cp .env.example .env
  ```

Abre el archivo `.env` y asegúrate de configurar los parámetros de tu base de datos MySQL:

```env
APP_NAME=PosVenta
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=postventaerp
DB_USERNAME=root
DB_PASSWORD=
```
> **Nota:** Si usas contraseña en tu MySQL (ej. root / root), colócala en `DB_PASSWORD`.

---

### 5. Crear la Base de Datos en MySQL
Si la base de datos no existe, créala desde tu gestor de base de datos preferido (phpMyAdmin, DBeaver, HeidiSQL o MySQL CLI):

```sql
CREATE DATABASE sistema_pos_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

### 6. Generar la Llave de la Aplicación
Genera la clave de encriptación `APP_KEY` requerida por Laravel:
```bash
php artisan key:generate
```

---

### 7. Ejecutar Migraciones y Datos de Prueba (Seeders)
Crea las tablas en la base de datos (`usuarios`, `roles`, `roles_usuarios`, etc.) y registra los roles y usuarios iniciales:
```bash
php artisan migrate --seed
```

*(Si ya habías corrido migraciones previas y deseas reiniciar la base de datos limpia con todos los datos iniciales, ejecuta `php artisan migrate:fresh --seed`).*

---

### 8. Compilar los Assets de Frontend
Para compilar los estilos y componentes Vue:

* **Para entorno de desarrollo (con recarga rápida en vivo):**
  ```bash
  npm run dev
  ```
* **Para compilar en producción:**
  ```bash
  npm run build
  ```

---

### 9. Iniciar el Servidor de Laravel
En una ventana de terminal independiente, inicia el servidor de desarrollo de PHP:
```bash
php artisan serve
```

La aplicación quedará disponible en:
👉 **`http://localhost:8000`**

---

## 👥 Usuarios y Credenciales Preconfiguradas

El seeder inicial genera automáticamente los roles y las siguientes cuentas de prueba:

| Rol | Nombre | Correo Electrónico | Contraseña | Permisos y Acceso |
| :--- | :--- | :--- | :--- | :--- |
| **Administrador (`admin`)** | Administrador | `admin@posventa.com` | `password` | **Acceso total**: Panel principal, POS Ventas, Inventario, Domicilios, Reportes y Módulo de Configuraciones. |
| **Administrador (`admin`)** | Administrador | `test@example.com` | `password` | Usuario inicial alternativo con acceso total. |
| **Vendedor** | Carlos Vendedor | `vendedor@posventa.com` | `password` | **Acceso exclusivo**: Ingresa directamente al Módulo de Ventas (POS). Restringido de dashboards y configuraciones. |

## 🧭 Estructura de Módulos del Sistema

1. **Pantalla de Inicio de Sesión (`/login`)**:
   - Diseño moderno dividido (*split-screen*) con formulario en el lateral izquierdo y panel corporativo púrpura en el lateral derecho.
   - Botón para ver/ocultar contraseña, validaciones reactivas y redirección automática según el rol asignado.

2. **Panel Principal / Dashboard (`/dashboard`)**:
   - Tarjetas KPI: Ventas del día, productos vendidos, alertas de stock bajo y domicilios.
   - Gráfico de barras interactivo de ventas por horario nocturno/diurno.
   - Lista dinámica de alertas de inventario con indicadores de estado (crítico / normal).

3. **Módulo de Ventas POS (`/ventas`)**:
   - Catálogo rápido de productos con buscador y categorías.
   - Carrito de compra reactivo con cálculo automático de totales.
   - Procesamiento de cobros y tickets.

4. **Módulo de Configuraciones (`/configuracion/roles-usuarios`)**:
   - Desplegable en el menú lateral para usuarios con el rol `admin`.
   - CRUD completo: Crear nuevos usuarios, editar nombres/correos/contraseñas y asignar o remover roles del sistema mediante la tabla pivote `roles_usuarios`.
   - Protección contra auto-eliminación de cuenta activa.

---

## 🛠️ Comandos de Mantenimiento y Solución de Problemas

### Limpiar Caché de la Aplicación
Si realizas cambios en rutas, configuraciones o vistas y no se reflejan:
```bash
php artisan optimize:clear
```

### Error de Conexión a la Base de Datos (`Access Denied` / `Unknown Database`)
1. Verifica que el servicio de MySQL esté corriendo (ej. botón verde en XAMPP).
2. Comprueba que el nombre de la base de datos en el archivo `.env` (`postventaerp`) exista en MySQL.
3. Verifica que el usuario `root` y la contraseña coincidan con tu servidor local.

### Enlace simbólico de almacenamiento (Storage)
Para habilitar la carga y visualización de archivos públicos:
```bash
php artisan storage:link
```
