# Hilo 2: El motor de simulación — realismo vs. determinismo

---

Uno de los mayores retos al diseñar un juego de fútbol es decidir cómo se simulan los partidos. Hay dos extremos: hacerlo completamente determinista (el mejor equipo siempre gana) o completamente aleatorio (da igual quién juegue). Ninguno de los dos funciona. El fútbol vive en el medio.

---

En la vida real, el Real Madrid puede perder contra el Valladolid. Pasa pocas veces, pero pasa. Un simulador que no permita eso no es un simulador de fútbol. Pero si el Valladolid gana la mitad de las veces, tampoco es creíble. El reto está en encontrar ese equilibrio.

---

Investigando cómo se modela esto en el análisis deportivo real, descubrí que los goles en un partido de fútbol siguen una distribución de Poisson. Es un concepto estadístico que predice cuántas veces ocurre un evento raro en un intervalo. Los goles son exactamente eso: eventos raros dentro de 90 minutos.

---

Lo que hace Poisson es generar resultados realistas de forma natural. Si un equipo tiene 1.5 goles esperados, la distribución produce muchos partidos con 1 gol, bastantes con 2, algunos con 0, pocos con 3, y casi ninguno con 5. Exactamente como los marcadores reales en La Liga.

---

La pregunta entonces es: cómo calcular esos goles esperados para cada equipo. Aquí es donde entra la fuerza del equipo. Se calcula jugador por jugador sumando sus atributos con pesos distintos. La habilidad técnica pesa un 40%, la física un 25%, la forma física un 20% y la moral un 15%.

---

Pero calcular la fuerza no es suficiente. Si dos equipos tienen fuerzas parecidas, el resultado debería ser muy abierto. Si hay una diferencia grande, el favorito debería ganar casi siempre. Para lograr esto, aplico un exponente a las fuerzas. Con un exponente de 1.8, las diferencias se amplifican de forma no lineal.

---

La IA me ayudó a calibrar este exponente. Le pasaba datos reales de La Liga y le pedía que comparara las distribuciones de resultados con distintos valores. Con 1.0 había demasiados empates entre equipos desiguales. Con 2.0, los grandes nunca perdían. Con 1.8 encontramos el punto justo.

---

La ventaja de local es otra capa. El equipo de casa recibe un bonus fijo en goles esperados y el visitante sufre una penalización en su contribución de fuerza. Esto replica la estadística real: en La Liga los equipos locales meten de media 1.5 goles frente a 1.0 del visitante.

---

Pero aquí viene lo que de verdad le da vida al sistema: cada jugador tiene un "día" oculto que se genera para cada partido. Es un multiplicador de rendimiento que sigue una curva de campana. La mayoría de veces estará cerca de su nivel normal. Pero de vez en cuando, alguien tendrá un día excepcional o desastroso.

---

Uso la transformada de Box-Muller para generar esta curva. La desviación estándar es configurable. Con 0.05 el mejor equipo casi siempre gana. Con 0.20 hay demasiados resultados sorpresa. Con 0.10, que es el valor actual, se consigue ese punto donde las sorpresas existen pero no son la norma.

---

La moral del jugador influye en este rendimiento oculto. Un jugador con moral alta tiene más probabilidades de tener un buen día. Uno con moral baja arrastra su rendimiento hacia abajo. Y la forma física afecta la consistencia: un jugador agotado tiene más varianza, es más impredecible.

---

Esto significa que gestionar la moral y la forma física de tu plantilla tiene un impacto real. No es solo un número decorativo. Un equipo con la moral alta es literalmente más fiable. Rota jugadores, descansa a los cansados, y tu equipo rendirá mejor estadísticamente.

---

La formación táctica también altera los goles esperados. Un 4-3-3 te da un 10% más de ataque pero expone tu defensa un 10%. Un 5-4-1 recorta tu ataque un 15% pero reduce lo que encajas un 15%. Son multiplicadores cruzados: tu formación ofensiva se multiplica por la formación defensiva del rival.

---

La mentalidad funciona igual pero con un matiz importante. Jugar atacante multiplica tus goles un 25% pero también aumenta los goles del rival un 15%. Jugar defensivo reduce tus goles un 30% pero baja los del rival un 40%. La decisión no es obvia. Depende del contexto.

---

