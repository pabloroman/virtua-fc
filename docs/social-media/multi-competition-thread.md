# Hilo 3: Múltiples competiciones y el sueño de la Champions League

---

Cuando empecé a diseñar VirtuaFC, el juego solo tenía La Liga. Veinte equipos, treinta y ocho jornadas, una clasificación. Sencillo. Pero desde el principio sabía que quería añadir la Copa del Rey, la Segunda División, y eventualmente competiciones europeas. El problema es que cada una funciona de forma completamente distinta.

---

La Liga es fácil de modelar. Todos contra todos, ida y vuelta, clasificación por puntos. La Copa del Rey ya complica las cosas: eliminatorias por sorteo, partidos de ida y vuelta, prórrogas, penaltis. La Segunda División añade playoffs de ascenso al final de la temporada regular. Cada formato necesita su propia lógica.

---

La clave fue una decisión de diseño temprana: crear una interfaz común con cuatro responsabilidades. Qué partidos se juegan juntos. Qué hacer antes de simularlos. Qué hacer después. Y a dónde redirigir al jugador para ver resultados. Cada tipo de competición implementa estas cuatro cosas a su manera.

---

Para La Liga, "qué partidos se juegan juntos" significa toda la jornada completa, aunque los partidos caigan en distintos días. Para la Copa del Rey significa todos los partidos del mismo día. Son reglas de agrupación completamente distintas, pero el resto del sistema no necesita saberlo.

---

La Copa del Rey fue el primer formato realmente difícil. Tiene sorteos dinámicos. Cuando se juega una ronda y se resuelven todas las eliminatorias, el sistema automáticamente comprueba si la siguiente ronda necesita sorteo. Empareja los equipos aleatoriamente, crea los partidos de ida y vuelta, y lo registra como un evento inmutable del juego.

---

La resolución de eliminatorias fue otro reto. Una eliminatoria puede ser a partido único o a doble partido. Si es doble, hay que sumar los marcadores globales. Si hay empate, se simula una prórroga con goles esperados mucho más bajos (la prórroga suele ser conservadora). Si sigue empatada, penaltis: cinco tandas y luego muerte súbita.

---

La Segunda División introdujo otro formato: liga con playoff. 22 equipos juegan 42 jornadas de liga regular. Los dos primeros ascienden directo. Pero los del puesto 3 al 6 juegan un playoff a eliminatoria por la tercera plaza de ascenso. El sistema genera las semifinales y la final automáticamente cuando termina la temporada regular.

---

Con estos tres formatos funcionando, el juego ya se sentía completo. Pero yo quería más. Quería que si tu equipo acaba entre los cuatro primeros de La Liga, pudiera jugar la Champions League la temporada siguiente. Y aquí pensé que me iba a llevar semanas. Me equivoqué.

---

La Champions League de la UEFA cambió su formato en 2024. Ya no hay fase de grupos tradicional. Ahora es un sistema suizo: 36 equipos en una sola clasificación, cada uno juega 8 partidos contra rivales diferentes. Los 8 primeros pasan directo a octavos. Del 9 al 24, playoff. Del 25 al 36, eliminados.

---

Cuando vi el nuevo formato pensé: esto es imposible de modelar. Un sorteo con restricciones de país, cuatro bombos, 144 partidos que generar sin que ningún equipo juegue dos veces contra el mismo rival, equilibrando partidos de local y visitante. Es un problema combinatorio serio.

---

Le planteé el problema a la IA. Le expliqué las restricciones: cada equipo juega contra exactamente dos rivales de cada bombo, cuatro en casa y cuatro fuera, máximo dos rivales del mismo país, y todo esto tiene que caber en 8 jornadas sin conflictos de horario. La IA me propuso un enfoque basado en circuitos de Euler.

---

