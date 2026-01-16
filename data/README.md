# Data Directory

This directory contains the SQLite database for the ByaHero bus tracking system.

## Database Location

The SQLite database file will be created at: `data/db.sqlite`

## Initialization

Before running the application for the first time, you need to initialize the database:

### Option 1: Using the CLI Script (Recommended)

```bash
php init_db.php
```

This script will:
- Create the `buses` table
- Seed the database with three initial buses: BUS-001, BUS-002, BUS-003
- Each bus starts with 40 total seats, all available, and status "available"

### Option 2: Using the API

Visit the following URL in your browser after starting the server:

```
http://localhost:8000/api.php?action=init_db
```

## Database Schema

### Buses Table

| Column           | Type     | Description                                    |
|------------------|----------|------------------------------------------------|
| id               | INTEGER  | Primary key, auto-increment                    |
| code             | TEXT     | Unique bus code (e.g., BUS-001)                |
| route            | TEXT     | Current route name                             |
| lat              | REAL     | Latitude coordinate                            |
| lng              | REAL     | Longitude coordinate                           |
| seats_total      | INTEGER  | Total number of seats (default: 40)            |
| seats_available  | INTEGER  | Number of available seats                      |
| status           | TEXT     | Bus status (available, on_stop, full, unavailable) |
| updated_at       | DATETIME | Last update timestamp                          |

## Notes

- The database file (`db.sqlite`) is not included in version control
- Make sure this directory has write permissions for the PHP process
- The database is automatically created when you run the initialization script
