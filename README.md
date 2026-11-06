# EventFlow

EventFlow is a Laravel application for coordinating school and community events. Members can manage event schedules, assign and track tasks, view a personal calendar, receive in-app notifications, and optionally export events to Google Calendar. Administrators have a separate platform area for managing users, events, tasks, categories, and audit history.

## Requirements

- PHP 8.3 or later with the extensions required by Laravel
- Composer
- Node.js 22.12 or later and npm
- SQLite (the default) or another database supported by Laravel

## Local setup

From the project directory:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

In another terminal, run `npm run dev` while developing frontend assets. Configure `APP_URL` to match the local application URL. The default database connection is SQLite; create `database/database.sqlite` if it does not exist before running migrations.

Alternatively, `composer run setup` runs the repository's setup script, including dependency installation, key generation, migrations, and the frontend build.

## Configuration

Set these values in `.env` as needed:

| Variable | Purpose |
| --- | --- |
| `APP_URL` | Application base URL used for generated links and OAuth callbacks. |
| `APP_TIMEZONE` | Timezone used to interpret event schedules and task deadlines. Defaults to `Asia/Jakarta`. |
| `DB_CONNECTION` | Database connection; defaults to `sqlite`. |
| `GOOGLE_CLIENT_ID` | OAuth client ID for optional Google Calendar integration. |
| `GOOGLE_CLIENT_SECRET` | OAuth client secret. Keep this value private. |
| `GOOGLE_REDIRECT_URI` | Optional explicit callback URL. If omitted, EventFlow uses the named OAuth callback route. |

For Google Calendar, register the callback URL (`/integrations/google-calendar/callback` on your configured `APP_URL`) in the Google OAuth client. EventFlow requests only the `calendar.events` scope. OAuth tokens are encrypted before storage. Without Google credentials, the calendar remains usable locally and the integration displays a configuration message.

## Accounts and administration

New users can register from the sign-in page. The registration endpoint does not accept a system role; new accounts are ordinary users.

Create the initial administrator from a trusted terminal:

```powershell
php artisan admin:provision admin@example.com
```

The command prompts for a name, a confirmed password, and final confirmation. The password must be at least 12 characters and include mixed case, a number, and a symbol. It refuses to create another initial administrator when one already exists. After provisioning, sign in and open `/admin` to manage users, access status, events, tasks, shared event categories, and audit logs.

Suspending an account invalidates its remember token and database-backed sessions. Accounts with linked events, tasks, or memberships cannot be deleted until those relationships are resolved. EventFlow also prevents an administrator from removing their own administrator access through the user-management panel.

## Scheduling and notifications

Event start/end times and new or changed task deadlines must be in the future, interpreted in `APP_TIMEZONE`. Existing historical schedule values can remain unchanged while other details are edited.

In-app notifications cover task assignment and status changes, event updates and membership additions, and deadline reminders. Users can choose notification types in **Profile & security**. Reminder delivery is scheduled every minute:

```powershell
php artisan schedule:work
```

On a production host, configure Laravel's scheduler using the standard once-per-minute `schedule:run` cron entry.

## Common commands

```powershell
php artisan migrate
php artisan route:list
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
```

Tests use an in-memory SQLite database and do not require Google credentials; external HTTP requests are faked in the integration tests.
