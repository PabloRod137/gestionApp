@extends('layouts.site')

@section('title', 'Política de privacidad | Nortia')
@section('description', 'Política de privacidad de Nortia.')
@section('nav', '')

@section('content')
<section class="product-hero"><div class="container"><h1>Política de privacidad</h1></div></section>
<section class="section"><div class="container container--narrow">
<h2>Responsable del tratamiento</h2>
<p>{{ config('legal.privacy_controller') }}. Contacto: {{ config('legal.legal_contact_email') }}.</p>
<h2>Finalidades y base jurídica</h2>
<p>{{ config('legal.privacy_purposes_and_bases') }}</p>
<h2>Destinatarios y proveedores</h2>
<p>{{ config('legal.privacy_recipients') }}</p>
<h2>Conservación</h2>
<p>{{ config('legal.privacy_retention_criteria') }}</p>
<h2>Transferencias internacionales</h2>
<p>{{ config('legal.privacy_international_transfers') }}</p>
<h2>Derechos</h2>
<p>Puede ejercer sus derechos en: {{ config('legal.privacy_rights_contact') }}.</p>
<h2>Delegado de Protección de Datos</h2>
<p>{{ config('legal.privacy_dpo_contact') }}</p>
<h2>Contacto y reservas</h2>
<p>{{ config('legal.privacy_contact_bookings') }}</p>
<h2>Cuentas del Area Cliente</h2>
<p>{{ config('legal.privacy_client_accounts') }}</p>
</div></section>
@endsection
