<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ShopController;

/*
|--------------------------------------------------------------------------
| Web Routes - Nortia
|--------------------------------------------------------------------------
*/

// --- 1. WEB PÚBLICA ---
Route::middleware('headers:csp')->group(function () {
    // Páginas informativas (vistas Blade en resources/views/site, con su CSS y JS en public/)
    Route::view('/', 'site.home')->name('home');
    Route::view('/quienes-somos', 'site.quienes-somos')->name('site.about');
    Route::view('/servicios', 'site.servicios')->name('site.services');
    Route::view('/inmuebles', 'site.inmuebles')->name('site.properties');
    Route::view('/blog', 'site.blog')->name('site.blog');
    // Primero las entradas de la web (vistas fijas) y, si no existe, las que se escriben desde el panel
    Route::get('/blog/{slug}', function (string $slug) {
        abort_unless(preg_match('/^[a-z0-9-]+$/', $slug), 404);
        if (view()->exists("site.blog.$slug")) {
            return view("site.blog.$slug");
        }
        $post = \App\Models\BlogPost::with('category')->where('slug', $slug)->where('publicado', true)->firstOrFail();

        return view('site.blog-post', compact('post'));
    })->name('site.blog.show');
    Route::view('/tienda', 'site.tienda')->name('site.shop');
    Route::view('/carrito', 'site.carrito')->name('site.cart');
    Route::view('/pago-completado', 'site.pago-completado')->name('site.payment.done');
    Route::view('/pago-cancelado', 'site.pago-cancelado')->name('site.payment.cancelled');
    Route::view('/contacto', 'site.contacto')->name('site.contact');
    Route::view('/reservar-cita', 'site.reservar-cita')->name('public.booking');
    Route::view('/aviso-legal', 'site.aviso-legal')->name('site.legal');
    Route::view('/politica-de-privacidad', 'site.privacidad')->name('site.privacy');
    Route::view('/politica-de-cookies', 'site.cookies')->name('site.cookies');
    Route::view('/condiciones-de-contratacion', 'site.contratacion')->name('site.terms');

    // Direcciones antiguas
    Route::redirect('/inmobiliaria', '/inmuebles', 301);
    Route::redirect('/actualidad', '/blog', 301);
});


// --- 2. PASARELA DE ACCESO AUTENTICADO (Breeze) ---
Route::get('/dashboard', [DashboardRedirectController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Perfil de usuario básico (Breeze)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Salir de la suplantación de cliente (Impersonate)
    Route::get('/impersonate/leave', function () {
        if (app('impersonate')->isImpersonating()) {
            app('impersonate')->clear();
            return redirect()->route('panel.clientes.index')
                ->with('success', 'Has vuelto a tu sesión de asesor original con éxito.');
        }
        return redirect()->route('dashboard');
    })->name('impersonate.leave');
});


// --- 3. GRUPOS DE RUTAS DEL CLIENTE Y PANEL INTERNO ---

// Portal del Cliente (Prefijo: 'cliente', Nombre de ruta: 'cliente.*')
Route::middleware(['auth', 'role:client'])
    ->prefix('cliente')
    ->name('cliente.')
    ->group(base_path('routes/cliente.php'));

// Panel del Trabajador/Admin (Prefijo: 'panel', Nombre de ruta: 'panel.*')
Route::middleware(['auth', 'role:worker|admin'])
    ->prefix('panel')
    ->name('panel.')
    ->group(base_path('routes/panel.php'));


// --- 4. RUTAS DE BREEZE ---
require __DIR__.'/auth.php';

// --- 5. SITEMAP Y ROBOTS ---
Route::get('/sitemap.xml', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach (\App\Support\Seo::rutasSitemap() as $path) {
        $xml .= '<url><loc>'.e(url($path)).'</loc></url>';
    }

    return response($xml.'</urlset>', 200)->header('Content-Type', 'application/xml');
})->name('sitemap');

// El sitemap sale del dominio configurado (APP_URL / el que recibe la petición), nunca de uno escrito a mano.
Route::get('/robots.txt', function () {
    $cuerpo = "User-agent: *
";
    foreach (['/panel/', '/cliente/', '/dashboard', '/login', '/register', '/password/', '/api/', '/carrito', '/pago-completado', '/pago-cancelado'] as $ruta) {
        $cuerpo .= "Disallow: $ruta
";
    }

    return response($cuerpo."
Sitemap: ".url('/sitemap.xml')."
", 200)->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('robots');

// --- 6. API DE LA WEB PÚBLICA (la consume el JavaScript de las páginas) ---
// Con sesión y token CSRF (cabecera X-CSRF-TOKEN); los formularios y las compras tienen límite de peticiones.
Route::prefix('api/nortia')->middleware('headers:csp')->group(function () {
    Route::get('/services', [PublicController::class, 'services']);
    Route::get('/availability', [PublicController::class, 'availability']);
    Route::post('/contact', [PublicController::class, 'contact'])->middleware('throttle:forms');
    Route::post('/reservations', [PublicController::class, 'reserve'])->middleware('throttle:forms');

    Route::get('/shop/products', [ShopController::class, 'products']);
    Route::get('/shop/orders/{id}', [ShopController::class, 'order']);
    Route::post('/shop/checkout', [ShopController::class, 'checkout'])->middleware('throttle:shop');

    Route::post('/chat/public', [ChatController::class, 'public'])->middleware('throttle:chat');
});
