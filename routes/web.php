<?php

use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\DomicilioController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\MovimientoInventarioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    if ($request->user()) {
        if ($request->user()->tieneRol('admin')) {
            return redirect()->route('dashboard');
        }
        if ($request->user()->tieneRol('vendedor')) {
            return redirect()->route('ventas.index');
        }
    }
    return redirect()->route('login');
});

// Panel Principal: exclusivo para admin
Route::get('/dashboard', function (Request $request) {
    if ($request->user()->tieneRol('vendedor') && ! $request->user()->tieneRol('admin')) {
        return redirect()->route('ventas.index');
    }
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Módulo de Ventas: accesible para vendedor y admin
Route::get('/ventas', [VentaController::class, 'index'])
    ->middleware(['auth', 'verified', 'rol:vendedor,admin'])
    ->name('ventas.index');
Route::post('/ventas', [VentaController::class, 'store'])
    ->middleware(['auth', 'verified', 'rol:vendedor,admin'])
    ->name('ventas.store');

// Seguimiento de domicilios propios para vendedor y administrador
Route::middleware(['auth', 'verified', 'rol:vendedor,admin'])->group(function () {
    Route::get('/domicilios', [DomicilioController::class, 'index'])->name('domicilios.index');
    Route::patch('/domicilios/{venta}/entregar', [DomicilioController::class, 'markDelivered'])->name('domicilios.entregar');
});

// Caja individual de cada vendedor o administrador
Route::middleware(['auth', 'verified', 'rol:vendedor,admin'])->group(function () {
    Route::get('/caja', [CajaController::class, 'index'])->name('caja.index');
    Route::post('/caja/abrir', [CajaController::class, 'open'])->name('caja.open');
    Route::post('/caja/{caja}/cerrar', [CajaController::class, 'close'])->name('caja.close');
});

// Compras y proveedores: exclusivo para admin
Route::middleware(['auth', 'verified', 'rol:admin'])->group(function () {
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/proveedores', [ProveedorController::class, 'index'])->name('proveedores.index');
    Route::post('/proveedores', [ProveedorController::class, 'store'])->name('proveedores.store');
    Route::put('/proveedores/{proveedor}', [ProveedorController::class, 'update'])->name('proveedores.update');
    Route::delete('/proveedores/{proveedor}', [ProveedorController::class, 'destroy'])->name('proveedores.destroy');
    Route::post('/proveedores/compras', [CompraController::class, 'store'])->name('proveedores.compras.store');
    Route::get('/proveedores/compras/{compra}/comprobante', [CompraController::class, 'receipt'])->name('proveedores.compras.receipt');
    Route::post('/proveedores/compras/{compra}/recibir', [CompraController::class, 'receive'])->name('proveedores.compras.receive');
});

// Módulo de Inventario: lectura para vendedor y admin, escritura solo admin
Route::get('/inventario', [InventarioController::class, 'index'])->middleware(['auth', 'verified', 'rol:vendedor,admin'])->name('inventario.index');
Route::prefix('inventario')->name('inventario.')->group(function () {
    Route::get('/movimientos/exportar', [InventarioController::class, 'export'])->middleware(['auth', 'verified', 'rol:admin'])->name('movimientos.export');
    Route::get('/productos/exportar', [InventarioController::class, 'exportProducts'])->middleware(['auth', 'verified', 'rol:admin'])->name('productos.export');
    Route::get('/productos', [ProductoController::class, 'index'])->middleware(['auth', 'verified', 'rol:vendedor,admin'])->name('productos.index');
    Route::post('/productos', [ProductoController::class, 'store'])->middleware(['auth', 'verified', 'rol:admin'])->name('productos.store');
    Route::put('/productos/{producto}', [ProductoController::class, 'update'])->middleware(['auth', 'verified', 'rol:admin'])->name('productos.update');
    Route::delete('/productos/{producto}', [ProductoController::class, 'destroy'])->middleware(['auth', 'verified', 'rol:admin'])->name('productos.destroy');
    Route::post('/productos/{producto}/ajustes', [MovimientoInventarioController::class, 'store'])->middleware(['auth', 'verified', 'rol:admin'])->name('productos.ajustes.store');

    Route::get('/categorias', [CategoriaController::class, 'index'])->middleware(['auth', 'verified', 'rol:vendedor,admin'])->name('categorias.index');
    Route::post('/categorias', [CategoriaController::class, 'store'])->middleware(['auth', 'verified', 'rol:admin'])->name('categorias.store');
    Route::put('/categorias/{categoria}', [CategoriaController::class, 'update'])->middleware(['auth', 'verified', 'rol:admin'])->name('categorias.update');
    Route::delete('/categorias/{categoria}', [CategoriaController::class, 'destroy'])->middleware(['auth', 'verified', 'rol:admin'])->name('categorias.destroy');
});

// Módulo de Configuración: exclusivo para admin (CRUD de usuarios y roles)
Route::middleware(['auth', 'verified', 'rol:admin'])->prefix('configuracion')->name('configuracion.')->group(function () {
    Route::get('/roles-usuarios', [UsuarioController::class, 'index'])->name('roles.index');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
    Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
