# WooCommerce Afiliados a Cupón

Un sistema de comisiones avanzado para WooCommerce, diseñado para vendedores o afiliados que operan con códigos de cupón y con un modelo de comisiones por niveles mensuales.

Este plugin extiende la funcionalidad de WooCommerce para crear un sistema de comisiones a medida, ideal para empresas de servicios o desarrollo donde el pago de la comisión no está ligado a la venta, sino a la finalización del proyecto.

---

## Descripción

Este plugin resuelve una necesidad de negocio específica: gestionar un equipo de vendedores externos que ganan comisiones basadas en un rendimiento mensual por cantidad, no por valor. A diferencia de los plugins de afiliados estándar, la comisión final de todas las ventas de un mes se calcula retroactivamente el primer día del mes siguiente, asegurando que se aplique la tasa correcta según el volumen total de ventas.

El sistema desvincula el cálculo de la comisión del pago de la misma. Una comisión se considera "ganada" a fin de mes, pero solo se vuelve "pagable" una vez que un administrador marca manualmente el proyecto asociado como "Finalizado".

## Características Principales

* **Rol de Usuario "Vendedor":** Crea un rol específico para tus afiliados que hereda las capacidades de un cliente normal.
* **Asignación de Cupones:** Permite asignar fácilmente un código de cupón de WooCommerce a un vendedor específico.
* **Panel de Vendedor Integrado:** Añade una nueva pestaña en el área "Mi Cuenta" de WooCommerce para que los vendedores puedan ver en tiempo real el estado de sus ventas, comisiones pendientes, comisiones pagables y su historial.
* **Panel de Administración Centralizado:** Proporciona al administrador del sitio una página dedicada para ver todas las comisiones, filtrarlas por vendedor o estado, y gestionar los pagos.
* **Sistema de Comisiones por Niveles (Tiers) Mensuales:** Calcula las comisiones basándose en el número total de ventas que un vendedor logra dentro de un mes calendario.
* **Cálculo Automatizado por Cron:** Utiliza el sistema de tareas programadas de WordPress (WP-Cron) para ejecutar automáticamente el complejo cálculo de comisiones el primer día de cada mes.
* **Base de Datos Personalizada:** Almacena todos los datos de comisiones en una tabla personalizada (`wp_afiliados_ventas`) para un rendimiento óptimo y para no sobrecargar la tabla `postmeta` de WordPress.
* **Flujo de Aprobación de Pagos:** Incluye un flujo de trabajo para que el administrador marque los proyectos como "finalizados", lo que cambia el estado de la comisión a "Pagable".

## Instalación

#### 1. Desde el panel de WordPress (Recomendado)

1.  Comprime la carpeta completa del plugin en un archivo `.zip`.
2.  Ve a tu panel de administración de WordPress y navega a `Plugins > Añadir nuevo`.
3.  Haz clic en el botón `Subir plugin` en la parte superior de la página.
4.  Selecciona el archivo `.zip` que acabas de crear y haz clic en `Instalar ahora`.
5.  Una vez instalado, haz clic en `Activar plugin`.

#### 2. Manualmente (vía FTP/SFTP)

1.  Descomprime el archivo `.zip` del plugin en tu computadora.
2.  Conéctate a tu servidor a través de un cliente FTP (como FileZilla).
3.  Navega al directorio `wp-content/plugins/` de tu instalación de WordPress.
4.  Sube la carpeta completa del plugin (`woocommerce-afiliados-cupon`) a este directorio.
5.  Ve a tu panel de administración de WordPress, navega a `Plugins` y busca "WooCommerce Afiliados a Cupón" en la lista.
6.  Haz clic en `Activar`.

## Flujo de Trabajo (Uso)

1.  **Configuración Inicial:**
    * Asegúrate de que tus usuarios vendedores tengan asignado el rol "Vendedor".
    * Crea cupones en `WooCommerce > Marketing > Cupones` y, en el campo personalizado, asigna cada cupón al vendedor correspondiente.

2.  **Ciclo de Venta:**
    * Un cliente utiliza un cupón de un vendedor para realizar una compra.
    * El plugin registra automáticamente la venta en la tabla de comisiones con un estado inicial y una comisión provisional de 0.

3.  **Proceso de Fin de Mes (Automático):**
    * El primer día de cada mes, la tarea programada se ejecuta en segundo plano.
    * El sistema calcula la cantidad total de ventas del mes anterior para cada vendedor, determina la tasa de comisión correcta según los niveles definidos y actualiza todas las comisiones de ese mes con el monto final.

4.  **Finalización de Proyectos:**
    * Cuando un proyecto/servicio asociado a un pedido se completa, el administrador debe ir a la página de edición de ese pedido en WooCommerce.
    * Allí, encontrará un panel de "Gestión de Comisión" donde podrá marcar el proyecto como "Finalizado".
    * Esto cambiará el estado de la comisión a "Pagable".

5.  **Gestión y Pagos:**
    * El vendedor puede ver en su panel qué comisiones están listas para ser pagadas.
    * El administrador puede ver un resumen de todas las comisiones pagables en el panel de administración del plugin y proceder a realizar los pagos de forma externa (transferencia, PayPal, etc.).

## Capturas de Pantalla

**1. Panel del Vendedor en "Mi Cuenta"**

Muestra al vendedor sus ventas, el estado de cada comisión y los totales.

![Panel del Vendedor en Mi Cuenta]()

---

**2. Panel de Administración de Comisiones**

Vista para el administrador del sitio, con herramientas para filtrar y gestionar todas las comisiones.

![Panel de Administración de Comisiones]()

---

**3. Asignación de Vendedor a un Cupón**

Campo personalizado que aparece en la página de edición de cupones de WooCommerce.

![Asignación de Vendedor a un Cupón]()

---

**4. Metabox de Gestión en el Pedido**

Permite al administrador marcar un proyecto como "Finalizado" directamente desde la orden de WooCommerce.

![Metabox de Gestión en el Pedido]()


## Licencia

Este plugin es liberado bajo la Licencia Apache 2.0.
Ver: https://www.apache.org/licenses/LICENSE-2.0