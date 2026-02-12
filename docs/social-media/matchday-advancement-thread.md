# Hilo: El avance de jornada en VirtuaFC

---

La parte que más me costó programar en la primera versión de mi juego, que creé hace casi dos años, fue el proceso en el que una jornada de liga se simula, dando paso a la siguiente. Esto parece una tontería pero es una parte vital del juego y se complica exponencialmente una vez nuevas competiciones se añaden. En esta nueva versión decidí resolverlo de raíz.

---

Imaginad lo que pasa cuando le das al botón de "Jugar jornada". Parece simple: se simulan los partidos y se muestran los resultados. Pero detrás hay un sistema que tiene que coordinar hasta cuatro tipos de competición completamente distintos al mismo tiempo.

---

Tu equipo puede estar jugando La Liga, la Copa del Rey, y la Champions League en la misma temporada. Cada competición tiene reglas diferentes para decidir qué partidos se juegan juntos, cómo se agrupan, y qué pasa después de cada partido.

---

El primer problema: no todas las competiciones agrupan los partidos igual. En La Liga, todos los partidos de la misma jornada se juegan juntos, aunque estén programados en fechas distintas. Un sábado y un domingo son la misma jornada. Pero en la Copa del Rey, lo que importa es la fecha. Todos los partidos del mismo día van en el mismo lote.

---

Para la Champions League fue aún más complejo. Es una competición híbrida. Tiene una fase de liga con 36 equipos y 8 jornadas donde los partidos se agrupan por jornada. Pero cuando termina la fase de liga, cambia a eliminatorias donde los partidos se agrupan por fecha. El mismo handler tiene que saber en qué fase está y actuar diferente.

---

La solución fue crear una interfaz común: el CompetitionHandler. Cada tipo de competición implementa cuatro responsabilidades. Qué partidos se juegan juntos. Qué hacer antes de simularlos. Qué hacer después. Y a dónde redirigir al jugador para ver los resultados.

---

Hay cuatro handlers: uno para ligas normales, otro para copas por eliminatorias, otro para el formato suizo de Champions, y otro para ligas con playoff como la Segunda División. Cada uno resuelve su complejidad internamente sin afectar a los demás.

---

El orquestador central es el MatchdayService. Lo primero que hace es buscar el próximo partido sin jugar, ordenado por fecha. A partir de ahí, encuentra todos los partidos del mismo día y pregunta a cada handler cómo expandir ese lote. Si hay partidos de liga, expande para incluir toda la jornada completa aunque algunos partidos caigan en otro día.

---

Aquí es donde la IA fue fundamental. Diseñar esta orquestación con múltiples competiciones simultáneas tenía muchos casos borde. Le planteaba escenarios: "qué pasa si el mismo día hay un partido de Copa y uno de Liga, pero la jornada de Liga incluye partidos del día siguiente". La IA me ayudó a pensar en estos casos y a diseñar el flujo correcto antes de escribir una línea de código.

---

Para la Copa del Rey, el sistema tiene que manejar sorteos dinámicos. Después de que se juega una ronda y se resuelven las eliminatorias, automáticamente comprueba si la siguiente ronda necesita sorteo. Si es así, empareja los equipos aleatoriamente, crea los partidos de ida y vuelta, y lo registra como un evento del juego.

---

La resolución de eliminatorias fue otro reto. Una eliminatoria puede ser a partido único o a ida y vuelta. Si es a ida y vuelta, hay que calcular el resultado global. Si hay empate, se aplica prórroga. Si sigue empatada, penaltis. Los penaltis simulan las cinco primeras tandas y luego muerte súbita si hace falta. Cada penalti tiene un 77% de probabilidad de entrar.

---

Para la Champions usé el formato suizo real. 36 equipos juegan 8 jornadas. Los 8 primeros pasan directo a octavos. Del 9 al 24 juegan una ronda de playoff. Del 25 al 36 quedan eliminados. El generador de eliminatorias usa bombos reales: los primeros cabezas de serie se emparejan con los últimos clasificados del playoff, replicando el sistema de la UEFA.

---

La Segunda División tiene otra variante: liga con playoff de ascenso. 42 jornadas de liga regular y luego los equipos del 3 al 6 juegan un playoff por la tercera plaza de ascenso. El sistema genera las semifinales y la final automáticamente cuando termina la liga, usando las posiciones de la clasificación para determinar los emparejamientos.

---

Una vez decididos los partidos a simular, entra el motor de simulación. Cada partido calcula los goles esperados de cada equipo usando una distribución de Poisson. Es la misma distribución estadística que se usa en análisis de fútbol real. Produce marcadores realistas: muchos 1-0 y 2-1, pocos 5-3.

---

La fuerza de cada equipo se calcula jugador por jugador. La habilidad técnica pesa un 40%, la física un 25%, la forma física un 20% y la moral un 15%. Pero aquí viene lo interesante: cada jugador tiene un "día bueno o malo" oculto que se genera aleatoriamente para cada partido.

---

Ese rendimiento oculto sigue una curva de campana. La mayoría de veces estará cerca de su nivel normal. Pero de vez en cuando, un jugador tendrá un día excepcional o un día desastroso. La moral alta aumenta las probabilidades de tener un buen día. La baja forma física te hace menos consistente.

---

Esto significa que el mismo partido entre los mismos equipos puede tener resultados diferentes cada vez. El Barcelona puede perder contra un equipo modesto si varios de sus jugadores tienen un mal día. Esto refleja lo que pasa en el fútbol real y hace que el juego sea impredecible sin ser injusto.

---

