<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Marco de las páginas comunes a todos los usuarios (p. ej. el perfil): el del portal del cliente o el del panel
 * del despacho, según quién haya entrado, para que se vean con la misma imagen.
 */
class AppLayout extends Component
{
    public function render(): View
    {
        return view(auth()->user()?->hasAnyRole(['admin', 'worker']) ? 'layouts.panel' : 'layouts.client');
    }
}
