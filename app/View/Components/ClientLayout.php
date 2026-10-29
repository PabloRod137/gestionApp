<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class ClientLayout extends Component
{
    /**
     * Obtiene la vista que representa al componente.
     */
    public function render(): View
    {
        return view('layouts.client');
    }
}
