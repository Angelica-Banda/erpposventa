# Sistema POS

Aplicación web de punto de venta y gestión básica. Incluye ventas, control de inventario, caja, domicilios, compras, proveedores, usuarios, configuración de empresa y reportes.

## Tecnologías

- **Backend:** PHP 8.2 o superior y Laravel 12.
- **Frontend:** Vue 3, Inertia.js, Tailwind CSS y Vite.
- **Base de datos:** SQLite por defecto; también hay configuración para MySQL y MariaDB.
- **Exportaciones:** PDF con Dompdf y Excel `.xlsx`.
- **Pruebas:** PHPUnit mediante Laravel.

## Requisitos

Instala las herramientas siguientes antes de configurar el proyecto:

| Herramienta | Requisito |
| --- | --- |
| PHP | 8.2 o superior |
| Composer | Composer 2 |
| Node.js | 20.19+ o 22.12+ (se recomienda una versión LTS compatible con Vite 7) |
| npm | Versión incluida con Node.js; compatible con `package-lock.json` |
| Base de datos | SQLite con `pdo_sqlite` (opción predeterminada) o MySQL/MariaDB con su controlador PDO |

Laravel y los paquetes del proyecto también requieren extensiones PHP habituales como `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo` y `pdo`. La exportación a Excel requiere `zip`; para MySQL/MariaDB habilita `pdo_mysql`. Composer indicará si falta una extensión requerida.

Comprueba las instalaciones:

```powershell
php -v
composer --version
node --version
npm --version
```

## Instalación local en Windows

Abre PowerShell en la carpeta del proyecto y ejecuta:

```powershell
composer install
Copy-Item .env.example .env
```

El archivo `.env.example` usa SQLite. Crea el archivo de base de datos si todavía no existe:

```powershell
if (-not (Test-Path database\database.sqlite)) {
    New-Item database\database.sqlite -ItemType File | Out-Null
}
```

Genera la clave de Laravel, instala las dependencias del frontend, crea las tablas y carga los roles y usuarios locales:

```powershell
php artisan key:generate
npm ci
php artisan migrate --seed
php artisan storage:link
```

Inicia el entorno local integrado:

```powershell
composer run dev
```

Este comando inicia el servidor de Laravel, el worker de colas, los logs y Vite. Abre la URL que indica Laravel, normalmente <http://localhost:8000>. Detén los procesos con `Ctrl+C`.

También puedes iniciar los servicios por separado en terminales distintas:

```powershell
php artisan serve
```

```powershell
npm run dev
```

Para compilar los recursos estáticos de producción:

```powershell
npm run build
```

## Configuración de base de datos

### SQLite

SQLite es la configuración predeterminada del proyecto. En `.env`, comprueba que esté seleccionada esta conexión:

```dotenv
DB_CONNECTION=sqlite
```

Laravel utiliza `database/database.sqlite` por defecto. Si cambias esa ruta, configura `DB_DATABASE` con la ubicación correspondiente y asegúrate de que el proceso PHP pueda leer y escribir el archivo.

### MySQL o MariaDB

Primero crea una base de datos vacía, por ejemplo:

```sql
CREATE DATABASE licorestito
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Luego configura las variables de conexión en `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=licorestito
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
```

Habilita `pdo_mysql` en PHP y ejecuta las migraciones:

```powershell
php artisan migrate --seed
```

No subas `.env` ni credenciales reales al repositorio.

## Roles y acceso

El sistema asigna los siguientes roles:

- **Administrador (`admin`):** acceso al panel, operación POS, inventario, caja, domicilios, compras, proveedores, reportes y configuración.
- **Vendedor (`vendedor`):** acceso al POS, su caja, consulta de inventario y seguimiento de sus domicilios. No puede administrar productos, compras, reportes ni configuración.

El registro público crea usuarios con rol de vendedor. Las rutas operativas están protegidas por autenticación, verificación de correo y control de rol, según el módulo.

El seeder crea estas cuentas de desarrollo:

| Rol | Correo | Contraseña inicial |
| --- | --- | --- |
| Administrador | `admin@posventa.com` | `password` |
| Vendedor | `vendedor@posventa.com` | `password` |

Estas credenciales son únicamente para desarrollo local. 

## Módulos disponibles

### Panel principal

Presenta indicadores del día o de la semana: ventas y transacciones, unidades vendidas, productos con stock bajo y domicilios. Incluye el resumen gráfico de ventas y una lista de productos que requieren atención.

### Ventas POS

Permite consultar productos y categorías, armar una venta y generar su comprobante. La venta requiere una caja abierta y valida el stock disponible. Se puede registrar como venta de mostrador o domicilio; este último tipo guarda los datos del cliente, la dirección, el costo del domicilio y su estado inicial.

### Inventario

Administra productos, categorías y existencias. Los ajustes y las ventas generan movimientos de inventario. El vendedor puede consultar productos; las operaciones de administración de catálogo y ajustes están reservadas al administrador.

### Caja

Cada usuario puede abrir y cerrar su caja. El cierre registra efectivo esperado, efectivo contado y diferencia. El administrador también dispone de una operación para corregir cierres, protegida por rol.

### Domicilios

Permite consultar y actualizar el estado de los domicilios propios. El listado se filtra por el usuario autenticado; el vendedor solo gestiona sus pedidos y el administrador también puede acceder a sus propios registros.

### Compras y proveedores

El administrador puede gestionar proveedores y registrar compras con productos, cantidades y costos. Las compras nuevas quedan pendientes: el inventario aumenta al recibirlas, no al registrarlas.

### Reportes

El módulo administrativo consulta **ventas, inventario, compras, caja y domicilios** por rango de fechas. Presenta indicadores, detalle y gráficos basados en registros existentes. Ofrece descarga en PDF y Excel; el PDF incluye el análisis y las recomendaciones calculadas con los datos disponibles.

### Configuración

El administrador puede gestionar usuarios y roles y mantener la información comercial y tributaria de la empresa, incluido el logo. Para que el logo almacenado en el disco público sea accesible, crea el enlace con `php artisan storage:link`.

## Pruebas y mantenimiento

Ejecuta la suite de pruebas:

```powershell
php artisan test
```

El entorno de PHPUnit usa una base de datos SQLite en memoria y no modifica la base de datos de desarrollo.

Para borrar cachés de configuración, rutas, vistas y otros datos optimizados:

```powershell
php artisan optimize:clear
```

Después de cambiar dependencias del frontend, instala las fijadas en el lockfile con `npm ci`. Para reconstruir los recursos:

```powershell
npm run build
```

## Estructura principal

```text
app/
  Http/Controllers/   Controladores y lógica de los módulos
  Http/Middleware/    Autenticación y autorización por rol
  Models/             Modelos de datos
database/
  migrations/         Esquema de la base de datos
  seeders/            Roles y cuentas iniciales de desarrollo
resources/
  js/Pages/           Páginas Vue/Inertia
  views/              Vistas Blade, incluido el PDF de reportes
routes/
  web.php             Rutas web y permisos
  auth.php            Rutas de autenticación
tests/
  Feature/            Pruebas de funcionalidades
  Unit/               Pruebas unitarias
```
