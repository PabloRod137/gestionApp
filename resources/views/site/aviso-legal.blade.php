@extends('layouts.site')

@section('title', 'Aviso Legal | Nortia')
@section('description', 'Aviso legal de Nortia con datos identificativos del titular.')
@section('nav', '')

@section('content')
<section class="product-hero"><div class="container"><h1>Aviso Legal</h1></div></section>
<section class="section"><div class="container container--narrow">
<h2>Datos identificativos</h2>
<p>Titular: {{ config('legal.legal_entity_name') }}.</p>
<p>NIF/CIF: {{ config('legal.legal_tax_id') }}.</p>
<p>Domicilio: {{ config('legal.legal_address') }}.</p>
<p>Contacto legal: {{ config('legal.legal_contact_email') }}.</p>
<p>Datos registrales: {{ config('legal.legal_registry_details') }}.</p>
<h2>Datos profesionales</h2>
<p>Colegio profesional: {{ config('legal.legal_professional_body') }}.</p>
<p>Número de colegiación: {{ config('legal.legal_professional_number') }}.</p>
<p>Titulo profesional: {{ config('legal.legal_professional_title') }}.</p>
<p>Normativa profesional aplicable: {{ config('legal.legal_professional_rules') }}.</p>
<h2>Objeto del sitio</h2>
<p>El sitio informa sobre servicios jurídicos, fiscales, laborales, de comunidades, inmobiliarios y de seguros, y permite enviar solicitudes de contacto y solicitudes de cita sujetas a confirmación del despacho.</p>
<h2>Precios e impuestos</h2>
<p>{{ config('legal.legal_tax_pricing_policy') }}</p>
</div></section>
@endsection
