<?php

namespace App\Chat;

use RuntimeException;

/** El mensaje del visitante no tiene la forma esperada (texto de 1 a 500 caracteres o una acción conocida). */
class InvalidChatInput extends RuntimeException
{
}
