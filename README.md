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
- **Jefes**: 7, en orden; cada héroe desbloquea el siguiente al vencer al anterior. Ganar da
  éter; perder quita una cuarta parte de la recompensa (nunca deja en negativo).
- Todo cambio de éter (manual o de combate) queda en el **histórico** con el saldo resultante.
- `make balance` simula combates de cada clase contra cada jefe para ajustar números.

## API (todas bajo sesión salvo `/api/auth/*` y `/public/*`)

| Método | Ruta | |
|---|---|---|
| POST | `/api/auth/login` · `/refresh` · `/logout` | sesión vía Keycloak (sin registro) |
| GET | `/api/me` | cuenta + resumen |
| GET/POST | `/api/characters` | listar / crear `{name, class}` |
| GET/PATCH/DELETE | `/api/characters/{id}` | ficha / editar `{name?, class?}` / borrar |
| GET/POST | `/api/characters/{id}/movements` | histórico paginado / `{amount, note?}` con signo |
| GET | `/api/characters/{id}/bosses` | jefes con estado para ese héroe |
| POST | `/api/characters/{id}/battles` | `{boss}` → registro del combate + movimiento |
| GET | `/public/catalog` | clases, ventajas, jefes y reglas |