La formación y la mentalidad también modifican los goles esperados. Un 4-3-3 aumenta el ataque pero también expone la defensa. Jugar con mentalidad atacante hace que marques más pero también que encajes más. Jugar defensivo reduce ambos. Son multiplicadores que alteran las probabilidades, no resultados deterministas.

---

Los delanteros de élite reciben un bonus especial. Un delantero con valoración de 94 (tipo Mbappé) añade goles esperados extra a su equipo. Uno de 88 apenas nota efecto. Solo los verdaderamente top marcan la diferencia por sí solos, como en el fútbol real.

---

Después de calcular el marcador se generan los eventos del partido: goles, asistencias, tarjetas, lesiones. Quién marca depende de su posición y calidad. Un delantero centro tiene mucha más probabilidad de marcar que un lateral. Pero un lateral también puede marcar, como ocurre en la vida real.

---

Las tarjetas tienen un sistema de frustración. El equipo que va perdiendo recibe más tarjetas. Cada gol en contra aumenta la media de amarillas esperadas. Esto replica el comportamiento real: los equipos que pierden cometen más faltas por desesperación. Si un jugador recibe dos amarillas, la segunda debe ocurrir después de la primera cronológicamente.

---

Aquí hubo un problema sutil que la IA me ayudó a detectar. El marcador se decide primero con Poisson. Luego se generan los eventos: quién marcó, tarjetas, lesiones. Pero qué pasa si un jugador marca en el minuto 65 pero recibió una roja en el minuto 60? No puede haber marcado si ya estaba expulsado.

---

La solución fue un postprocesado. Después de generar todos los eventos, el sistema revisa si algún goleador o asistente fue expulsado o lesionado antes de su gol. Si es así, reasigna ese gol a un compañero disponible del mismo equipo, respetando los pesos por posición. El marcador no cambia. Solo cambia quién marcó.

---

El concepto del event sourcing fue otra decisión arquitectónica clave. Cada resultado de partido se graba como un evento inmutable. No se actualizan filas en la base de datos directamente. Se graba el evento "MatchResultRecorded" con todos los detalles y luego un proyector actualiza las tablas de lectura: clasificaciones, estadísticas de jugadores, sanciones.

---

Le pedí a la IA que me ayudara a diseñar la separación entre la simulación pura y la persistencia. La simulación es un cálculo en memoria que devuelve un resultado. La persistencia es un evento que pasa por el agregado y se almacena para siempre. Esto me permite cambiar cómo proyecto los datos sin perder el historial de lo que pasó.

---

Después de grabar los resultados viene la parte que nadie ve pero que hace que el juego funcione: las acciones post-partido. El sistema procesa fichajes completados, genera nuevas ofertas si la ventana está abierta, avanza las búsquedas de ojeadores, gestiona préstamos, comprueba lesionados recuperados, y revisa la forma física de los jugadores.

---

Cada handler de competición ejecuta sus propias acciones post-partido. El de Copa resuelve eliminatorias y sortea la siguiente ronda. El de Champions resuelve eliminatorias del knockout. El de Segunda revisa si el playoff necesita generar nuevas rondas. El de Liga no necesita hacer nada extra porque la clasificación se actualiza automáticamente.

---

Las suspensiones por tarjetas se sirven ANTES de procesar los nuevos eventos del partido. Este orden es crítico. Si un jugador cumple sanción y luego recibe otra amarilla en el mismo avance, no queremos que la nueva sanción se aplique inmediatamente. Primero se cumple la vieja, luego se registra la nueva.

---

El rendimiento fue otro desafío. En una sola jornada pueden jugarse 10+ partidos con 11 jugadores por equipo. Cargar todos esos datos individualmente sería lento. El sistema pre-carga todos los jugadores necesarios en una sola consulta, agrupa por equipo, y los pasa a cada simulación. Lo mismo con las sanciones: una consulta para todas.

---

La IA me ayudó especialmente con la optimización de consultas. Le mostraba el flujo completo y me señalaba dónde había problemas N+1: lugares donde el código hacía una consulta por partido en vez de una consulta para todos los partidos. Juntos fuimos eliminando esos cuellos de botella uno por uno.

---

Todo esto culmina en una redirección inteligente. Si tu equipo jugó, te lleva a la vista del partido en directo donde los eventos se revelan minuto a minuto con animaciones. Si tu equipo no jugó, te lleva directamente a los resultados de la competición correspondiente.

---

El partido en directo es una animación en tiempo real construida con Alpine.js. El reloj avanza, los eventos aparecen cuando llega su minuto, y cuando hay un gol la pantalla se pausa dramáticamente durante un segundo y medio mientras el marcador destella. Puedes verlo a velocidad normal o doble.

---

Mirando atrás, la clave fue separar las responsabilidades correctamente. El MatchdayService orquesta. Los handlers deciden. El simulador calcula. El proyector persiste. Cada pieza hace una cosa bien. Añadir una nueva competición significa crear un nuevo handler, no tocar el código existente.

---

Me llevó mucho tiempo y muchas iteraciones llegar a este diseño. La IA no escribió el código por mí, pero fue como tener un arquitecto de software con el que pensar en voz alta. Le planteaba problemas, discutíamos enfoques, y llegábamos juntos a soluciones que yo solo habría tardado mucho más en encontrar.

---

Si estás construyendo algo con muchas piezas que interactúan entre sí, mi consejo es: invierte tiempo en definir las interfaces antes de escribir la implementación. El CompetitionHandler tiene cuatro métodos. Esos cuatro métodos son los que hacen que todo funcione sin importar cuántas competiciones añada en el futuro.
