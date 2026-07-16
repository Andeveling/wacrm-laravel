# 0001 — Adoptar el patrón ADR (Action / Domain / Responder)

- **Estado:** Aceptado
- **Fecha:** 2026-07-16
- **Contexto:** wacrm (subproyecto Laravel 13 + Inertia + Fortify + Reverb)

## Contexto

A medida que el backend crece, los controllers de Laravel empiezan a acumular responsabilidades: validan, autorizan, ejecutan reglas de negocio, manipulan modelos y deciden el response. Eso vuelve difícil saber qué pertenece a HTTP, qué al negocio y qué es puro formato de respuesta.

Este proyecto además usa Inertia, que ya separa la capa de presentación en el front (React). La capa backend debería tener una separación equivalente.

## Decisión

Adoptamos el patrón **ADR** (Action / Domain / Responder) según la definición de Wendell Adriel ([artículo de referencia](https://wendelladriel.com/blog/using-the-adr-action-domain-responder-pattern-in-laravel)) para los **features nuevos** del proyecto.

### Estructura de carpetas

```
app/Domain/
└── <Contexto>/                     # bounded context (ej. Articles, Contacts, Billing)
    ├── Actions/
    │   └── PublicarArticulo.php    # clase final, una responsabilidad
    ├── Responders/
    │   └── PublicarArticuloResponder.php
    ├── Results/
    │   └── PublicarArticuloResultado.php  # value object con estado
    └── Support/
        └── EstadoArticulo.php      # enums del dominio
```

### Responsabilidades

| Capa                                            | Hace                                                                                            | NO hace                                                       |
| ----------------------------------------------- | ----------------------------------------------------------------------------------------------- | ------------------------------------------------------------- |
| **Action** (`__invoke`)                         | Recibe request, resuelve FormRequest si aplica, llama al Domain, pasa el resultado al Responder | Reglas de negocio, armar responses, hablar con varios modelos |
| **Domain** (`App\Domain\<X>\Actions\<Y>`)       | Ejecuta las reglas del negocio, devuelve un Result con un enum de estado                        | Conocer HTTP, decidir redirects o renders                     |
| **Responder** (`App\Domain\<X>\Responders\<Y>`) | Mapea Result → `Inertia::render(...)`, `redirect()->route(...)` o `response()->json(...)`       | Re-evaluar reglas, hablar con el modelo                       |

### Convenciones

1. **Action** = invokable controller, clase `final`, una sola responsabilidad pública.
2. **Domain** devuelve un **Result object** (`readonly class`) con un enum de estado (ej. `PublicarArticuloResultado { public function __construct(public EstadoArticulo $estado, public Articulo $articulo, public ?string $mensaje = null) {} }`). Nada de booleanos para flujos no triviales.
3. **Responder** se inyecta en la Action vía container. Si el result tiene `estado::Publicado` → redirect a `articles.show` con flash; si `estado::Error` → `back()->withErrors()`.
4. **Inertia encaja perfecto**: el Responder devuelve `Inertia::render('Articles/Show', ['article' => ...])`.
5. **Validación**: seguimos usando `FormRequest` (en `app/Http/Requests/`) — es el input boundary, no rompe ADR.

### Alcance

- **Aplica a:** nuevos features y nuevos endpoints a partir de este ADR.
- **NO aplica a:** controllers ya existentes (`Settings/ProfileController`, `Settings/SecurityController`). Se migrarán solo si se vuelven a tocar (regla "gradual al tocar", fuera de este ADR).
- **Fortify:** `app/Actions/Fortify/*` no se mueve. Fortify ya impone su propio contrato (devuelven `User`); reescribirlos a ADR no aporta.
- **Jobs / Listeners / Commands** quedan fuera del scope de este ADR.

### Reglas prácticas

Hereda las cuatro reglas de Wendell:

1. Si la Action empieza a crecer ramas o condicionales, estás filtrando lógica de negocio hacia HTTP.
2. Si el Domain devuelve `bool`, probablemente el flujo tiene más estado del que cabe ahí.
3. Si el Responder decide reglas, también filtra — transport solo.
4. Si un endpoint es solo "renderizar una página" o "actualizar un campo sin reglas", ADR completo es overkill. Un `__invoke` que devuelve `Inertia::render(...)` directamente es válido y preferible.

## Consecuencias

### Positivas

- Controllers nuevos quedan con 5–15 líneas por defecto. La intención del código es legible en una pantalla.
- Las reglas de negocio son testeables sin instanciar el container de Laravel (el Domain es PHP puro).
- El Responder centraliza el formato: cambiar de Inertia a JSON para una API es un solo archivo.
- Forzar Result objects + enums reduce bugs por estados implícitos.

### Negativas / Costos

- Más archivos por feature: 3–4 clases en lugar de 1 controller con un método.
- Onboarding: contribuidores LATAM que vienen de MVC clásico tienen que aprender el patrón. La barrera se mitiga con este ADR y un ejemplo en `docs/` o en el `CONTEXT.md`.
- `app/Actions/Fortify/*` y `app/Domain/<Contexto>/Actions/*` (nuevos) **convivirán**: hay que distinguir "Action de Fortify" de "Action de ADR". Convención: **las ADR viven en `app/Domain/<Contexto>/Actions/`**, no en `app/Actions/`.

### Neutras

- La decisión es **reversible**: si ADR agrega fricción sin beneficio para flujos simples, se revisa con un ADR nuevo (no editando este).
- No cambiamos dependencias ni versiones.

## Referencias

- Wendell Adriel, _Using the ADR (Action/Domain/Responder) Pattern in Laravel_ — <https://wendelladriel.com/blog/using-the-adr-action-domain-responder-pattern-in-laravel>
- Laravel Fortify Actions — <https://laravel.com/docs/fortify#defining-actions> (referencia para distinguir de ADR Actions)
- ADR template — Michael Nygard (formato estándar de este documento)
