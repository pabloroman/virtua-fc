# Hilo 1: El avance de jornada en VirtuaFC

---

La parte que más me costó programar en la primera versión de mi juego, que creé hace casi dos años, fue el proceso en el que una jornada de liga se simula, dando paso a la siguiente. Esto parece una tontería pero es una parte vital del juego y se complica exponencialmente una vez nuevas competiciones se añaden. En esta nueva versión decidí resolverlo de raíz.

---

Imaginad lo que pasa cuando le das al botón de "Jugar jornada". Parece simple: se simulan los partidos y se muestran los resultados. Pero detrás de ese botón hay un pipeline de más de diez pasos que se ejecutan en un orden muy concreto. Si el orden cambia, todo se rompe.

---

Lo primero que hace el sistema es buscar el próximo partido sin jugar, ordenado por fecha. A partir de ahí, identifica todos los partidos programados para ese día y los agrupa en un lote. Pero la cosa se complica: si hay partidos de liga, expande el lote para incluir toda la jornada, aunque algunos partidos caigan en otro día.

---

Aquí es donde la IA fue fundamental. Diseñar esta orquestación tenía muchos casos borde. Le planteaba escenarios: "qué pasa si el mismo día hay un partido de Copa y uno de Liga, pero la jornada de Liga incluye partidos del día siguiente". La IA me ayudó a pensar en estos casos y a diseñar el flujo correcto antes de escribir una línea de código.

---

Una vez identificados los partidos, el sistema pre-carga en memoria todo lo necesario. Todos los jugadores de todos los equipos involucrados. Todas las sanciones activas. Todo de una vez, no partido por partido. En una jornada pueden jugarse más de diez partidos con once jugadores por equipo. Si cargas los datos uno a uno, se nota.

---

La IA me ayudó especialmente con esto. Le mostraba el flujo completo y me señalaba dónde había problemas N+1: lugares donde el código hacía una consulta a la base de datos por cada partido en vez de una consulta para todos. Juntos fuimos eliminando esos cuellos de botella uno por uno.

---

Antes de simular, el sistema prepara las alineaciones. Para tu equipo usa la alineación que tú elegiste. Para los equipos de la IA, selecciona automáticamente el mejor once disponible, descartando jugadores sancionados y lesionados. Esto pasa para todos los partidos del lote a la vez.

---

Después de simular todos los partidos, los resultados se graban usando event sourcing. Cada resultado se almacena como un evento inmutable. No se actualizan filas en la base de datos directamente. Se graba un evento con todos los detalles y luego un proyector actualiza las tablas de lectura: clasificaciones, estadísticas, sanciones.

---

Le pedí a la IA que me ayudara a diseñar esta separación entre la simulación y la persistencia. La simulación es un cálculo puro en memoria que devuelve un resultado. La persistencia es un evento que pasa por el agregado y se almacena para siempre. Puedo cambiar cómo proyecto los datos sin perder el historial.

---

Luego viene la parte que nadie ve pero que hace que el juego se sienta vivo: las acciones post-partido. El sistema procesa fichajes pendientes, genera nuevas ofertas si la ventana de traspasos está abierta, avanza las búsquedas de ojeadores, gestiona préstamos, comprueba lesionados recuperados, y revisa la forma física del plantel.

---

También revisa ofertas a punto de expirar y te avisa si quedan menos de dos días para responder. Busca jugadores con baja forma física y te alerta. Intenta generar jóvenes de la cantera que puedes incorporar al primer equipo. Todo esto pasa en segundo plano cada vez que avanzas una jornada.

---

Las suspensiones por tarjetas se sirven ANTES de procesar los nuevos eventos del partido. Este orden es crítico. Si un jugador cumple sanción y luego recibe otra amarilla en el mismo avance, no queremos que la nueva sanción se aplique inmediatamente. Primero se cumple la vieja, luego se registra la nueva.

---

Después de todo esto, el sistema decide a dónde llevarte. Si tu equipo jugó, te redirige a la vista del partido en directo. Si no jugó, te lleva a los resultados de la competición correspondiente. Cada tipo de competición decide su propia pantalla de resultados.

---

El partido en directo es una animación construida con Alpine.js. El reloj avanza en tiempo real, los eventos aparecen cuando llega su minuto, y cuando hay un gol la pantalla se pausa dramáticamente durante un segundo y medio mientras el marcador destella. Puedes verlo a velocidad normal o doble.

---

Mientras ves tu partido, un ticker lateral muestra los goles de los otros partidos que se están jugando simultáneamente. Así tienes esa sensación de seguir tu partido mientras estás pendiente de los demás resultados, como cuando ves la jornada real un domingo por la tarde.

---

Lo que hace especial a este pipeline es que cada paso sabe exactamente lo que tiene que hacer y nada más. El servicio de jornada orquesta. El simulador calcula. El proyector persiste. Las acciones post-partido reaccionan. Si una pieza falla, las demás siguen intactas.

---

Me llevó muchas iteraciones llegar a este flujo. La IA no escribió el código por mí, pero fue como tener un arquitecto de software con el que pensar en voz alta. Le planteaba problemas, discutíamos enfoques, y llegábamos juntos a soluciones que yo solo habría tardado el doble en encontrar.
