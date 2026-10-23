Registro de revisión
Fase | Qué generó la IA | Qué corregí o rechacé | Por qué
0    |Preparación | Cambié XAMPP por Laragon (puertos 81 y 3307) | XAMPP falló; el stack PHP 8 + MySQL + PDO no cambia

| 1 · D0 | D0.md con RF, RNF, RN y supuestos | Eliminé un RF de reportes mensuales / agregué el SUPUESTO de Laragon | Estaba fuera del caso / no lo incluyó |

| 1 · D0 | RNF en tabla sin identificadores; supuestos como SUP-01 | Reescribí los RNF como RNF1–RNF11 y marqué los supuestos con SUPUESTO | El examen exige RNF1… identificados y supuestos marcados como SUPUESTO |

| 2 · Especificación | requirements.md y design.md generados en el chat por defecto de Kiro | Volví a generarlos con el agente Spec de Kiro (.kiro/specs/) | El examen pide la fase 2 en Kiro modo spec; el agente Spec produce requirements y design trazados entre sí |

| 2 · Especificación | requirements.md y design.md sin el formato Spec de Kiro | Los reorganicé con Introduction, Glossary, User Stories, Correctness Properties y Testing Strategy | Kiro marcaba secciones obligatorias faltantes y así cada RN queda trazada a una propiedad verificable |

| 3 · Base de datos | schema.sql, seed.sql y .env.example | [lo que corregiste, o "Verifiqué tipos y CHECK contra design.md; sin cambios"] | [por qué] |