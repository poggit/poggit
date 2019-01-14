# Poggit
The PocketMine Plugin Platform

## Installation
### Setup
1. `mkdir secrets`
2. Edit `secrets/secrets.js`, which should export an object assignable to the type in `server/secrets.ts`
3. `cp default-docker-compose.yml docker-compose.yml`
4. Edit `docker-compose.yml`
5. `docker-compose build`

### Starting
```bash
docker-compose up -d
```
