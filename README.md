# MDRRMO PHP REST API

PHP 8.3 REST API for the MDRRMO emergency-reporting form. It stores reports in PostgreSQL and adds a PostGIS geographic point/index when the extension is available.

## Repository structure

```text
mdrrmo-api/
├── public/
│   └── api/
│       └── incidents/
│           ├── create.php
│           ├── list.php
│           └── view.php
├── src/
│   ├── database.php
│   └── db_connection.php
├── .dockerignore
├── .gitignore
├── Dockerfile
├── README.md
└── test-db.php
```

## API endpoints

Set `API_URL` to your Render service URL (for example, `https://your-service.onrender.com`).

| Method | URL | Purpose |
| --- | --- | --- |
| `POST` | `${API_URL}/api/incidents/create.php` | Validate and save an emergency report |
| `GET` | `${API_URL}/api/incidents/list.php?limit=50` | List recent reports (requires a bearer token) |
| `GET` | `${API_URL}/api/incidents/view.php?id=123` | View one report (requires a bearer token) |
| `GET` | `${API_URL}/` | Health response |

### Create report JSON

```json
{
  "incident_type": "Flood",
  "description": "Water is rising near the bridge.",
  "persons_affected": 0,
  "latitude": 14.5995,
  "longitude": 120.9842,
  "gps_accuracy_m": 12
}
```

The API returns `201 Created` and the saved report. Invalid fields return `422`. Photos are not uploaded or stored by this API.

## GitHub setup

1. Sign in to GitHub and open the `mdrrmo-api` repository.
2. Choose **Add file > Upload files**.
3. Upload the contents of this folder, preserving the paths shown above.
4. Commit the files to the `main` branch. Keep the repository structure limited to the tree above.

## Render setup

1. Create or use a Render PostgreSQL database. Enable PostGIS if your database plan supports it; the API also stores numeric coordinates without PostGIS.
2. In Render, create a **Web Service** connected to the GitHub `mdrrmo-api` repository and select the **Docker** runtime. The included Dockerfile builds PHP with `pdo_pgsql`.
3. Add `DATABASE_URL` using the database's internal connection URL. Set `DB_SSLMODE=require` when TLS is required (the default).
4. Set `CORS_ALLOW_ORIGIN` to the exact Hatchable site origin, without a trailing slash. For this project it is `https://mdrrmo-constituents-u9wv.hatchable.site`.
5. Set `API_READ_TOKEN` to a long, unique secret in Render's environment settings. Keep it private. The `list.php` and `view.php` endpoints reject requests without `Authorization: Bearer <token>`.
6. Deploy, then open the Render URL. The root path should return `{"ok":true,"service":"MDRRMO Incidents API"}`.

The `incident_reports` table is created on the first API request. The API does not need or store a Hatchable database credential. Do not commit database URLs, tokens, passwords, or `.env` files to GitHub.

## Connect the Hatchable form

The reporting form should send a JSON `POST` to `${API_URL}/api/incidents/create.php` with the fields above. Map the form's **GPS accuracy (meters)** field to `gps_accuracy_m`. Keep optional photos in the browser; this starter endpoint does not upload them. Set `CORS_ALLOW_ORIGIN` to the deployed Hatchable origin.

This is a classroom prototype. The form must clearly say reports are not monitored for emergency response. Add staff authentication and a reviewed operational privacy/security design before collecting real incident reports.

## Database connection check

Set `DATABASE_URL` in the local environment, then run `php test-db.php` from the project folder. It prints PostgreSQL and (if installed) PostGIS versions.