La idea es tratar los emparejamientos como un grafo. Cada equipo es un nodo. Cada partido posible es una arista. El algoritmo construye los emparejamientos bombo por bombo, respetando las restricciones de país, y luego orienta las aristas para equilibrar locales y visitantes. Finalmente, extrae los 8 jornadas como "matchings perfectos" del grafo.

---

Lo más sorprendente fue que funciona. Le di una tarde entera al problema, iterando con la IA sobre los casos borde. Que qué pasa si un bombo tiene tres equipos del mismo país. Que qué pasa si no se puede equilibrar local/visitante al final. El algoritmo tiene reintentos con semillas aleatorias distintas hasta que encuentra una solución válida.

---

En una tarde tenía 144 partidos generados correctamente para la fase de liga de la Champions. Me pareció irreal. Un problema que pensé que me llevaría semanas se resolvió en horas porque la IA me ayudó a elegir el enfoque algorítmico correcto desde el principio, en vez de ir probando a fuerza bruta.

---

Después de la fase de liga viene el knockout. Aquí repliqué el formato real de la UEFA con sus bombos de eliminatorias. Los equipos del 9-10 se emparejan con los del 23-24. Los del 11-12 con los del 21-22. Y así sucesivamente. El cabeza de serie siempre juega la vuelta en casa.

---

Para octavos de final, los 8 primeros de la fase de liga se emparejan con los ganadores del playoff, pero siguiendo bombos específicos. El 1 y el 2 de la liga se cruzan con los ganadores del bombo donde estaban los equipos del 15-18. Todo esto está codificado como constantes que reflejan el reglamento real de la UEFA.

---

A partir de cuartos de final, el sorteo es abierto. Simplemente se barajan los ganadores de la ronda anterior y se emparejan aleatoriamente. Las semifinales igual. La final es a partido único. El generador sabe cuándo crear eliminatorias a ida y vuelta y cuándo a partido único según la ronda.

---

Cada ronda tiene fechas reales del calendario europeo. El playoff se juega en febrero. Octavos en marzo. Cuartos en abril. Semifinales entre abril y mayo. La final a finales de mayo. Todo se integra con el calendario de La Liga y Copa del Rey sin conflictos.

---

Con la Champions hecha, añadir la Europa League y la Conference League fue trivial. Mismo formato suizo, mismos handlers, misma lógica de knockout. Solo cambian los datos: los equipos, los bombos, y las fechas. Tres competiciones europeas por el precio de una.

---

La clasificación a Europa también funciona automáticamente. Cuando acaba la temporada de La Liga, un procesador revisa las posiciones finales. Los cuatro primeros van a la Champions. El quinto y sexto a la Europa League. El ganador de la Copa del Rey va a la Conference League si no se ha clasificado ya para algo mejor.

---

Lo que me llena de satisfacción es que añadir la Champions, con toda su complejidad, no requirió cambiar ni una línea del código existente. Solo añadí un nuevo handler para el formato suizo, un servicio para el sorteo, y un generador para las eliminatorias. El resto del juego siguió funcionando exactamente igual.

---

Esto es el poder de definir bien las interfaces desde el principio. Cuando diseñé el sistema con solo La Liga y Copa del Rey, invertí tiempo en hacer que fuera extensible. Parecía innecesario en ese momento. Pero cuando llegó la Champions, todo encajó como si hubiera sido diseñado para ella. Porque lo fue.

---

La IA no modeló la Champions por mí. Pero me ahorró el camino largo. Sin ella, habría pasado días investigando algoritmos de grafos y combinatoria. Con ella, identificamos el enfoque correcto en una conversación de veinte minutos y pasamos directamente a implementar. A veces la IA no te da la respuesta, te ahorra las preguntas equivocadas.

---

Si estáis construyendo algo y pensáis "esto es demasiado complejo para añadirlo", probad a plantear el problema bien antes de descartarlo. A veces la complejidad aparente se desmorona cuando encuentras la abstracción correcta. La Champions League parecía imposible. Me llevó una tarde.
