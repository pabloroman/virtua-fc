# Hilo 4: El sistema de lesiones — cuando el juego te obliga a tomar decisiones

---

En la primera versión de mi juego las lesiones eran básicas. Un jugador se lesionaba, desaparecía unas semanas, y volvía. No había tipos de lesión, no había factores de riesgo, no había forma de prevenirlas. Era un dado que tiraba el sistema y tú lo aceptabas. En esta versión quise que las lesiones fueran un sistema de juego en sí mismo.

---

La pregunta que me hice fue: qué hace que las lesiones sean interesantes en un juego de gestión. La respuesta no es que sean frecuentes ni que sean dramáticas. Es que te obliguen a tomar decisiones. Rotar o arriesgar. Invertir en médicos o en fichajes. Jugar con un veterano cansado o dar oportunidad a un joven.

---

Empecé por modelar los tipos de lesión. Hay diez, desde fatiga muscular (una semana fuera) hasta rotura de ligamento cruzado (hasta nueve meses). Cada tipo tiene un peso de probabilidad inversamente proporcional a su gravedad. Las lesiones menores son muy comunes. Las graves son raras. Como en la realidad.

---

La fatiga muscular y las distensiones musculares acumulan más de la mitad de todas las lesiones. Son molestas pero manejables. Te pierdes un partido o dos. Los esguinces de tobillo y las molestias de aductor están en un segundo nivel: dos a cuatro semanas. Empiezan a doler porque puedes perderte un tramo importante de liga.

---

Los desgarros de isquiotibiales y las contusiones de rodilla son las primeras lesiones que de verdad preocupan. Tres a seis semanas fuera. Un desgarro en enero te puede dejar sin un jugador clave para la recta final de la temporada. Y luego están las fracturas de metatarso, las roturas de cruzado, las roturas de Aquiles. Esas cambian temporadas enteras.

---

Cada tipo de lesión tiene afinidad por ciertas posiciones. Los delanteros sufren más desgarros de isquiotibiales por los sprints repetidos. Los defensas centrales sufren más contusiones de rodilla por los duelos aéreos. Los porteros son los menos propensos a lesionarse en general. Todo modelado con pesos por posición.

---

Pero la probabilidad base de lesión es solo el punto de partida. El sistema calcula la probabilidad real multiplicando cinco factores independientes. Si alguno es alto, la probabilidad sube. Si todos son bajos, la lesión es casi imposible. Si varios son altos a la vez, es casi inevitable.

---

El primer factor es la durabilidad. Es un atributo oculto que cada jugador recibe cuando se genera, siguiendo una curva de campana. La mayoría son normales. Algunos son de cristal: se lesionan el doble de lo esperado. Otros son de hierro: reducen su riesgo un 60%. El jugador nunca ve este número. Solo lo intuye con el tiempo.

---

Le pedí a la IA que me ayudara a decidir si la durabilidad debía ser visible o no. Discutimos que en la vida real, los clubes no saben exactamente cuánto riesgo tiene un jugador hasta que acumula historial. Un jugador "de cristal" lo descubres después de tres o cuatro lesiones en dos temporadas. Quise replicar esa incertidumbre.

---

El segundo factor es la edad. Los jugadores en su mejor momento (20-29 años) tienen riesgo base. Los jóvenes en desarrollo tienen un poco más. Los veteranos de más de 32 años tienen un 50% más de riesgo. Y además, los mayores de 30 tardan más en recuperarse: un 10-20% extra de tiempo de baja.

---

El tercer factor es la forma física. Un jugador fresco, con forma por encima de 85, tiene menos riesgo de lo normal. Pero un jugador agotado, por debajo de 30 de forma, tiene 2.5 veces más probabilidad de lesionarse. Esto crea una conexión directa entre la gestión de rotaciones y las lesiones.

---

El cuarto factor es la congestión de partidos. Si un jugador jugó hace menos de tres días, su riesgo se duplica. Si jugó hace tres o cuatro días, aumenta un 50%. Solo con cinco o más días de descanso vuelve al riesgo base. Esto castiga al jugador que pone siempre a los mismos once sin rotar.

---

