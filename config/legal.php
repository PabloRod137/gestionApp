<?php

// Datos legales y de privacidad que el despacho debe aportar antes de publicar la web.
// Mientras una variable no esté definida se muestra un aviso de «pendiente» y la web no se considera lista (ver App\Support\LegalData).
// una variable vacía (como llega desde .env.example) cuenta como no definida
$pending = fn (string $name) => trim((string) env($name)) !== '' ? env($name) : 'PENDIENTE DE COMPLETAR POR EL DESPACHO ('.$name.')';

return [
    'legal_entity_name' => $pending('LEGAL_ENTITY_NAME'),
    'legal_tax_id' => $pending('LEGAL_TAX_ID'),
    'legal_address' => $pending('LEGAL_ADDRESS'),
    'legal_contact_email' => $pending('LEGAL_CONTACT_EMAIL'),
    'legal_registry_details' => $pending('LEGAL_REGISTRY_DETAILS'),
    'legal_professional_body' => $pending('LEGAL_PROFESSIONAL_BODY'),
    'legal_professional_number' => $pending('LEGAL_PROFESSIONAL_NUMBER'),
    'legal_professional_title' => $pending('LEGAL_PROFESSIONAL_TITLE'),
    'legal_professional_rules' => $pending('LEGAL_PROFESSIONAL_RULES'),
    'legal_tax_pricing_policy' => $pending('LEGAL_TAX_PRICING_POLICY'),
    'privacy_controller' => $pending('PRIVACY_CONTROLLER'),
    'privacy_purposes_and_bases' => $pending('PRIVACY_PURPOSES_AND_BASES'),
    'privacy_recipients' => $pending('PRIVACY_RECIPIENTS'),
    'privacy_retention_criteria' => $pending('PRIVACY_RETENTION_CRITERIA'),
    'privacy_international_transfers' => $pending('PRIVACY_INTERNATIONAL_TRANSFERS'),
    'privacy_rights_contact' => $pending('PRIVACY_RIGHTS_CONTACT'),
    'privacy_dpo_contact' => $pending('PRIVACY_DPO_CONTACT'),
    'privacy_contact_bookings' => $pending('PRIVACY_CONTACT_BOOKINGS'),
    'privacy_client_accounts' => $pending('PRIVACY_CLIENT_ACCOUNTS'),
];
