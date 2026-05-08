# Guía de Estilo Visual del Sistema de Información Recepted

**Trabajo de Fin de Grado**

Grado en Ingeniería Informática

Mayo de 2026

---

## Resumen

El presente documento constituye la guía de estilo visual del sistema de información Recepted, desarrollado como Trabajo de Fin de Grado. En él se describen de forma sistemática los criterios de diseño que rigen la interfaz de usuario de las 12 páginas que componen la aplicación: *landing*, *index*, *login*, *register*, *home*, *finanzas*, *perfil*, *config*, *mis_uploads*, *admin_panel*, *tickets* y *superadmin_console*. La guía abarca el sistema cromático, la tipografía, el espaciado, los componentes reutilizables y las directrices de diseño adaptativo y accesibilidad. Su propósito es garantizar la coherencia visual del sistema y servir de referencia para el mantenimiento y la evolución futura de la interfaz.

**Palabras clave:** diseño de interfaz, sistema cromático, tipografía web, accesibilidad, diseño adaptativo.

---

---

## 1. Introducción

La consistencia visual en los sistemas de información web constituye un factor determinante tanto para la experiencia de usuario como para la mantenibilidad del código. La presente guía establece los estándares visuales del proyecto Recepted, definiendo los elementos gráficos fundamentales que garantizan una interfaz coherente en todas sus páginas.

El documento se organiza conforme a los principales aspectos del diseño de interfaz: sistema cromático, tipografía, espaciado, componentes de interfaz, variables CSS, diseño adaptativo y normas de implementación. Cada sección describe los valores concretos adoptados en el proyecto y su justificación funcional.

---

## 2. Sistema Cromático

El sistema cromático del proyecto se estructura en dos categorías: los colores constitutivos de la identidad visual de la marca y los colores funcionales asociados a elementos específicos de la interfaz.

---

### Colores básicos de la marca

Estos colores definen la identidad visual de Recepted y aparecen en los elementos de mayor visibilidad: acciones principales, navegación y acentos generales.

#### 2.1. Color Primario y Variantes

*Tabla 1*

*Paleta de color primario y sus variantes de estado*

| Azul principal | `#3540E9` | Botones primarios, enlaces activos, encabezados de finanzas |
| Azul oscuro — *hover* | `#2A31C4` | Estado *hover* de botones primarios |
| Azul profundo — activo | `#1e2690` | Estado presionado en botones del módulo de análisis |
| Azul violáceo | `#5F6BFF` | Gradientes y acentos secundarios |
| Azul claro — acento | `#85B4FF` | Fondo de *badges* y etiquetas de tarjeta |
| Azul claro — *hover* de acento | `#6FA8FF` | Estado *hover* del acento azul claro |

#### 2.2. Colores de Navegación

*Tabla 2*

*Paleta del sistema de navegación*

