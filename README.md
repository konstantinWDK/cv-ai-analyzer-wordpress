# CV Validator SaaS

**Versión:** 2.3.2  
**Autor:** Konstantin WDK

Plugin para subir y validar currículums en PDF mediante PDFParser, extraer el texto a `.txt`, enviar el contenido a la API de DeepSeek (compatible con OpenAI Chat) para decidir “apto” o “no apto” según taxonomías configurables de industria, especializaciones, posiciones preferidas y valores personales. Cada CV se guarda como Custom Post Type (CPT) con metadatos, columnas administrativas y términos asociados.

## Tabla de contenidos

- [Características](#caracter%C3%ADsticas)
- [Requisitos](#requisitos)
- [Instalación](#instalaci%C3%B3n)
- [Configuración](#configuraci%C3%B3n)
- [Taxonomías](#taxonom%C3%ADas)
- [Importar datos de demostración](#importar-datos-de-demostraci%C3%B3n)
- [Shortcodes](#shortcodes)
- [Uso en back-end](#uso-en-back-end)
- [Uso en front-end](#uso-en-front-end)
- [Notas para desarrolladores](#notas-para-desarrolladores)
- [Changelog](#changelog)
- [Licencia](#licencia)

## Características

- Subida de CV en PDF y extracción de texto con PDFParser.
- Guardado del texto plano en un fichero `.txt` en `uploads`.
- Validación mediante DeepSeek API (OpenAI-compatible Chat): análisis según instrucciones y umbrales de coincidencia.
- CPT `cv` con metadatos:
  - URL de PDF
  - URL de TXT
  - Decisión (“apto” / “no apto”)
  - Razones detalladas
- Taxonomías dinámicas:
  - Industrias
  - Especializaciones
  - Posiciones preferidas
  - Valores personales
- Selector visual de taxonomías con “burbujas” clicables en la página de ajustes.
- Shortcodes para formulario de envío y listado de CVs validados.
- Importación de datos de ejemplo (15 términos en cada taxonomía).

## Requisitos

- WordPress ≥ 5.0
- PHP ≥ 7.4
- Extensión cURL habilitada
- Composer: instalado en `pdfparser/vendor`
- Clave API y endpoint de DeepSeek (o tu propio compatible con OpenAI Chat)

## Instalación

1. Subir el plugin a `/wp-content/plugins/cv-validator-saas`.
2. Activar desde el panel de Plugins de WordPress.
3. Ir a **CV Validator → Ajustes** y configurar:
   - Clave API DeepSeek
   - Endpoint
   - Modelo (p. ej. `deepseek-chat`)
   - Instrucciones del sistema (opcional)
   - Seleccionar industrias, especializaciones, posiciones y valores activos.

## Configuración

En **CV Validator → DeepSeek API y Taxonomías Activas**:

- **DeepSeek API Key:** tu token de autenticación.
- **API Endpoint:** URL del servicio de chat (`https://api.deepseek.com/v1/chat/completions` por defecto).
- **Model:** nombre del modelo (p. ej. `deepseek-chat`).
- **System Instructions:** plantilla de instrucciones con variables:
  ```
  {industria}: nombre de la industria seleccionada.
  {{Especializaciones}}, {{Posiciones}}, {{Valores}}: listas activas.
  ```
- **Industrias / Especializaciones / Posiciones / Valores:** activar o desactivar términos con las “burbujas”.

## Taxonomías

| Taxonomía            | Slug                   | Descripción                                  |
| -------------------- | ---------------------- | -------------------------------------------- |
| Industrias           | `cv_industry`          | Configura el sector para el análisis.       |
| Especializaciones    | `cv_specialization`    | Define skills clave a buscar en el CV.      |
| Posiciones preferidas| `cv_position`          | Roles deseados para el candidato.           |
| Valores personales   | `cv_personal_value`    | Comportamientos o valores a evidenciar.     |

## Importar datos de demostración

En la misma página de ajustes, haz clic en **Importar Demo** para cargar 15 términos de ejemplo en cada taxonomía. Útil para pruebas o demostraciones.

## Shortcodes

- **[cv_validator_form]**

  Muestra un formulario front-end para subir un CV:
  - Selector de Industria
  - Checkboxes de Especializaciones
  - Checkboxes de Posiciones
  - Checkboxes de Valores
  - Input de archivo PDF
  - Procesa el archivo y muestra resultado al usuario.

- **[cv_validator_list]**

  Lista todos los CVs analizados con su decisión:
  ```html
  [cv_validator_list]
  ```
  Genera un `<ul>` con títulos y estado (“Apto” / “No apto”).

## Uso en back-end

1. En el menú lateral, ve a **CV Validator → Analizar CV (Back-end)**.
2. Sube un PDF y pulsa **Analizar CV**.
3. Se procesará el archivo, se extraerá el texto y se enviará a DeepSeek.
4. Aparecerá la Decisión y la Razón.
5. El CV se guardará como CPT `cv` con metadatos y términos asignados.

Para revisar CVs, ve a **CVs** en el menú de administración. Verás columnas de Decisión, Razón, PDF y TXT.

## Uso en front-end

- Inserta el shortcode **[cv_validator_form]** en cualquier página o entrada.
- El usuario elige industria y filtros, sube su CV y obtiene feedback inmediato.
- Usa **[cv_validator_list]** para mostrar un listado público de resultados (`post_status publish`).

## Notas para desarrolladores

- La extracción de texto usa `Smalot\PdfParser\Parser`.
- El plugin crea automáticamente el directorio `uploads/cv-validator-saas-txt`.
- Los metadatos se guardan como:
  - `_cv_pdf_url`
  - `_cv_txt_url`
  - `_cv_decision`
  - `_cv_reason`
- Puedes engancharte a los filtros y acciones de WordPress para extender la lógica.

## Changelog

### 2.3.2

- Corregida la tasa de timeout de la petición a DeepSeek (60 s).
- Ajuste en el estilo de burbujas: tamaño y espaciado.

### 2.3.1

- Mejoras en la extracción de texto de PDFParser.
- Validación adicional de subida de archivos.

### 2.3.0

- Feature: Implementación de shortcodes front-end.
- Feature: Taxonomías dinámicas con burbujas interactivas.

## Licencia

Este proyecto está licenciado bajo la **GPLv2 o superior**.
