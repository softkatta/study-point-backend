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

## Initial Login (after seed)

The seeder creates **roles, super admin, starter branches, plans, facilities, FAQs and testimonials**. Staff users and live admissions must still be added from the admin panel.

Default credentials (override via `.env`):

- `admin@studypoint.in` / value of `SEED_SUPER_ADMIN_PASSWORD` (super_admin)

```
SEED_SUPER_ADMIN_EMAIL=admin@studypoint.in
SEED_SUPER_ADMIN_NAME=Super Admin
SEED_SUPER_ADMIN_PASSWORD=change-me-before-seeding
```

Re-running `php artisan db:seed` updates the super admin name/status but **does not reset the password**.

See `../docs/api.md` for full API documentation.
