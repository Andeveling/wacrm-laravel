<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Mensajes de error del runtime de autenticación (Fortify).
    |--------------------------------------------------------------------------
    */

    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña es incorrecta.',
    'throttle' => 'Demasiados intentos de acceso. Por favor intente nuevamente en :seconds segundos.',

    /*
    |--------------------------------------------------------------------------
    | UI del módulo de autenticación — paridad con la versión Next.js.
    |--------------------------------------------------------------------------
    | Estas claves se exponen a la capa Inertia como props para que las
    | páginas React puedan renderizar copy en español sin acoplarse a
    | `trans()` en el cliente.
    */

    'ui' => [

        'login' => [
            'title_welcome' => 'Bienvenido de nuevo',
            'title_accept_invite' => 'Inicia sesión para aceptar',
            'description_welcome' => 'Inicia sesión en tu cuenta',
            'description_accept' => 'Inicia sesión y te llevaremos a la invitación.',
            'email_label' => 'Correo electrónico',
            'email_placeholder' => 'tu@ejemplo.com',
            'password_label' => 'Contraseña',
            'forgot_link' => '¿Olvidaste tu contraseña?',
            'signing_in' => 'Iniciando sesión...',
            'sign_in_button' => 'Iniciar sesión',
            'no_account' => '¿No tienes una cuenta?',
            'create_account' => 'Crear cuenta',
            'or_continue_with' => 'O CONTINÚA CON CORREO',
            'passkey_button' => 'Iniciar sesión con passkey',
        ],

        'register' => [
            'title_default' => 'Crear cuenta',
            'title_with_invite' => 'Crear cuenta y unirse',
            'description_default' => 'Comienza con CRM Template para WhatsApp',
            'description_with_invite' => 'Verifica tu correo y luego acepta la invitación para unirte a tu equipo.',
            'full_name_label' => 'Nombre completo',
            'full_name_placeholder' => 'Juan Pérez',
            'email_label' => 'Correo electrónico',
            'email_placeholder' => 'tu@ejemplo.com',
            'password_label' => 'Contraseña',
            'password_placeholder' => 'Mínimo 6 caracteres',
            'password_confirmation_label' => 'Confirmar contraseña',
            'password_confirmation_placeholder' => 'Repite tu contraseña',
            'creating_account' => 'Creando cuenta...',
            'create_account_button' => 'Crear cuenta',
            'already_have_account' => '¿Ya tienes una cuenta?',
            'sign_in' => 'Iniciar sesión',
            'success_title' => 'Revisa tu correo',
            'success_body' => 'Hemos enviado un enlace de confirmación a :email. Revisa tu bandeja de entrada y haz clic en el enlace para verificar tu cuenta.',
            'back_to_sign_in' => 'Volver a iniciar sesión',
            'invite_invalid' => 'Esta invitación es inválida o ha expirado.',
            'passwords_do_not_match' => 'Las contraseñas no coinciden.',
            'password_too_short' => 'La contraseña debe tener al menos 6 caracteres.',
        ],

        'forgot_password' => [
            'title' => 'Restablecer contraseña',
            'description' => 'Ingresa tu correo y te enviaremos un enlace de restablecimiento',
            'email_label' => 'Correo electrónico',
            'email_placeholder' => 'tu@ejemplo.com',
            'sending' => 'Enviando...',
            'send_link_button' => 'Enviar enlace de restablecimiento',
            'back_to_sign_in' => 'Volver a iniciar sesión',
            'success_title' => 'Revisa tu correo',
            'success_body' => 'Hemos enviado un enlace de restablecimiento de contraseña a :email. Revisa tu bandeja de entrada.',
        ],

        'reset_password' => [
            'title' => 'Restablecer contraseña',
            'description' => 'Ingresa tu nueva contraseña a continuación',
            'email_label' => 'Correo electrónico',
            'password_label' => 'Contraseña',
            'password_placeholder' => 'Contraseña',
            'password_confirmation_label' => 'Confirmar contraseña',
            'password_confirmation_placeholder' => 'Confirmar contraseña',
            'reset_button' => 'Restablecer contraseña',
        ],

        'verify_email' => [
            'title' => 'Verificación de correo',
            'description' => 'Verifica tu dirección de correo haciendo clic en el enlace que te acabamos de enviar.',
            'resend_button' => 'Reenviar correo de verificación',
            'log_out' => 'Cerrar sesión',
            'resent' => 'Se ha enviado un nuevo enlace de verificación a la dirección de correo que proporcionaste durante el registro.',
        ],

        'two_factor' => [
            'auth_code_title' => 'Código de autenticación',
            'auth_code_description' => 'Ingresa el código de autenticación proporcionado por tu aplicación autenticadora.',
            'recovery_code_title' => 'Código de recuperación',
            'recovery_code_description' => 'Confirma el acceso a tu cuenta ingresando uno de tus códigos de recuperación de emergencia.',
            'recovery_code_placeholder' => 'Ingresa código de recuperación',
            'continue_button' => 'Continuar',
            'use_recovery_code' => 'iniciar sesión con un código de recuperación',
            'use_authentication_code' => 'iniciar sesión con un código de autenticación',
            'or_you_can' => 'o puedes',
        ],

        'confirm_password' => [
            'title' => 'Confirmar contraseña',
            'description' => 'Esta es un área segura de la aplicación. Por favor confirma tu contraseña antes de continuar.',
            'password_label' => 'Contraseña',
            'password_placeholder' => 'Contraseña',
            'confirm_button' => 'Confirmar contraseña',
            'or_confirm_with_passkey' => 'O confirma con passkey',
            'confirming_passkey' => 'Confirmando...',
            'separator' => 'O confirma con contraseña',
        ],

        'invitation_preview' => [
            'loading' => 'Verificando invitación...',
            'valid_title' => 'Has sido invitado',
            'valid_description' => 'Revisa los detalles a continuación, luego regístrate para unirte.',
            'valid_account' => 'Cuenta',
            'valid_invited_by' => 'Invitado por',
            'valid_role' => 'Rol',
            'valid_expires' => 'Expira',
            'valid_accept_register' => 'Aceptar y registrarse',
            'used_title' => 'Esta invitación ya fue utilizada',
            'used_description' => 'Pide a la persona que la envió que emita una nueva.',
            'expired_title' => 'Esta invitación ha expirado',
            'expired_description' => 'Pide a la persona que la envió que emita una nueva.',
            'invalid_title' => 'Invitación no encontrada',
            'invalid_description' => 'Este enlace no coincide con una invitación válida. Verifica la URL o pide a quien te invitó que envíe una nueva.',
            'unknown_title' => 'Algo salió mal',
            'unknown_description' => 'No pudimos verificar esta invitación ahora. Intenta actualizar la página en un momento.',
            'try_again' => 'Intentar de nuevo',
            'create_account_instead' => 'Crear una cuenta nueva',
            'sign_in_instead' => 'Iniciar sesión',
            'signed_in_title' => 'Te uniste a :account',
            'signed_in_subtitle' => 'Aceptar mueve tu inicio de sesión a :account. Tu cuenta personal vacía será eliminada.',
            'accept_invitation_button' => 'Aceptar invitación',
            'accepting' => 'Aceptando...',
            'create_and_join_button' => 'Crear cuenta y unirse',
            'already_have_account_button' => 'Ya tengo una cuenta',
            'conflict_title' => 'No puedes unirte a :account con esta cuenta',
            'conflict_body' => 'Para unirte a :account, cierra sesión y regístrate de nuevo con una dirección de correo diferente. El enlace de invitación sigue siendo válido mientras no haya expirado.',
            'stay_signed_in' => 'Permanecer conectado',
            'sign_out_and_use_different' => 'Cerrar sesión y usar otro correo',
            'signing_out' => 'Cerrando sesión...',
            'redeem_failed_toast' => 'No se pudo aceptar la invitación',
            'redeem_reached_toast' => 'Te uniste al equipo',
            'sign_out_failed_toast' => 'No se pudo cerrar sesión. Intenta actualizar la página.',
            'already_member' => 'Ya eres miembro de otra cuenta. Cierra sesión e inicia sesión con un correo diferente para unirte a esta cuenta.',
            'role_admin' => 'Administrador',
            'role_agent' => 'Agente',
            'role_viewer' => 'Observador',
            'role_owner' => 'Propietario',
        ],
    ],
];
