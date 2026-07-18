# StudyPoint Backend API

Laravel 12 REST API for StudyPoint Study Library.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure `.env`:
```
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=study-point
DB_USERNAME=root
DB_PASSWORD=root
FRONTEND_URL=http://localhost:5173
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173
```

```bash
php artisan migrate:fresh --seed
php artisan serve
```

API base: `http://localhost:8000/api/v1`

## Tests

```bash
php artisan test
```

## Architecture

```
app/
├── Enums/           # AdmissionStatus, StudentStatus, etc.
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Requests/
│   └── Resources/
├── Models/
├── Services/        # Business logic (AdmissionService)
└── Support/         # ApiResponse helper
```

## Initial Login

Super admin is **not** created by `db:seed`. Create it during the SoftKatta **install wizard** (`/install`) with the name, email, and password you choose.

`php artisan migrate:fresh --seed` only seeds roles/permissions and demo catalog data (branches, plans, etc.).

See `../docs/api.md` for full API documentation.