Le pedí a la IA que me ayudara a diseñar la interacción entre formación y mentalidad para que no hubiera una opción dominante. Si jugar atacante siempre fuera mejor, nadie jugaría defensivo. Si defensivo fuera infalible, nadie atacaría. La clave fue hacer que la respuesta correcta dependa del rival.

---

Los delanteros de élite reciben un bonus especial. Un delantero de nivel 94 añade unos 0.15 goles esperados extra a su equipo en cada partido. Uno de 88 apenas nota efecto. Solo los verdaderamente top marcan diferencia por sí solos. Quería replicar eso: tener a Mbappé importa, pero no te garantiza nada.

---

Después de calcular el marcador con Poisson, se generan los eventos del partido. Quién marcó, quién asistió, tarjetas, lesiones. Cada evento usa selección aleatoria ponderada por posición. Un delantero centro tiene peso 30 para marcar. Un central, peso 2. Un portero, 0. Pero para asistencias, el mediapunta tiene peso 25.

---

La calidad del jugador también influye en quién protagoniza los eventos. Un delantero de 85 tiene más probabilidades de ser elegido como goleador que uno de 65 en la misma posición. El peso por posición se multiplica por un factor de calidad. Así, los mejores jugadores aparecen más en las estadísticas, como en la realidad.

---

Las tarjetas tienen un sistema que me encanta: la frustración. El equipo que va perdiendo recibe más amarillas. Cada gol en contra aumenta la media esperada de tarjetas. Esto replica algo real: los equipos que pierden cometen más faltas por desesperación. Las rojas directas también son más probables si vas perdiendo.

---

Si un jugador recibe dos amarillas en el mismo partido, la segunda debe ocurrir cronológicamente después de la primera. Parece obvio, pero cuando generas eventos aleatorios tienes que forzar esta restricción explícitamente. La segunda amarilla se genera en un rango de minutos posterior a la primera.

---

Aquí surgió un problema sutil que la IA me ayudó a detectar. El marcador se decide primero. Luego se generan los eventos. Pero qué pasa si un jugador marca en el minuto 65 pero recibió una roja en el minuto 60? No puede haber marcado si ya estaba expulsado.

---

La solución fue un paso de postprocesado. Después de generar todos los eventos, el sistema recorre la cronología y reasigna goles y asistencias de jugadores que fueron expulsados o lesionados antes de su evento. El gol se le da a otro compañero disponible, respetando los pesos por posición. El marcador nunca cambia. Solo cambia quién marcó.

---

Las lesiones son otro mundo. Hay diez tipos, desde fatiga muscular (1 semana) hasta rotura de ligamento cruzado (hasta 36 semanas). Cada tipo tiene afinidad por ciertas posiciones. Los delanteros sufren más desgarros de isquiotibiales. Los defensas, más contusiones de rodilla.

---

La probabilidad de lesión depende de cinco factores multiplicativos: durabilidad del jugador (un atributo oculto), edad, forma física, congestión de partidos, y el nivel de servicios médicos del club. Un jugador joven, descansado, con buena durabilidad y servicios médicos de primer nivel tiene muy poca probabilidad de lesionarse. Un veterano agotado sin médicos, mucha.

---

Ese atributo oculto de durabilidad se genera con una distribución de curva de campana cuando el jugador se crea. La mayoría son normales. Algunos son de cristal. Otros son de hierro y casi nunca se lesionan. El jugador nunca ve este número, pero lo siente a lo largo de las temporadas.

---

Todo el sistema de simulación es configurable sin tocar código. Hay un archivo de configuración con más de quince parámetros: goles base, exponente de fuerza, ventaja de local, varianza de rendimiento, probabilidades de tarjetas, lesiones, autogoles. Puedo ajustar el balance del juego cambiando un número.

---

La IA fue clave para el proceso de calibración. Le planteaba: "con estos parámetros, el equipo mejor clasificado gana la liga el 80% de las temporadas simuladas. Quiero que sea más cercano al 60%". Y me sugería qué parámetros ajustar y en qué dirección. Iterar sobre el balance del juego habría sido mucho más lento sin esa ayuda.

---

El resultado final es un motor que produce temporadas creíbles. Los grandes ganan la mayoría de las veces. Pero hay sorpresas. Hay rachas. Hay jugadores que tienen una temporada excepcional y otros que decepcionan. No es un dado. No es una calculadora. Es algo que se siente como fútbol.
