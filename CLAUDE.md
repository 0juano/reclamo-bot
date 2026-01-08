# Reclamo Bot

## Municipios
- Emails municipales usan dominio `.gob.ar` (no `.com.ar`)
- "Completo" = datos verificados en web oficial
- "Verificado" = municipio respondió a email de prueba

## Tiempos de Respuesta (emails enviados 8-Ene-2026 15:43hs ARG)
| Municipio | Respuesta | Tiempo |
|-----------|-----------|--------|
| Tigre | 16:19 | 36 min |
| Lomas de Zamora | 18:50 | 3h 7m |
- Muchos sitios municipales tienen SSL expirado - usar http:// como fallback al verificar
- Para verificar respuestas: buscar en Gmail `from:gob.ar` o `from:gov.ar` + `subject:consulta`

## JSON Files
- Template en `municipios/_template.json`
- `verificado: true` en JSON significa datos completos, no respuesta confirmada
