# Deploying to Render

This application needs both PHP and Node.js during deployment. Render's Node
runtime does not include Composer, so deploy it as a Docker web service instead.

1. Commit and push `Dockerfile` and `.dockerignore` to the GitHub branch Render
   deploys.
2. In Render, create a **new Web Service** for the repository and select
   **Docker** as the runtime. An existing service created with the Node runtime
   will keep using that runtime.
3. Create a Render PostgreSQL database and set these web-service environment
   variables:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_URL=https://<your-render-service>.onrender.com`
   - `APP_KEY=<output of php artisan key:generate --show>`
   - `DB_CONNECTION=pgsql`
   - `DB_URL=<the database's internal connection URL>`
   - `SESSION_DRIVER=database`
   - `CACHE_STORE=database`
   - `QUEUE_CONNECTION=database`
4. Deploy. The container installs PHP dependencies with Composer, builds the
   frontend assets, runs database migrations, and starts Laravel on Render's
   assigned port.

Do not use the local SQLite database for production data on Render's ephemeral
filesystem. Files uploaded to the local storage disk are also ephemeral; use
persistent storage or an object-storage service if those files must survive
redeploys.