Y el quinto factor es la inversión en servicios médicos. Aquí es donde el sistema se conecta con la economía del juego. Tu club puede invertir en infraestructura médica en cinco niveles. Sin médicos, tus jugadores se lesionan un 30% más y tardan un 20% más en recuperarse. Con servicios de primer nivel, se lesionan un 45% menos y se recuperan un 30% más rápido.

---

Este fue un diseño intencionado. No quería que invertir en médicos fuera "lo correcto" siempre. Es una decisión de presupuesto. Ese dinero podría ir al presupuesto de fichajes, o a mejorar el estadio para generar más ingresos. Pero si no inviertes en médicos y pierdes a tres titulares en diciembre, vas a desear haberlo hecho.

---

La IA me ayudó a calibrar los multiplicadores de cada nivel médico. Le planteé que un club sin médicos debería perder aproximadamente el doble de jornadas por lesión que un club con servicios de primer nivel. Con esa restricción, iteramos hasta encontrar los valores exactos que producían esa proporción en simulaciones.

---

Toda la probabilidad se calcula multiplicando los cinco factores sobre una base del 4% por jugador y partido. En el mejor caso (jugador joven, durable, descansado, en forma, con buenos médicos) la probabilidad baja al 0.5%. En el peor caso (veterano frágil, agotado, sin descanso, sin médicos) llega al tope del 35%.

---

Puse un tope del 35% a propósito. Sin él, un jugador en las peores condiciones se lesionaría casi siempre. Eso no sería divertido. Sería frustrante. El tope existe para que incluso la peor gestión posible no destruya la experiencia de juego. Las consecuencias deben existir, pero no deben ser absolutas.

---

Solo puede haber una lesión por equipo por partido. Esto también fue una decisión consciente. En la vida real es raro que dos jugadores se lesionen en el mismo partido. Y en términos de juego, perder dos titulares de golpe por mala suerte sería excesivo. Una lesión por partido es suficiente drama.

---

Cuando un jugador se lesiona durante un partido simulado, el sistema de eventos del partido lo registra con un minuto concreto. Esto importa porque si un jugador se lesiona en el minuto 30, no puede haber marcado un gol en el minuto 65. El postprocesado de eventos reasigna ese gol a un compañero disponible.

---

La recuperación también tiene sus capas. La forma física se regenera entre partidos: seis puntos por día de descanso. Pero un jugador que no juega también pierde agudeza competitiva, bajando dos a cuatro puntos por jornada que se sienta en el banquillo. Hay un suelo de 60: los suplentes no bajan de ahí, pero tampoco están en plena forma.

---

Esto crea un dilema constante. Tu mediapunta titular está al 70% de forma después de tres partidos en diez días. Si le haces descansar, se recupera pero pierde ritmo. Si le pones otra vez, mejora su ritmo pero aumenta el riesgo de lesión. No hay respuesta correcta universal. Depende del partido, del rival, del calendario.

---

Al final de temporada, todas las lesiones se borran automáticamente. Todos los jugadores empiezan la nueva temporada frescos, con forma entre 90 y 100. Es un reset limpio. No quise que una rotura de cruzado en mayo arruinara el inicio de la temporada siguiente. El drama de la lesión es intenso pero contenido dentro de una temporada.

---

Lo que más me gusta de este sistema es que la durabilidad oculta crea narrativas emergentes. No las programé. Surgen solas. "Este jugador que fiché se ha lesionado tres veces en media temporada, creo que es de cristal, debería buscar un sustituto." Esa historia la cuenta el jugador, no el código.

---

La IA me ayudó especialmente a encontrar el equilibrio general. Muchas veces le decía: "en las simulaciones de prueba, el jugador medio se lesiona X veces por temporada, quiero que sean Y". Y ajustábamos factores hasta que las estadísticas agregadas se parecían a las de una temporada real de La Liga. Los detalles importan, pero lo que importa más es que el todo se sienta creíble.

---

Si hay algo que aprendí diseñando este sistema es que los mejores sistemas de juego son los que crean decisiones, no los que crean eventos. Una lesión aleatoria es un evento. Un sistema donde tus decisiones de rotación, inversión y planificación afectan cuántas lesiones sufres... eso es un juego.
