# Poggit/Rocket
This is an attempt or rewriting Poggit in Rust using Rocket.rs.

## Structure
- `db`: A PostgreSQL server on which everything is stored.
- `common`: The common library module for all binaries
- `backend`: The backend for executing business logic mutations. Only exposed to other containers.
- `builder`: Exposed on poggit-webhook.pmmp.io, handling GitHub webhook deliveries. Spawns child contsiners when containerized execution is required.
- `ci`: Exposed on ci.pmmp.io, providing an almost-read-only user interface for dev builds.
- `plugins`: Exposed on plugins.pmmp.io, providing the user interface for viewing, downloading, submitting and reviewing releases.
- `proxy`: Exposed on poggit.pmmp.io as a redirect server for old links.

Each component is hosted in its own Docker container. Frontend servers communicate with the backend using GraphQL protocol.

## Roadmap
- [ ] Basic structure and experimenting library features (most rewrites failed at this point)
- [ ] Create web frontend templates and routes
- [ ] Implement GraphQL backend interface
- [ ] Implement login system
- [ ] Implement release submission
- [ ] Implement miscellaneous CRUD interfaces (e.g. ban lists, API updaters)
- [ ] Implement webhook server
- [ ] Implement migration system
