# EventFlow Diagrams

These editable diagrams describe the application as implemented in the repository, using the Laravel routes, middleware, controllers, policies, models, migrations, tests, and configuration as evidence. Diagram descriptions and the source files should be updated together when behavior changes.

## Diagram index

| Diagram | Purpose |
| --- | --- |
| [Overall system architecture](system-architecture.svg) ([Mermaid source](system-architecture.mmd)) | Shows browser requests, Laravel's MVC layers, persistence, the reported hosting target, and the password-reset mail integration. |
| [Authentication and authorization](authentication-flowchart.svg) ([Mermaid source](authentication-flowchart.mmd)) | Covers login, throttling, session renewal, active-account and administrator checks, logout, and password reset. |
| [Event management](event-management-flowchart.svg) ([Mermaid source](event-management-flowchart.mmd)) | Shows event creation, category and schedule validation, membership, leader-only changes, notifications, and soft deletion. |
| [Task management](task-management-flowchart.svg) ([Mermaid source](task-management-flowchart.mmd)) | Shows task creation/assignment, deadlines, role-specific updates, status-derived progress, notifications, and archiving. |
| [Administrator workflow](administrator-flowchart.svg) ([Mermaid source](administrator-flowchart.mmd)) | Summarizes permission-gated user, event, task, category, announcement, setting, reporting, and audit functions. |
| [Database ERD](database-erd.svg) ([Mermaid source](database-erd.mmd)) | Documents application and Laravel infrastructure tables, key relationships, polymorphic records, and important constraints. |
| [Deployment architecture](deployment-architecture.svg) ([Mermaid source](deployment-architecture.mmd)) | Shows the local Laragon/Git/GitHub workflow and marks unverified Railway production steps. |

The architecture color key is blue for the client, green for the application, purple for data storage, and orange for external services. Flowcharts use standard symbols: ovals for start/end, rectangles for processes, diamonds for decisions, and parallelograms for input/output.

## Editing and rendering

Edit the `.mmd` source in a text editor or a Mermaid-aware editor. Render SVGs locally from the repository root with the Mermaid CLI; this does not add project dependencies:

```powershell
npx --yes --package @mermaid-js/mermaid-cli mmdc `
  -i docs/diagrams/system-architecture.mmd `
  -o docs/diagrams/system-architecture.svg
```

The Mermaid CLI requires a compatible local Chrome/Chromium browser. If it reports that no browser is installed, install the matching headless browser with the Puppeteer version used by the CLI, or pass an installed browser path with `mmdc -p <puppeteer-config.json>`.

Repeat with each matching `.mmd` and `.svg` filename to refresh the other diagrams. For example, render all diagrams in PowerShell:

```powershell
Get-ChildItem docs\diagrams\*.mmd | ForEach-Object {
    $svgPath = [System.IO.Path]::ChangeExtension($_.FullName, '.svg')
    npx --yes --package @mermaid-js/mermaid-cli mmdc -i $_.FullName -o $svgPath
}
```

The SVGs are generated outputs; make diagram changes in the Mermaid source, then regenerate the corresponding SVG. Keep both versions committed so GitHub and other Markdown viewers can display the diagrams without rendering Mermaid.

## Verified implementation notes

- Authentication is shared by ordinary users and administrators. Protected routes use `auth`, `active.account`, and authenticated-response caching middleware; administrator routes add the `administrator` middleware and per-route permission gates. A super administrator passes the defined administrator permission check.
- Any authenticated user can create an event. The creator is added as its initial `leader`; event membership is required to view it, and leaders manage event details, membership, and tasks. Events and tasks use soft deletion.
- A task assignee must belong to the same event. The database enforces that assignment through the composite `(event_id, assigned_to)` foreign key to `event_members`; unassigned tasks are permitted. Progress is derived from task statuses rather than a separate progress column.
- User notifications are persisted in Laravel's `notifications` table. Scheduled reminder and announcement commands are registered to run each minute. Brevo is wired for password-reset email; it is not the channel used for in-app notifications.
- Google Calendar token columns and its event-mapping table are removed by a later migration. No active Google Calendar routes or integration were found.
- The repository documents SQLite as the local/test default and includes MySQL configuration. It does not contain a Railway deployment manifest or deployment workflow. Railway and production MySQL are shown as reported targets, not verified live infrastructure; confirm the actual Railway build, runtime, scheduler, and database configuration before treating those deployment details as factual.

## ERD interpretation

The ERD distinguishes database constraints from ORM conventions. For example, `events.category` is a string linked to `event_categories.name` by application convention only; no foreign key exists. `notifications` and the target of an audit log use polymorphic type/id pairs, not relational foreign keys. `sessions.user_id` is indexed but also has no foreign key. Framework cache, session, password-reset, and queue tables are included because they are defined by repository migrations.
