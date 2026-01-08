# 🇦🇷 Reclamo Bot

Hacé reclamos a tu municipio de forma rápida. La IA escribe una carta formal por vos.

Fork argentino de [Karen Bot](https://gist.github.com/levelsio/b4467fd2fb63bc5373fd3e8559d5ad62) de [@levelsio](https://twitter.com/levelsio).

## Cómo funciona

1. Seleccionás tu municipio
2. Marcás la ubicación del problema en el mapa
3. Describís el problema en tus palabras
4. La IA genera una carta formal
5. Se envía por email al municipio

## Municipios disponibles (20)

| Municipio | Provincia | Contacto | Estado |
|-----------|-----------|----------|--------|
| Buenos Aires Ciudad | CABA | 147 / App BA 147 | ⚠️ Sin email |
| La Matanza | Buenos Aires | matanzaresponde@lamatanza.gov.ar | ✅ Completo |
| Córdoba | Córdoba | atencionvecinal@cordoba.gob.ar | ✅ Completo |
| Rosario | Santa Fe | WhatsApp 341-544-0147 | ⚠️ Sin email |
| La Plata | Buenos Aires | 147 / App MiLP | ⚠️ Sin email |
| Lomas de Zamora | Buenos Aires | reclamoscav@lomasdezamora.gov.ar | ✅ Verificado |
| General Pueyrredón | Buenos Aires | consultas@mardelplata.gob.ar | ✅ Completo |
| Quilmes | Buenos Aires | reclamos@quilmes.gob.ar | ✅ Completo |
| Salta | Salta | consumidor.municipal@municipalidadsalta.gob.ar | ✅ Completo |
| San Miguel de Tucumán | Tucumán | 0800-555-8222 / App Ciudad SMT | ⚠️ Sin email |
| Almirante Brown | Buenos Aires | reclamos@brown.gob.ar | ✅ Completo |
| Merlo | Buenos Aires | (0220) 483-0954 | ⚠️ Sin email |
| Moreno | Buenos Aires | reclamos@moreno.gob.ar | ✅ Completo |
| Santa Fe | Santa Fe | informes@santafeciudad.gov.ar | ✅ Completo |
| Lanús | Buenos Aires | centrodeatencion@lanus.gob.ar | ✅ Completo |
| Florencio Varela | Buenos Aires | contacto@varela.gob.ar | ✅ Completo |
| San Isidro | Buenos Aires | sanisidro@sanisidro.gob.ar | ✅ Completo |
| Tigre | Buenos Aires | sirve@tigre.gob.ar | ✅ Verificado |
| Malvinas Argentinas | Buenos Aires | info@malvinasargentinas.gob.ar | ✅ Completo |
| Vicente López | Buenos Aires | 147 / App MiBarrio | ⚠️ Sin email |

**Estados:**
- ✅ **Verificado**: El municipio respondió a un email de prueba
- ✅ **Completo**: Datos de contacto confirmados en web oficial
- ⚠️ **Sin email**: Solo tiene teléfono/app, no acepta emails

## Instalación

### Requisitos

- PHP 7.4+ con GD
- Servidor web (Nginx/Apache)
- Cuenta en [Resend](https://resend.com) (gratis hasta 3000 emails/mes)
- API key de [OpenRouter](https://openrouter.ai) (recomendado) o [OpenAI](https://platform.openai.com)

### Pasos

1. Cloná el repo:
```bash
git clone https://github.com/0juano/reclamo-bot.git
```

2. Configurá las variables de entorno (recomendado) o editá `index.php`:

**Opción A: Variables de entorno (recomendado)**
```bash
export RECLAMO_ACCESS_KEY="tu_clave_secreta"
export RESEND_API_KEY="tu_api_key_de_resend"
export LLM_PROVIDER="openrouter"  # o 'openai'
export OPENROUTER_API_KEY="tu_api_key"
export OPENROUTER_MODEL="anthropic/claude-3.5-haiku"
export RECLAMO_YOUR_NAME="Tu Nombre"
export RECLAMO_FROM_EMAIL="tu@email.com"
export RECLAMO_CC_EMAILS="copia@email.com"  # Opcional, separar con comas
```

**Opción B: Editar index.php directamente**
```php
define('KEY_TO_ACCESS_THE_SCRIPT', 'tu_clave_secreta');
define('RESEND_API_KEY', 'tu_api_key_de_resend');

// Elegí tu provider de IA (openrouter es más barato)
define('LLM_PROVIDER', 'openrouter');  // o 'openai'

// Si usás OpenRouter (recomendado)
define('OPENROUTER_API_KEY', 'tu_api_key');
define('OPENROUTER_MODEL', 'anthropic/claude-3.5-haiku');

// Si usás OpenAI
define('OPENAI_API_KEY', 'tu_api_key');
define('OPENAI_MODEL', 'gpt-4o-mini');

define('YOUR_NAME', 'Tu Nombre');
define('FROM_YOUR_EMAIL', 'tu@email.com');
```

### Modelos recomendados (OpenRouter)

| Modelo | Costo aprox. | Notas |
|--------|--------------|-------|
| `anthropic/claude-3.5-haiku` | ~$0.001/carta | Rápido, buena calidad |
| `openai/gpt-4o-mini` | ~$0.001/carta | Buena alternativa |
| `google/gemini-flash-1.5` | ~$0.0005/carta | Más barato |

3. Configurá tu dominio en Resend para poder enviar emails

4. Agregá una ruta en tu servidor:
```nginx
location /reclamo {
    alias /path/to/reclamo-bot;
    index index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $request_filename;
        include fastcgi_params;
    }
}
```

5. Accedé a `tudominio.com/reclamo?key=tu_clave_secreta`

## Seguridad y validación

### Archivos adjuntos
- **Formatos permitidos**: JPG, PNG, GIF, WebP
- **Tamaño máximo**: 1MB por imagen (se comprime automáticamente)
- **Cantidad máxima**: 20 archivos
- **Validación**: Extensión + verificación real de imagen con `getimagesize()`

### Límites de entrada
- **Descripción del reclamo**: máximo 10.000 caracteres
- **Dirección**: máximo 500 caracteres
- **Coordenadas**: latitud -90 a 90, longitud -180 a 180

### Protecciones implementadas
- Autenticación con comparación timing-safe (`hash_equals`)
- Prevención XSS con `htmlspecialchars()` y `json_encode()`
- Validación de email contra whitelist de municipios configurados
- Variables de entorno para secrets (no hardcodeados)

## Agregar municipio

¿Tu municipio no está? ¡Agregalo!

1. Forkeá este repo
2. Copiá `municipios/_template.json` a `municipios/nombre-municipio.json`
3. Completá los datos:

```json
{
  "nombre": "Municipalidad de San Isidro",
  "provincia": "Buenos Aires",
  "email": "reclamos@sanisidro.gob.ar",
  "telefono": "0800-XXX-XXXX",
  "web": "https://www.sanisidro.gob.ar",
  "mapa_centro": [-34.4708, -58.5276],
  "mapa_zoom": 13,
  "tipos_reclamo": ["bacheo", "luminarias", "poda"],
  "notas": "Información útil sobre el proceso",
  "horarios": "Lun-Vie 8-18hs",
  "verificado": false,
  "ultima_actualizacion": "2025-01-08",
  "contribuido_por": "@tu_usuario"
}
```

4. Hacé un PR

### ¿Cómo encontrar el email de reclamos?

- Buscá en Google: `[nombre municipio] reclamos email`
- Revisá la web oficial del municipio
- Llamá al número de atención al vecino y preguntá

### Coordenadas del mapa

Podés obtener las coordenadas desde Google Maps:
1. Buscá el centro de tu ciudad
2. Click derecho → "¿Qué hay aquí?"
3. Copiá las coordenadas (ej: -34.4708, -58.5276)

## Testing

Correr los tests:
```bash
# Instalar dependencias
composer install

# Correr tests
composer test

# O con Docker (sin PHP local)
docker run --rm -v $(pwd):/app -w /app composer:latest install
docker run --rm -v $(pwd):/app -w /app php:8.2-cli vendor/bin/phpunit
```

### Cobertura de tests (26 tests, 83+ assertions)

| Función | Tests | Qué valida |
|---------|-------|------------|
| `getMunicipios()` | 4 | Carga JSON, ordena alfabéticamente, maneja errores |
| `extractSubject()` | 5 | Extrae "Asunto:" o trunca a 60 chars |
| `parseCcEmails()` | 4 | Parsea emails separados por coma |
| `formatAttachmentMessage()` | 3 | Pluralización correcta (foto/fotos) |
| `validateCoordinates()` | 6 | Rangos válidos lat/lng |
| `resizeImage()` | 4 | Compresión y escalado de imágenes |

## Estructura del proyecto

```
reclamo-bot/
├── index.php           # Aplicación principal
├── src/
│   └── functions.php   # Funciones reutilizables
├── tests/
│   └── ReclamoBotTest.php
├── municipios/
│   ├── _template.json  # Template para nuevos municipios
│   ├── tigre.json
│   └── buenos-aires.json
├── composer.json
├── phpunit.xml
└── README.md
```

## Stack

- **Backend**: PHP 7.4+
- **Mapa**: Leaflet.js + OpenStreetMap
- **IA**: OpenRouter (Claude, GPT, Gemini) o OpenAI directo
- **Email**: Resend
- **Tests**: PHPUnit 10

## Manejo de errores

- **Logs**: Errores se loguean en PHP error log (no se muestran al usuario)
- **API failures**: Errores de LLM/Resend se loguean con contexto
- **Validación**: Inputs inválidos retornan mensajes claros en español
- **Red**: Fallos de cURL se detectan y reportan separado de errores HTTP

## Contribuir

PRs bienvenidos! Especialmente para:

- 🏛️ Agregar nuevos municipios
- 🐛 Corregir bugs
- 🌐 Mejorar la UI
- 📝 Mejorar el prompt de la carta

## Créditos

- Idea original: [@levelsio](https://x.com/levelsio)
- Adaptación Argentina: [@0juano](https://x.com/0juano)

## Licencia

MIT - Usalo como quieras.
