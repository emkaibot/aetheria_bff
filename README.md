# Aetheria

(Nombre provisional. La energía del juego se sigue llamando **éter**; los
identificadores internos —realm `eter`, base de datos `eter`, `app.eter.game`— no cambian.)

RPG de gestión multicuenta. Cada cuenta tiene tantos héroes como quiera; cada héroe acumula
**éter**, que le sube de nivel y le desbloquea ventajas. Con ellas reta a jefes
en combates que se resuelven en el servidor.

- **BFF** (este repo): Symfony 7.4 + Doctrine + Postgres, con Keycloak.
- **App**: [aetheria_app](https://github.com/emkaibot/aetheria_app), Expo SDK 57 + Expo Router.

## Arrancar

```bash
make up          # BFF :8280 · Keycloak :8281 (admin/admin123) · Adminer :8282 · Postgres :5452 · Mailpit :8225
# y en la app: nvm use && npm install && npm run ios
```

Cuentas de prueba: `demo@eter.app / demo1234` y `rival@eter.app / rival1234`.

## Cuentas

**No hay registro**, ni en la app ni en Keycloak. Las cuentas se crean con un comando y a la
persona le llega un correo de Keycloak con un enlace (válido 3 días) para poner su contraseña:

```bash
make account EMAIL=ana@ejemplo.com NAME=Ana
```

Con un correo que ya tiene cuenta, reenvía la invitación. En local los correos no salen: se
leen en Mailpit, http://localhost:8225. Si el realm se creó antes de configurar el correo,
`make keycloak-setup` se lo aplica.
Los puertos están desplazados +200 respecto a goveo para poder tener los dos levantados.

## Reglas

- **Nivel** = ⌊√(éter/10)⌋+1 (máx. 50). Estadísticas = base de clase + crecimiento por nivel.
- **Ventajas** por saldo de éter (se pierden si el saldo baja): 25 ⚡ +8% ataque · 75 🪨 −10% daño ·
  150 🐈 esquiva 10% · 300 🎯 crítico 15% · 500 💨 segundo aliento · 800 🩸 robo de vida ·
  1200 🌟 +15% a todo · 2000 👑 primer golpe x3.
- **Éter**: sólo se da o se quita a mano. Da nivel y ventajas; los combates no lo tocan.
- **Jefes**: 7, en orden; cada personaje desbloquea el siguiente al vencer al anterior. Ganar
  da **oro** y uno o dos **objetos**; perder no quita nada. El jefe crece con el tamaño del
  equipo (`Boss::partyScale`).
- **Héroes**: 10, cada uno con su papel y su especial. Se empieza con Darius, Seraphina y
  Aelyn; cada jefe libera uno la primera vez que cae, o se recluta antes con oro. Van hasta 9
  junto al líder y suben con la **EXP** de los combates (un tercio si se pierde).
- **Mascotas**: 6, con tres formas (evolucionan a nivel 20 y 40), una bonificación para todo
  el equipo y una habilidad en combate. Azurion viene de serie; el resto se libera con jefes o
  se compra. Van hasta 3.
- **Equipamiento**: 8 ranuras por portador (el líder y cada héroe), 7 rarezas. Se mejora con
  oro hasta +10, se bloquea y se vende. Las armas son de un papel.
- **Expediciones AFK**: 5 zonas (se abren al vencer a su jefe) y 3 dificultades. Al recoger se
  reparte según el tiempo pasado, con un límite de 8, 12 o 24 h y venta automática por rareza.
- **Gremios y raid**: cada personaje puede estar en un gremio (hasta 20). Cada semana el
  gremio tiene un jefe de raid con vida compartida; cada personaje ataca 3 veces al día
  (15 rondas, el jefe pega como uno de su nivel) y gana **sellos de incursión** según el daño,
  más 25 para todos cuando cae. Los sellos pagan estrellas de héroe (+10 % cada una),
  EXP para las mascotas y el cofre primordial. Nunca éter.
- Todo cambio de éter queda en el **histórico** con el saldo resultante.
- `make balance` simula combates del equipo típico de cada momento (`Benchmark`) contra cada
  jefe; con `ARGS=--calibrate`, propone la `partyScale` de cada uno.

## API (todas bajo sesión salvo `/api/auth/*` y `/public/*`)

| Método | Ruta | |
|---|---|---|
| POST | `/api/auth/login` · `/refresh` · `/logout` | sesión vía Keycloak (sin registro) |
| GET | `/api/me` | cuenta + resumen |
| GET/POST | `/api/characters` | listar / crear `{name, class}` |
| GET/PATCH/DELETE | `/api/characters/{id}` | ficha / editar `{name?, class?}` / borrar |
| GET/POST | `/api/characters/{id}/movements` | histórico paginado / `{amount, note?}` con signo |
| GET | `/api/characters/{id}/bosses` | jefes con estado para ese héroe |
| POST | `/api/characters/{id}/battles` | `{boss}` → registro del combate y lo ganado |
| GET/POST | `/api/characters/{id}/heroes` | compañía / reclutar `{hero}` con oro |
| PUT | `/api/characters/{id}/party` | `{heroes: [...]}` → quiénes van en el equipo |
| GET/POST | `/api/characters/{id}/pets` | mascotas / comprar `{pet}` con oro |
| PUT | `/api/characters/{id}/pets/active` | `{pets: [...]}` → cuáles van con el equipo |
| GET | `/api/characters/{id}/gear` | inventario |
| POST | `/api/characters/{id}/gear/{item}/{acción}` | `equip {wearer}` · `unequip` · `lock` · `unlock` · `upgrade` · `sell` |
| GET/POST | `/api/characters/{id}/loadout/{wearer}[/auto]` | lo que lleva puesto `leader` o un héroe / autoequipar |
| GET/POST | `/api/characters/{id}/expedition` | estado y zonas / salir `{zone, difficulty, limit_hours, autosell}` |
| POST | `/api/characters/{id}/expedition/claim` | recoger la expedición en marcha |
| GET/POST | `/api/characters/{id}/guild` | gremio y raid (o gremios a los que unirse) / fundar `{name}` |
| POST | `/api/characters/{id}/guild/join` · `/guild/leave` | unirse `{guild}` / salir |
| POST | `/api/characters/{id}/raid/attack` | atacar en la raid (misma forma que un combate) |
| POST | `/api/characters/{id}/heroes/{hero}/star` · `/pets/{pet}/feed` · `/seals/chest` | gastar sellos |
| GET | `/public/catalog` | clases, ventajas, jefes, héroes, mascotas, equipamiento y reglas |
