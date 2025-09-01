# AFIP PHP SDK

Este proyecto es un Software Development Kit (SDK) en PHP para facilitar la integración con los servicios web de la AFIP (Administración Federal de Ingresos Públicos) de Argentina.

## Estructura del proyecto
- `src/` - Código fuente principal del SDK.
- `src/Afip_res/` - Recursos necesarios como certificados, claves y archivos WSDL.
- `src/Class/` - Clases para los distintos servicios web implementados.
- `examples/` - Ejemplos de uso del SDK.

## Requisitos
- PHP 5.6 o superior
- Extensión SOAP habilitada
- OpenSSL habilitado

## Instalación
1. Clona este repositorio o descarga los archivos.
2. Configura los certificados y claves en `src/Afip_res/`.
3. Ajusta los parámetros necesarios en tu integración.

## Ejemplo de uso
Consulta el archivo `examples/CreateVoucher.php` para ver cómo utilizar el SDK para emitir un comprobante electrónico.

## Licencia
Ver archivo `LICENSE`.