| Fondo principal del *navbar* (Bootstrap *dark*) | `#212529` | Fondo visible del *navbar* en su estado base |
| Texto base del *navbar* | `#f8f9fa` | Texto e iconografía en enlaces y botones del *navbar* |
| Texto en *hover* del *navbar* | `#ffffff` | Color de texto en interacción (*hover*/*focus*) |
| Fondo *hover* de navegación | `rgba(255, 255, 255, 0.15)` | Fondo semitransparente en enlaces y botones del *navbar* |
| Borde del botón hamburguesa | `rgba(255, 255, 255, 0.5)` | Borde del control `navbar-toggler` en estado base |

#### 2.3. Acento Verde Lateral

*Tabla 3*

*Paleta de acento verde y variantes de opacidad*

| Verde lateral | `#3FA565` | Acento alternativo en componentes laterales |
| Verde suave — fondo | `rgba(63, 165, 101, 0.10)` | Fondos suaves con tonalidad verde |
| Verde luminoso — sombra | `rgba(63, 165, 101, 0.28)` | Sombras con efecto luminoso verde |
| Cyan suave — fondo | `rgba(89, 202, 227, 0.10)` | Fondos suaves con tonalidad cyan |
| Cyan luminoso — sombra | `rgba(89, 202, 227, 0.28)` | Sombras con efecto luminoso cyan |

#### 2.4. Acento Decorativo

El color dorado `#C9A84C` se emplea exclusivamente en la página *landing* como elemento decorativo en separadores de sección. Su uso está restringido a dicho contexto para preservar su carácter diferenciador.

---

### Otros elementos de color

Colores funcionales asociados a estados de sistema, componentes concretos y contextos de uso específicos.

#### 2.5. Indicadores de Estado y Retroalimentación

*Tabla 4*

*Colores de retroalimentación visual por tipo de estado*

| Éxito | `#4CAF50` | `#E8F5E9` | `#2E7D32` | Borde `#6ee7b7` / texto `#065f46` / fondo `#d1fae5` |
| Error | `#F44336` | `#FFEBEE` | `#C62828` | Borde `#fca5a5` / texto `#991b1b` / fondo `#fee2e2` |
| Advertencia | `#FFC107` | `#FFF3E0` | `#E65100` | — |
| Información | `#3540E9` | `#E3F2FD` | `#1565C0` | — |

#### 2.6. Representación de Datos Financieros

*Tabla 5*

*Colores del módulo de análisis financiero*

| Ingresos | `#16a34a` | Valores positivos en texto y gráficos |
| Gastos | `#dc2626` | Valores negativos en texto y gráficos |
| Relleno de área — ingresos | `rgba(22, 163, 74, 0.08)` | Área bajo la curva en gráficos de ingresos |
| Relleno de área — gastos | `rgba(220, 38, 38, 0.08)` | Área bajo la curva en gráficos de gastos |
| Línea de tendencia | `#0ea5a8` | Línea de evolución temporal en Chart.js |

#### 2.7. Jerarquía Tipográfica de Color

*Tabla 6*

*Escala de colores de texto por nivel de jerarquía*

| Título de página | `#0b1739` | Encabezados H1 en contenedores principales |
| Texto oscuro | `#0f172a` | *Labels* y valores destacados |
| Texto estándar | `#1e293b` | Párrafo y cuerpo general |
| Texto medio | `#334155` | Secciones de apoyo |
| Texto suave | `#475569` / `#64748b` | Etiquetas de formulario y textos auxiliares |
| Texto muy suave | `#657786` | *Hints* y pies de campo |
| Enlace | `#1d4ed8` | Color base de hipervínculos |
| Enlace — *hover* | `#1e40af` | Estado *hover* de hipervínculos |

#### 2.8. Fondos y Superficies

*Tabla 7*

*Colores de fondo por nivel de capa*

| Página principal | `#f8fafc` | Fondo de contenedores en autenticación, finanzas e índice |
| Tarjetas | `#FCFCF8` | Superficies de tarjetas, *inputs* y botones de menú |
| Fondo suave | `#F8F9FB` / `#f1f5f9` | Cabeceras de tabla y fondos secundarios |
| Fondo *hover* | `#F3F5F8` | Respuesta visual al *hover* en zonas neutras |
| *Hero landing* | `#0f172a → #1e293b → #334155` | Degradado del encabezado de la página *landing* |
| *Placeholder* de imagen | `#2a2a2a` | Zonas de imagen decorativa en *landing* |

#### 2.9. Bordes y Separadores

*Tabla 8*

*Colores de borde según contexto*

| Borde estándar | `#e2e8f0` | Contenedores, tablas y paneles |
| Borde de componentes | `#E0E6F0` | Tarjetas del sistema de componentes |
| *Input* en reposo | `#cbd5e1` | Campos de formulario sin foco |
| *Input* con foco | `#0ea5a8` | Resalte del campo activo en formularios |
| Borde de menú | `#dbe1ea` | Botones de navegación en reposo |

#### 2.10. Componentes Específicos y Estados de Sistema

*Tabla 9*

*Colores de botones tipo índice y estados especiales*

| Fondo de botón índice | `#BAEBFA` | Botones en páginas que emplean `index.css` |
| Fondo *hover* de botón índice | `#a9dcec` | Estado *hover* del botón índice |
| Borde de botón índice | `#a9dcec` | Borde del botón índice |
| Texto de botón índice | `#0f172a` | Texto del botón índice |
| Deshabilitado | `#94a3b8` | Botones y controles no disponibles |
| Blanco puro | `#ffffff` | Texto sobre fondos de color |
| Teal oscuro — *landing* | `#0a7379` | *Hover* del botón CTA en *landing* |
| Teal profundo — Excel | `#0f766e` | Indicadores de resumen en análisis Excel |

---

## 3. Tipografía

### 3.1. Fuente Principal

El sistema tipográfico se sustenta en la familia **Inter**, importada desde Google Fonts con los pesos 300 a 700. La pila de fuentes de reserva recomendada es la siguiente: `"Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`.

### 3.2. Escala Tipográfica

*Tabla 10*

*Escala de tamaños de texto y sus aplicaciones*

| Título *hero* | `clamp(2.8rem, 6vw, 5rem)` | 45 – 80 px | Encabezado principal de la página *landing* |
| Título de página (H1) | `clamp(2rem, 4vw, 2.8rem)` | 32 – 45 px | Encabezado principal de cada página |
| Título de sección (H2) | `1.2rem` | ~19 px | Encabezados de segundo nivel |
| Texto destacado corto | `1.1rem` | ~18 px | Subtítulos y elementos de énfasis |
| Texto base | `0.95rem` | ~15 px | Lectura general y párrafos |
| Texto auxiliar | `0.875rem` | ~14 px | Metainformación y etiquetas |
| Texto mínimo | `0.85rem` | ~13.6 px | Pies de campo y notas |

*Nota.* Para formularios en dispositivos móviles, el tamaño mínimo de fuente en campos de entrada debe mantenerse en 16 px con el fin de evitar el zoom automático en iOS.

### 3.3. Pesos Tipográficos

*Tabla 11*

*Pesos de fuente y sus aplicaciones*

| 400 | Lectura general y texto de párrafo |
| 600 | Etiquetas, encabezados de sección y texto de formulario |
| 700 | Elementos de navegación y texto destacado |
| 800 | Botones principales y elementos de marca |

### 3.4. Altura de Línea

*Tabla 12*

*Valores de interlineado según contexto*

| 1.0 | Botones y elementos compactos de una línea |
| 1.4 – 1.6 | Párrafos normales y bloques de lectura |
| 1.8 | Listas y lectura en dispositivos móviles |

---

## 4. Espaciado y Proporciones

El sistema de espaciado sigue una escala de valores discretos que garantizan la consistencia visual entre páginas. Se recomienda emplear exclusivamente los valores definidos en la tabla siguiente antes de introducir nuevos valores ad hoc.

*Tabla 13*

*Escala de espaciado y sus aplicaciones*

| 4 px | Microajustes visuales |
| 8 px | Separación entre icono y texto |
| 10 px | Separación estándar de controles |
| 12 px | Separación de bloques cercanos |
| 14 px | Separación en formularios compactos |
| 16 px | Separación media general |
| 20 px | Separación dentro de tarjetas |
| 24 px | Separación amplia entre bloques |
| 28 px | Margen inicial de contenedor |
| 36 px | *Padding* de contenedor grande |
| 48 px | Margen exterior amplio |

Los parámetros estructurales del contenedor principal son los siguientes: ancho del 80 % del *viewport*, margen vertical de 28 – 48 px, *padding* interno de 26 – 36 px y *padding* de tarjetas de 20 px.

---

## 5. Bordes, Radios y Sombras

### 5.1. Radios de Esquina

*Tabla 14*

*Radios de borde por tipo de componente*

| Tarjetas | 12 px |
| Contenedores grandes | 20 – 22 px |
| Botones tipo pastilla | 999 px |
| Campos de formulario | 8 – 12 px |

El borde estándar aplicado a contenedores y paneles es `1px solid #e2e8f0`.

### 5.2. Sombras

Las sombras se emplean para establecer jerarquía visual entre capas sin sobrecargar la interfaz.

*Tabla 15*

*Sombras principales por contexto*

| Tarjeta en reposo | `0 2px 8px rgba(53, 64, 233, 0.05)` |
| Tarjeta en *hover* | `0 4px 16px rgba(53, 64, 233, 0.1)` |
| Botón principal | `0 10px 20px rgba(53, 64, 233, 0.3)` |
| Contenedor principal | `0 18px 42px rgba(15, 23, 42, 0.08)` |

---

## 6. Componentes de Interfaz

A continuación se presentan las definiciones CSS base de los componentes principales del sistema. Estos fragmentos constituyen la referencia canónica para cualquier nuevo desarrollo o modificación.

### 6.1. Botón Principal

```css
.btn-primary {
  background: #3540E9;
  color: #ffffff;
  border-radius: 8px;
  padding: 10px 20px;
}

.btn-primary:hover {
  background: #2A31C4;
}
```

### 6.2. Tarjeta

```css
.card {
  background: #FCFCF8;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(53, 64, 233, 0.05);
}
```

### 6.3. Campo de Formulario

```css
.form-input {
  padding: 10px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.95rem;
}

.form-input:focus {
  border-color: #0ea5a8;
  box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.18);
}
```

### 6.4. Alertas de Estado

```css
.alert-success { background: #E8F5E9; border-left: 4px solid #4CAF50; }
.alert-error   { background: #FFEBEE; border-left: 4px solid #F44336; }
.alert-warning { background: #FFF3E0; border-left: 4px solid #FFC107; }
```

### 6.5. *Navbar* de Bootstrap

```css
.navbar {
  background: #212529;
  z-index: 1100;
  min-height: 64px;
}
```

### 6.6. Dimensiones Estándar de Componentes

*Tabla 16*

*Dimensiones estándar por tipo de componente*

| Botón pequeño | *Padding* / fuente | 6px 14px / 0.875rem |
| Botón estándar | *Padding* / fuente | 10px 20px / 0.95rem |
| Botón grande | *Padding* / fuente | 12px 24px / 1rem |
| Botón táctil | Altura mínima | 42 px |
| *Input* estándar | *Padding* / altura | 10px 14px / 40 – 44 px |
| Tarjeta | *Padding* / radio | 20px / 12px |
| Contenedor | Ancho / *padding* | 80 % / 26 – 36px |
| *Navbar* de Bootstrap | Altura mínima | 64 px |
| Alerta | *Padding* / borde | 14px 16px / 4px |

---

## 7. Variables del Sistema de Diseño

El empleo de variables CSS centraliza los valores del sistema y facilita su mantenimiento. Toda nueva implementación debe priorizar el uso de estas variables sobre la declaración de valores literales.

```css
:root {
  --color-primary:  #3540E9;
  --color-success:  #4CAF50;
  --color-danger:   #F44336;
  --color-warning:  #FFC107;
  --color-text:     #2C3E50;
  --color-border:   #E0E6F0;
  --color-surface:  #FCFCF8;
  --color-bg-soft:  #F8F9FB;
  --color-cyan:     #59CAE3;

  --transition-fast:    150ms;
  --transition-base:    250ms;
  --transition-slow:    400ms;
  --ease-out:           cubic-bezier(0, 0, 0.2, 1);
  --ease-in-out:        cubic-bezier(0.4, 0, 0.2, 1);
}
```

---

## 8. Diseño Adaptativo y Accesibilidad

El punto de ruptura principal para dispositivos móviles se establece en `max-width: 768px`. En este contexto, el contenedor ocupa el 100 % del ancho disponible, los campos de entrada mantienen un tamaño mínimo de fuente de 16 px para prevenir el zoom automático en iOS y las listas adoptan un interlineado de 1.8 para mejorar la legibilidad en pantallas pequeñas.

En materia de accesibilidad, la interfaz respeta la preferencia del sistema operativo `prefers-reduced-motion: reduce`, reduciendo o eliminando las animaciones cuando el usuario así lo ha configurado. Las áreas de interacción táctil se mantienen por encima del mínimo recomendado de 44 × 44 px en todos los dispositivos.

---

## 9. Normas de Implementación

Con el fin de garantizar la coherencia y la mantenibilidad del sistema, se establecen las siguientes normas de desarrollo:

**Prácticas recomendadas:**

- Emplear las variables CSS definidas (`var(--color-*)`, `var(--transition-*)`) en lugar de valores literales codificados directamente.
- Reutilizar los tamaños y espaciados definidos en la escala antes de introducir nuevos valores.
- Mantener consistencia visual entre todas las páginas de la aplicación.

**Prácticas a evitar:**

- Declarar estilos en línea (*inline*) en el marcado HTML o PHP cuando sea posible resolverlo mediante clase CSS.
- Incluir nuevos valores de color sin verificar previamente la paleta existente.
- Declarar familias tipográficas distintas por componente.
- Definir múltiples colores de foco sin una regla común.

---

## 10. Archivos de Referencia

*Tabla 17*

*Archivos fuente del sistema de diseño*

| `src/css/shared.css` | Variables y *tokens* globales del sistema |
| `src/css/components.css` | Botones, tarjetas, formularios y alertas |
| `src/css/index.css` | *Navbar* de Bootstrap y contenedor estándar |
| `src/css/animations.css` | Transiciones y *keyframes* |
| `src/css/viewport.css` | Diseño adaptativo y accesibilidad |

---

*Nota.* El presente documento fue elaborado en el marco del Trabajo de Fin de Grado correspondiente al curso académico 2025-2026. Mayo de 2026.

