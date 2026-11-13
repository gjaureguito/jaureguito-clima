# clima.jaureguitosistemas.com

Página de clima de San Juan con publicidad de Jaureguito Sistemas.

- **Medición actual oficial:** SMN Argentina, estación San Juan (licencia CC BY 2.5 AR).
- **Pronóstico por hora y a 7 días:** MET Norway, api.met.no (licencia CC BY 4.0, uso comercial permitido).
- Las dos fuentes exigen atribución, que ya está en el pie de página. No la saques.
- Los datos se guardan en `cache/` durante 30 minutos. Si una fuente se cae, se muestra el último dato guardado.

## Archivos

| Archivo | Para qué |
|---|---|
| `index.php` | La página |
| `clima.php` | Descarga, caché y procesamiento de datos |
| `estadisticas.php` | Normales 1991-2020 y récords 1967-2025 del SMN (estación San Juan Aero) y datos para los gráficos |
| `estadisticas.js` | Gráficos interactivos: termómetro de hoy, semana vs lo normal y explorador de meses (SVG, sin librerías) |
| `cache/.htaccess` | Bloquea el acceso público a la caché |
| `diagnostico.php` | Prueba de conexión: **borralo después de usarlo** |

## Publicarlo

1. **Hostinger:** en hPanel → Dominios → Subdominios, creá `clima`. Hostinger crea la carpeta `public_html/clima`.
2. **Cloudflare:** en DNS → Registros → Agregar, cargá un registro tipo `CNAME` con nombre `clima` y destino `jaureguitosistemas.com`, con el proxy activado (nube naranja).
3. Subí los archivos a `public_html/clima` (Administrador de archivos o Git). La carpeta `cache` necesita permisos 755.
4. Abrí `https://clima.jaureguitosistemas.com/diagnostico.php`. Tienen que aparecer `MET Norway: OK` y `SMN zip: OK`, y la línea de San Juan parseada. Después borrá el archivo.
5. Agregá el subdominio en Google Search Console.

## Foto de portada

Foto real de la precordillera de San Juan (Sierra del Tontal), de WeHaKa en Wikimedia Commons, con licencia **CC BY-SA 4.0**. Se puede usar con fines comerciales si se cita al autor, y el crédito ya está en el pie de página.

Por defecto la página carga la foto desde Wikimedia. Para que cargue más rápido desde tu propio hosting, descargala dentro de `img/` (en Windows, desde la carpeta del sitio):

```bash
mkdir img
curl -L -o img/san-juan.jpg "https://upload.wikimedia.org/wikipedia/commons/thumb/b/b4/Precordillera_de_San_Juan_Sierra_del_Tontal.jpg/1920px-Precordillera_de_San_Juan_Sierra_del_Tontal.jpg"
```

Si existe `img/san-juan.jpg`, la página la usa sola. Si la cambiás por otra foto, actualizá el crédito del pie de página.

## Si el SMN cambia su formato

Si `diagnostico.php` muestra la línea de San Juan pero el resultado parseado da `NULL`, ajustá la función `parsear_smn()` en `clima.php` según el orden de columnas real. Mientras tanto la página sigue funcionando: solo oculta el bloque del SMN.

## Análisis diario en Python (`analisis/`)

`analisis/calcular.py` (pandas) corre todos los días en GitHub Actions (`.github/workflows/analisis-diario.yml`, 08:17 de San Juan):
descarga el **Registro de temperatura (365 días)** del SMN, guarda la estación San Juan Aero en `datos/historico_san_juan.csv`
(el histórico crece día a día, más allá de los 365 días que publica el SMN) y genera `datos/estadisticas.json`,
que la página muestra en la sección "El último año en San Juan". El commit del workflow dispara el despliegue de Hostinger.

Para correrlo a mano:

```bash
pip install -r analisis/requirements.txt
python analisis/calcular.py
```

O desde GitHub: pestaña Actions → "Análisis diario del clima" → Run workflow.
