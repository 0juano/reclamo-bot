# 🇦🇷 Reclamo Bot

Hacé reclamos a tu municipio de forma rápida. La IA escribe una carta formal por vos.

Fork argentino de [Karen Bot](https://gist.github.com/levelsio/b4467fd2fb63bc5373fd3e8559d5ad62) de [@levelsio](https://twitter.com/levelsio).

## Cómo funciona

1. Seleccionás tu municipio
2. Marcás la ubicación del problema en el mapa
3. Describís el problema en tus palabras
4. La IA genera una carta formal
5. Se envía por email al municipio

## Municipios disponibles

| Municipio | Provincia | Email | Estado |
|-----------|-----------|-------|--------|
| Tigre | Buenos Aires | sirve@tigre.gob.ar | ✅ Verificado |
| Buenos Aires Ciudad | CABA | (usar app BA 147) | ⚠️ Sin email directo |

## Instalación

### Requisitos

- PHP 7.4+ con GD
- Servidor web (Nginx/Apache)
- Cuenta en [Resend](https://resend.com) (gratis hasta 3000 emails/mes)
- API key de [OpenAI](https://platform.openai.com)

### Pasos

1. Cloná el repo:
```bash
git clone https://github.com/TU_USUARIO/reclamo-bot.git
```

2. Configurá las variables en `index.php`:
```php
define('KEY_TO_ACCESS_THE_SCRIPT', 'tu_clave_secreta');
define('RESEND_API_KEY', 'tu_api_key_de_resend');
define('OPENAI_API_KEY', 'tu_api_key_de_openai');
define('YOUR_NAME', 'Tu Nombre');
define('FROM_YOUR_EMAIL', 'tu@email.com');
```

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

## Estructura del proyecto

```
reclamo-bot/
├── index.php           # Aplicación principal
├── municipios/
│   ├── _template.json  # Template para nuevos municipios
│   ├── tigre.json
│   └── buenos-aires.json
└── README.md
```

## Stack

- **Backend**: PHP
- **Mapa**: Leaflet.js + OpenStreetMap
- **IA**: OpenAI GPT-4o-mini
- **Email**: Resend

## Contribuir

PRs bienvenidos! Especialmente para:

- 🏛️ Agregar nuevos municipios
- 🐛 Corregir bugs
- 🌐 Mejorar la UI
- 📝 Mejorar el prompt de la carta

## Créditos

- Idea original: [@levelsio](https://twitter.com/levelsio)
- Adaptación Argentina: [@juanote](https://twitter.com/juanote)

## Licencia

MIT - Usalo como quieras.
