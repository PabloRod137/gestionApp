<?php

// Textos del asistente público de la web. Solo información ya publicada. Editable por el despacho.
// Los huecos :phone, :email y :hours se rellenan con config/site.php.
return [
    'greeting' => 'Hola, soy el asistente de Nortia. Puedo orientarte sobre nuestros servicios, precios, horario y cómo reservar una cita.',
    'menu_text' => '¿Sobre qué quieres información?',
    'menu_buttons' => [
        ['id' => 'services', 'label' => 'Servicios'],
        ['id' => 'prices', 'label' => 'Precios de las consultas'],
        ['id' => 'booking', 'label' => 'Reservar cita'],
        ['id' => 'hours', 'label' => 'Horario'],
        ['id' => 'contact', 'label' => 'Contacto'],
        ['id' => 'client_area', 'label' => 'Área Cliente'],
        ['id' => 'lead', 'label' => 'Que me contacte el despacho'],
    ],
    'fallback' => 'No he entendido bien tu pregunta. Puedes elegir una opción del menú o dejar tus datos para que el despacho te contacte.',
    'fallback_buttons' => [
        ['id' => 'menu', 'label' => 'Ver el menú'],
        ['id' => 'lead', 'label' => 'Que me contacte el despacho'],
    ],
    'services' => 'Trabajamos en seis áreas: legal (con compliance y protección de datos), fiscal, laboral, comunidades de propietarios, inmobiliario (inmuebles y vehículos) y seguros.',
    'prices_note' => 'Los importes son orientativos y el despacho los confirma al recibir tu solicitud.',
    'booking' => 'Puedes reservar una cita online eligiendo servicio, modalidad, día y hora. La solicitud queda pendiente de confirmación por el despacho.',
    'hours' => 'Nuestro horario es :hours. Para reservar, elige un hueco disponible en el calendario de reserva de cita.',
    'contact' => 'Puedes llamarnos al :phone o escribirnos a :email. También tienes el formulario de contacto en la web. Estamos en Santander, Cantabria.',
    'client_area' => 'El Área Cliente es tu espacio privado: allí consultas tus citas y, una vez identificado, tus expedientes y documentos, y puedes crear incidencias.',
    'lead' => [
        'ask_name' => 'Con gusto. ¿Cómo te llamas?',
        'retry_name' => 'Necesito un nombre de entre 2 y 100 letras. ¿Cómo te llamas?',
        'ask_contact' => 'Indica un teléfono o un correo para que el despacho pueda contactarte.',
        'retry_contact' => 'No he reconocido ese dato. Escribe un teléfono (9 dígitos o más) o un correo electrónico.',
        'ask_consent' => 'Para guardar tus datos necesito tu consentimiento: los usaremos solo para responder a tu solicitud, tal y como explica la política de privacidad.',
        'consent_button' => 'Acepto la política de privacidad y que el despacho me contacte',
        'restart' => 'Empecemos de nuevo. ¿Cómo te llamas?',
        'done' => 'Gracias, :name. El despacho te contactará en cuanto pueda. Referencia: :reference.',
    ],
    'links' => [
        'services' => ['title' => 'Servicios', 'href' => '/servicios'],
        'booking' => ['title' => 'Reservar cita', 'href' => '/reservar-cita'],
        'contact' => ['title' => 'Formulario de contacto', 'href' => '/contacto'],
        'client_area' => ['title' => 'Área Cliente', 'href' => '/login'],
        'privacy' => ['title' => 'Política de privacidad', 'href' => '/politica-de-privacidad'],
    ],

    // Asistente privado del Área Cliente (sin IA): solo trata los datos del propio cliente. Editable por el despacho.
    'cliente' => [
        'greeting' => 'Hola, :name. Soy el asistente de tu área privada. Puedo ayudarte con tus gestiones en el despacho: cómo van tus expedientes, tus documentos, lo que falta por aportar, tus citas y facturas, y pedir certificados. No doy asesoramiento jurídico: para eso, escribe a tu asesor.',
        'menu_text' => '¿Qué quieres consultar?',
        'menu_buttons' => [
            ['id' => 'expedientes', 'label' => 'Mis expedientes'],
            ['id' => 'documentos', 'label' => 'Mis documentos'],
            ['id' => 'pendiente', 'label' => 'Qué falta aportar'],
            ['id' => 'citas', 'label' => 'Mis citas'],
            ['id' => 'facturas', 'label' => 'Mis facturas'],
            ['id' => 'certificado', 'label' => 'Pedir un certificado'],
            ['id' => 'mensaje', 'label' => 'Escribir a mi asesor'],
        ],
        'fallback' => 'Con eso no puedo ayudarte desde aquí: solo trato tus datos y gestiones en el despacho. Elige una opción o escribe a tu asesor.',
        'sin_expediente' => 'Todavía no tienes un expediente abierto, así que no puedo dejarle el mensaje a nadie. Llámanos al :phone o escríbenos a :email.',
        'pregunta_certificado' => '¿Qué certificado necesitas y para qué? Escríbelo en un mensaje y se lo paso a tu asesor. Lo prepara y lo revisa una persona del despacho.',
        'pregunta_mensaje' => 'Escribe tu mensaje y se lo paso a tu asesor. Te responderá en el chat de tu expediente.',
        'hecho_certificado' => 'Hecho. He dejado tu solicitud a tu asesor. El certificado lo prepara y lo revisa una persona del despacho; te avisarán cuando esté.',
        'hecho_mensaje' => 'Hecho. He dejado tu mensaje a tu asesor. Te responderá en el chat de tu expediente.',
    ],
];
