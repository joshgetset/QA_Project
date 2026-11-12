<p align="center"></p>

<p align="center">
![alt text](image.png)   <a href="https://themeselection.com/item/materio-dashboard-free-laravel/" target="_blank">
      <img src="https://cdn.themeselection.com/ts-assets/materio/logo/logo.png" alt="materio-logo" width="40px" height="auto">
   </a>
</p>

<h1 align="center">
   <a href="https://themeselection.com/item/materio-dashboard-free-laravel/" target="_blank" align="center">
      Materio - Bootstrap 5 HTML + Laravel Admin Template
   </a>
</h1>

<p align="center">Most Powerful & Comprehensive Bootstrap 5 HTML + Laravel Admin Dashboard Template built for developers!</p>

<p align="center">
  <a href="https://github.com/themeselection/materio-bootstrap-html-laravel-admin-template-free/blob/main/LICENSE">
    <img src="https://img.shields.io/github/license/themeselection/materio-bootstrap-html-laravel-admin-template-free" alt="license">
  </a>
  <a href="https://github.com/themeselection/materio-bootstrap-html-laravel-admin-template-free/releases/">
    <img src="https://img.shields.io/github/release/themeselection/materio-bootstrap-html-laravel-admin-template-free.svg" alt="GitHub release">
  </a>
  <a href="https://github.com/themeselection/materio-bootstrap-html-laravel-admin-template-free/issues">
    <img src="https://img.shields.io/github/issues/themeselection/materio-bootstrap-html-laravel-admin-template-free.svg" alt="GitHub issues">
  </a>
  <a href="https://github.com/themeselection/materio-bootstrap-html-laravel-admin-template-free/issues">
    <img src="https://img.shields.io/github/issues-closed/themeselection/materio-bootstrap-html-laravel-admin-template-free.svg" alt="GitHub closed issues">
  </a>
  <a href="https://twitter.com/Theme_Selection" target="_blank">
    <img alt="Twitter Follow" src="https://img.shields.io/twitter/follow/Theme_Selection">
  </a>
</p>

<kbd>[![Materio - Bootstrap 5 HTML Laravel Admin Template Demo Screenshot](https://cdn.themeselection.com/ts-assets/materio/materio-bootstrap-laravel-admin-template-free/banner/banner.png)](https://themeselection.com/item/materio-dashboard-free-laravel/)</kbd>

## Introduction 🚀

If you’re a developer looking for the most Powerful & comprehensive [**Free Bootstrap 5 HTML Laravel Admin Template**](https://themeselection.com/item/materio-dashboard-free-laravel/) built for developers, rich with features, and highly customizable look no further than Materio. We’ve followed the highest industry standards to bring you the very best admin template that is not only fast and easy to use but highly scalable. Offering ultimate convenience and flexibility, you’ll be able to build whatever application you want with very little hassle.

Build premium quality applications with ease. Use our innovative **[Laravel admin template](https://themeselection.com/item/category/laravel-admin-templates/)** to create eye-catching, high-quality WebApps. Your apps will be completely responsive, ensuring they’ll look stunning and function flawlessly on desktops, tablets, and mobile devices.

[View Demo](https://demos.themeselection.com/materio-bootstrap-html-laravel-admin-template-free/demo/)

## Installation ⚒️

Installing and running Materio is super easy, please Follow below steps and you will be ready to rock 🤘

1. Open the terminal in your root directory of Materio Laravel.
2. Use the following command to install the composer

```bash
composer install
```

3. Run the following command to generate the key

```bash
php artisan key:generate
```

4. By running the following command, you will be able to get all the dependencies in your **node_modules** folder:

```bash
yarn
```

5. To run the project, you need to run the following command in the project directory. It will compile JavaScript and Styles.

```bash
yarn build
```

6. To serve the application, you need to run the following command in the project directory

```bash
php artisan serve
```

7. Now navigate to the given address, and you will see your application is running.🥳

## QAMS University System Integrations

The authenticated QAMS dashboard displays the 16 university systems and refreshes configured system data every 30 seconds. Until an API URL is configured, cards display sample values. Configure each system's `QAMS_*_API_URL` and optional `QAMS_*_API_TOKEN` in `.env`; tokens are sent as Bearer authorization headers. The dashboard API is available at `GET /api/qams/systems`. Selecting a card opens that system's full metrics page.

Each connected system should expose a JSON `GET` endpoint returning the normalized shape below. `metrics` may contain any number of label/value pairs; values can be strings or numbers.

```json
{
  "summary": "Enrollment data received.",
  "metrics": [
    { "label": "Enrolled students", "value": 8420 },
    { "label": "Pending requests", "value": 18 }
  ]
}
```

Configure API URLs only for trusted university systems. Failed or invalid API responses are logged and shown as unavailable; sample values are never substituted for a configured API that fails.

### CES campus data API

CES can publish an aggregated, API-key-protected summary from its six local MySQL databases at `GET /api/ces/enrollment-summary`. The API returns campus student-record counts and a total; it does not return student records.

Configure `CES_API_KEY` and the separate `CES_DB_*` values in `.env`. Create a MySQL account for `CES_DB_USERNAME` with `SELECT` permission only on the required tables listed below; do not use the application's database administrator account. The application host must be allowed to connect to MySQL. Do not commit `.env` or send API keys/passwords in chat. Keep the API reachable only by trusted clients, use HTTPS outside local development, and rotate the key if it is exposed.

The authenticated CES metrics page fetches live data from the session-protected `GET /dashboard/systems/ces/analytics` route, filtering by campus, college, program, and academic year. The browser does not receive or send the CES API key. Results are cached for 30 seconds; unavailable campuses are reported and totals are marked partial rather than silently presented as complete. **Total Enrolled Students** is calculated per campus as `COUNT(DISTINCT grades20261.StudentNo)` for non-empty student numbers. `grades20261` supplies the 2026–2027 enrollment cohort; college and program filters use `students.Course` joined to `course` and `department`. Campus totals are added together and are distinct within each campus, not globally deduplicated across campuses. Demographic counts use that same distinct grades-table cohort, joined to `students` and `students2` for profile fields. "New Freshman" and graduating-enrollee metrics continue to use finalized `registration` rows and their `StudentStatus` / `isGraduating` fields for the selected 2026 academic year. International-student, 4Ps, and disability counts use `students.nationality`, `students2.Family4Ps`, and `students.Disability` respectively. Other population cards remain visible as unavailable until their source fields and definitions are confirmed; they never display sample values.

For a local MySQL server at `127.0.0.1`, use MySQL Workbench to create a dedicated read-only account and grant it access only to the analytics and summary source tables:

```sql
CREATE USER 'ces_reader'@'127.0.0.1' IDENTIFIED BY 'replace-with-a-long-random-password';
GRANT SELECT ON enrolment_sogod.students TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_sogod.students2 TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_sogod.registration TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_sogod.grades20261 TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_sogod.course TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_sogod.department TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_bontoc.students TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_bontoc.students2 TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_bontoc.registration TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_bontoc.grades20261 TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_bontoc.course TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_bontoc.department TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_tomasoppus.students TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_tomasoppus.students2 TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_tomasoppus.registration TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_tomasoppus.grades20261 TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_tomasoppus.course TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_tomasoppus.department TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_hinunangan.students TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_hinunangan.students2 TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_hinunangan.registration TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_hinunangan.grades20261 TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_hinunangan.course TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_hinunangan.department TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_maasin.students TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_maasin.students2 TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_maasin.registration TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_maasin.grades20261 TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_maasin.course TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_maasin.department TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_sanjuan.students TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_sanjuan.students2 TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_sanjuan.registration TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_sanjuan.grades20261 TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_sanjuan.course TO 'ces_reader'@'127.0.0.1';
GRANT SELECT ON enrolment_sanjuan.department TO 'ces_reader'@'127.0.0.1';
```

In the local `.env`, set `CES_DB_USERNAME=ces_reader`, `CES_DB_PASSWORD` to the password you chose, and keep `CES_DB_DATABASE=information_schema`. Generate an API key locally (for example, with `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`), then set the result as both `CES_API_KEY` and `QAMS_CES_API_TOKEN`. Keep these values private. After editing `.env`, run `php artisan config:clear`.

To have the QAMS dashboard consume this endpoint, set `QAMS_CES_API_URL` to its reachable URL and set `QAMS_CES_API_TOKEN` to the same secret as `CES_API_KEY`. For local testing, the URL is typically `http://127.0.0.1:8000/api/ces/enrollment-summary`. The dashboard client already sends the configured token as a Bearer token and accepts the response's `summary` and `metrics` fields.

When this URL points back to the same local Laravel app, the dashboard reads the CES enrollment analytics directly instead of making a self-HTTP request. The CES summary card uses distinct students in `grades20261` and does not use the API's student-record counts as enrollment totals.

Use `QAMS_<REAL_SYSTEM_NAME>_API_URL` and `QAMS_<REAL_SYSTEM_NAME>_API_TOKEN` for future integrations; do not use internal numeric system IDs in environment variable names. Existing numbered API placeholders have been renamed to descriptive system names in `.env.example` and `config/qams.php`.

The campus database names are allow-listed in `config/ces.php`; request parameters cannot select a database or table. Future system integrations should follow the same pattern: each system exposes a small API backed by its own databases, while QAMS calls the system API with its key rather than connecting directly to a growing set of remote databases.

### Scaling integrations to multiple systems

The target architecture is up to 16 system APIs, with each system API responsible for aggregating data from its own campus databases (up to six). QAMS should call the system APIs, not connect directly to as many as 96 databases. Keep each API URL and credential server-side, use a separate credential per integration, and return a consistent response such as `{ "summary": "...", "metrics": [{ "label": "...", "value": 0 }] }`. The shared executive dashboard can display those normalized metrics without a new JavaScript file for every system.

`resources/assets/js/ces-analytics.js` is CES-specific: it implements CES campus, college, program, and academic-year filters and enrollment demographics. It is not automatically used by other systems. A system needing its own detailed analytics page will need a dedicated page/script or a future reusable analytics UI with a shared schema.

Before connecting a second live system, generalize the CES-specific branches in `app/Services/QamsSystemData.php` (including its direct CES analytics dependency and `system-17` checks) behind a system integration boundary. Keep CES behavior intact while doing so; other systems should then use the common API, caching, and availability handling without adding more CES-specific conditionals.

### CES API implementation change log

- 2026-11-12: Documented the target architecture for 16 system APIs aggregating up to six campus databases each, clarified that CES analytics JavaScript is CES-specific, and noted the backend generalization needed before the second live integration. Files changed: `README.md`.
- 2026-11-12: Updated CES demographic chart state from the latest analytics response so switching from cards to chart displays fetched male/female values instead of initial empty placeholders. Files changed: `resources/assets/js/ces-analytics.js` and `README.md`.
- 2026-11-12: Hardened dashboard refresh rendering for systems without a `metrics` array, made trend-entry lookup tolerant of browsers without Navigation Timing entries, and clear the CES empty-state message once live metrics arrive. Files changed: `resources/assets/js/qams-dashboard.js` and `README.md`.
- 2026-11-12: Added blue SweetAlert2 confirmation dialogs before changing the pillar or logging out, with pillar selection restored on cancellation. Files changed: `resources/assets/js/qams-confirmations.js`, `resources/views/layouts/sections/navbar/navbar-partial.blade.php`, `resources/views/layouts/sections/scripts.blade.php`, `tests/Feature/QamsDashboardTest.php`, and `README.md`.
- 2026-11-12: Fixed the pillar selector appearing over its SweetAlert confirmation by blurring the native select and allowing its popup to close before displaying the dialog; guard against overlapping confirmations. Files changed: `resources/assets/js/qams-confirmations.js` and `README.md`.
- 2026-11-12: Verified local executive-dashboard refresh calls CES enrollment analytics directly and does not issue requests to unconfigured system APIs; included HTTP/network failure details in the refresh warning and added a dashboard-route regression test. Files changed: `resources/assets/js/qams-dashboard.js`, `tests/Feature/QamsDashboardTest.php`, and `README.md`.
- 2026-11-12: Switched CES total-enrollment and demographic cohort queries to distinct students in `grades20261`, using `students`/`students2` for profile fields and course joins for college/program filters. Kept new-freshman and graduating indicators on their existing finalized-registration fields; added the grade table to read-only database grants. Files changed: `app/Services/CesEnrollmentAnalytics.php` and `README.md`.
- 2026-11-12: Changed the executive dashboard CES metric to show only finalized, distinct enrolled students for the latest available academic year; student-record counts and partial-campus totals are excluded. Added the academic-year label and tests for local direct fetching and rejecting partial results. Files changed: `app/Services/QamsSystemData.php`, `resources/assets/js/qams-dashboard.js`, `resources/views/personal/dashboard.blade.php`, `tests/Feature/QamsDashboardTest.php`, and `README.md`.
- 2026-11-12: Removed the local dashboard's self-HTTP call to the CES summary API, which could time out against the same single-worker Laravel server. The dashboard reads CES data through a local service; the API retains its aggregate student-record summary endpoint. Files changed: `app/Services/CesEnrollmentSummary.php`, `app/Http/Controllers/Api/CesEnrollmentSummaryController.php`, `app/Services/QamsSystemData.php`, `resources/assets/js/qams-dashboard.js`, `resources/views/personal/dashboard.blade.php`, `tests/Feature/QamsDashboardTest.php`, and `README.md`.
- 2026-11-12: Preserved the last successful connected-system response for one day so navigation-only executive-dashboard visits can display cached metrics without triggering API refreshes; identified cached statuses distinctly and clarified the CES empty-state message. Files changed: `app/Services/QamsSystemData.php`, `resources/assets/js/qams-dashboard.js`, `resources/assets/css/custom/dashboard.css`, `resources/views/personal/dashboard.blade.php`, `tests/Feature/QamsDashboardTest.php`, and `README.md`.
- 2026-11-12: Limited automatic executive-dashboard data refresh to full page reloads; navigating to the dashboard no longer triggers a refresh, while the Refresh button remains available. Files changed: `resources/assets/js/qams-dashboard.js`, `tests/Feature/QamsDashboardTest.php`, and `README.md`.
- 2026-11-12: Made the executive dashboard render from cached values before refreshing connected APIs in the background, and limited its enrolled-student summary to explicitly labeled enrollment metrics instead of the API's first metric. Files changed: `app/Services/QamsSystemData.php`, `app/Http/Controllers/dashboard/Analytics.php`, `resources/views/personal/dashboard.blade.php`, `resources/assets/js/qams-dashboard.js`, `tests/Feature/QamsDashboardTest.php`, and `README.md`.
- 2026-11-12: Changed executive-dashboard system API refreshes to run concurrently, reducing navigation wait when multiple integrations have expired caches while retaining the existing cache interval and per-system failure states. Files changed: `app/Services/QamsSystemData.php`, `tests/Feature/QamsDashboardTest.php`, and `README.md`.
- 2026-11-12: Avoided a redundant CES summary API request when opening the CES metrics page; its displayed metrics continue to load from the authenticated analytics route. Files changed: `app/Http/Controllers/dashboard/Analytics.php`, `app/Services/QamsSystemData.php`, and `tests/Feature/QamsDashboardTest.php`.
- 2026-11-12: Removed the routine loading/success CES status banner; partial-data and request-error messages remain visible. Documented the enrolled-student count's source columns and aggregation scope. Files changed: `resources/assets/js/ces-analytics.js`, `resources/views/personal/system-metrics.blade.php`, `tests/Feature/QamsDashboardTest.php`, and `README.md`.
- 2026-11-12: Added the `ces_sources` MySQL connection and allow-listed campus configuration; added a Bearer-key middleware and rate-limited summary route; added an API controller that returns row counts only; added feature tests; documented read-only setup and API consumption.
- Files changed: `.env.example`, `README.md`, `app/Http/Controllers/Api/CesEnrollmentSummaryController.php`, `app/Http/Middleware/EnsureCesApiKey.php`, `bootstrap/app.php`, `config/ces.php`, `config/database.php`, `routes/api.php`, and `tests/Feature/CesEnrollmentSummaryApiTest.php`.
- 2026-11-12: Renamed numbered QAMS API variables to real-system-name variables; added blank CES API placeholders to the local `.env` and template; updated system configuration and setup guidance. Files changed: `.env`, `.env.example`, `README.md`, and `config/qams.php`.
- 2026-11-12: Replaced CES sample filter and card values with cached, authenticated queries across the six databases; added live campus/college/program/year filters, finalized-registration summary metrics, mapped demographic counts, and visible unavailable states for unmapped metrics. Files changed: `app/Http/Controllers/CesAnalyticsController.php`, `app/Services/CesEnrollmentAnalytics.php`, `config/qams.php`, `resources/assets/js/ces-analytics.js`, `resources/views/personal/system-metrics.blade.php`, `routes/web.php`, `tests/Feature/CesAnalyticsTest.php`, `tests/Feature/QamsDashboardTest.php`, and `README.md`.

## Available Tasks 🧑‍💻

**Building for Production:** If you want to run the project and make the build in the production mode then run the following command in the root directory, by default The project will continue to run in the development mode:

```bash
yarn prod
```

## What's Included 📦

- Dashboard
- Layouts
  - Without menu
  - Without Navbar
  - Container
  - Fluid
  - Blank
- Pages
  - Account Settings
  - Login
  - Register
  - Forgot Password
  - Error
  - Under Maintenance
- Cards
- User Interface
  - **All Bootstrap Components**
- Extended UI
  - Perfect Scrollbar
  - Text Divider
- Remix Icons
- Form Elements
  - Basic Inputs
  - Input Groups
- Form Layout
  - Vertical Form
  - Horizontal Form
- Tables

## What's in Premium Version 💎

| Materio Free Version                                                                              | Materio Premium Version                                                                                                                                                                       |
| ------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [Demo](https://demos.themeselection.com/materio-bootstrap-html-laravel-admin-template-free/demo/) | [Demo](https://demos.themeselection.com/materio-bootstrap-html-laravel-admin-template/demo-1/)                                                                                                |
| [Download](https://themeselection.com/item/materio-dashboard-free-laravel/)                       | [Purchase](https://themeselection.com/item/materio-dashboard-pro-laravel/)                                                                                                                    |
| Single vertical Menu                                                                              | Vertical Menu + Horizontal Menu                                                                                                                                                               |
| Simple Light Style                                                                                | Light, Dark & System Style                                                                                                                                                                    |
| Default Theme                                                                                     | Default, Semi Dark & Bordered Themes                                                                                                                                                          |
| Fixed Layout(Menu)                                                                                | Fixed & Static Layout(Menu)                                                                                                                                                                   |
| 1 Simple Dashboard                                                                                | 5 Niche Dashboards                                                                                                                                                                            |
| -                                                                                                 | Multiple Ready to use Application like **Laravel CRUD Application**, Email, Chat, Calendar, Kanban, eCommerce, Academy, Logistics, Invoice, Users List, Users View, Roles and Permission etc. |
| Simple Form Elements                                                                              | Advance form elements, validation & form wizard                                                                                                                                               |
| Basic Cards                                                                                       | Basic, Advance , Statistics, Analytics, Gamifications and Actions Cards                                                                                                                       |
| Basic User Interface(Components)                                                                  | Advance and Custom User Interfaces(Components)                                                                                                                                                |
| Two Extended Components                                                                           | Twelve Ready to use Extended Components                                                                                                                                                       |
| -                                                                                                 | Quick Search - Quickly navigate between pages (w/ hotkey support)                                                                                                                             |
| Basic Pages                                                                                       | Authentication Pages in 2 Variants + Ready to use pages like User Profile, Account Settings,FAQ, Help Center, Pricing, Misc etc.                                                              |
| -                                                                                                 | 3D Characters + Illustrations                                                                                                                                                                 |
| Basic tables                                                                                      | Advanced & Data tables                                                                                                                                                                        |
| -                                                                                                 | Quick customization using theme config file                                                                                                                                                   |
| -                                                                                                 | Leaflet Maps                                                                                                                                                                                  |
| 1 Chart Library                                                                                   | 2 Chart Libraries                                                                                                                                                                             |
| -                                                                                                 | Multiple Navbar & Menu Options                                                                                                                                                                |
| -                                                                                                 | Starter-kit                                                                                                                                                                                   |
| -                                                                                                 | **Localization support**                                                                                                                                                                      |
| -                                                                                                 | RTL Support                                                                                                                                                                                   |
| Regular Support                                                                                   | Priority Support                                                                                                                                                                              |
| Detailed Documentation                                                                            | Detailed Documentation                                                                                                                                                                        |

## Documentation 📜

<!-- If you have docs in wiki then use below line -->

Check GitHub [Wiki](https://github.com/themeselection/materio-bootstrap-html-laravel-admin-template-free/wiki) of this repo

<!-- If you have live docs then use below line -->

Check out our live [Documentation](https://demos.themeselection.com/materio-bootstrap-html-admin-template/documentation/laravel-introduction.html)

## Browser Support 🖥️

![chrome](https://github.com/nuxt/nuxt/assets/47495003/bbb6d7b0-2db6-4af4-abdc-a73de71dd287)
&nbsp;&nbsp;![firefox](https://github.com/nuxt/nuxt/assets/47495003/bca1f2d0-d597-453b-8525-5c94e36bfc33)
&nbsp;&nbsp;![safari](https://github.com/nuxt/nuxt/assets/47495003/8ecbb395-78fb-40fb-bb59-7301bf8a7e5d)
&nbsp;&nbsp;![Microsoft Edge](https://github.com/nuxt/nuxt/assets/47495003/f945821b-0cbd-464d-8103-824d4d5c4e9a)

## Contributing 🦸

Contribution are always welcome and recommended! Here is how:

- Fork the repository ([here is the guide](https://docs.github.com/en/get-started/quickstart/fork-a-repo)).
- Clone to your machine `git clone https://github.com/themeselection/materio-bootstrap-html-laravel-admin-template-free.git` Make your changes
- Create a pull request

### Contribution Requirements 🧰

- When you contribute, you agree to give a non-exclusive license to ThemeSelection to use that contribution in any context as we (ThemeSelection) see appropriate.
- If you use content provided by another party, it must be appropriately licensed using an open source license.
- Contributions are only accepted through Github pull requests.
- Finally, contributed code must work in all supported browsers (see above for browser support).

## Changelog 📆

Please refer to the [CHANGELOG](CHANGELOG.md) file. We will add a detailed release notes to each new release.

## Support 🧑🏻‍💻

For free products, enjoy community support via GitHub issues. Upgrade to Premium for dedicated support from our expert team.

## License &copy;

- Copyright © [ThemeSelection](https://themeselection.com/)
- Licensed under [MIT](LICENSE)
- All our free items are Open Source and licensed under MIT. You can use our free items for personal as well as commercial purposes. We just need an attribution from your end. Copy the below link and paste it at the footer of your web application or project.

  ```html
  <a href="https://themeselection.com/">ThemeSelection</a>
  ```

## Also Available In

<p>
  <!-- Figma -->
   <a href="https://themeselection.com/item/materio-figma-admin-dashboard-ui-kit/" target="_blank"><picture><source width="auto" height="74px" media="(prefers-color-scheme: dark)" srcset="https://github.com/microsoft/vscode/assets/47495003/0318a6c8-4f9b-4cf6-af5e-d357f909ea2b"><source width="auto" height="74px" media="(prefers-color-scheme: light)" srcset="https://github.com/microsoft/vscode/assets/47495003/47f21dfe-c1fc-4a7d-859e-4d98f8cdded1"><img width="auto" height="74px" alt="html" src="https://github.com/microsoft/vscode/assets/47495003/47f21dfe-c1fc-4a7d-859e-4d98f8cdded1"></picture></img></a>&nbsp;&nbsp;
   <!-- HTML -->
   <a href="https://themeselection.com/item/materio-dashboard-pro-bootstrap/" target="_blank"><picture><source width="auto" height="74px" media="(prefers-color-scheme: dark)" srcset="https://github.com/microsoft/vscode/assets/47495003/5fe77c46-2e4c-475a-8dec-e30e2badddee"><source width="auto" height="74px" media="(prefers-color-scheme: light)" srcset="https://github.com/microsoft/vscode/assets/47495003/3f5decd8-cd99-4ed3-aa76-528ca061385b"><img width="auto" height="74px" alt="html" src="https://github.com/microsoft/vscode/assets/47495003/3f5decd8-cd99-4ed3-aa76-528ca061385b"></picture></img></a>&nbsp;&nbsp;
   <!-- HTML + Laravel -->
   <a href="https://themeselection.com/item/materio-dashboard-pro-laravel/" target="_blank"><picture><source width="auto" height="74px" media="(prefers-color-scheme: dark)" srcset="https://github.com/microsoft/vscode/assets/47495003/efe420e4-9863-41b7-9eda-47ea94f21a62"><source width="auto" height="74px" media="(prefers-color-scheme: light)" srcset="https://github.com/microsoft/vscode/assets/47495003/be3b86e0-4d5e-4736-bf89-4267fb4d6710"><img width="auto" height="74px" alt="html_laravel" src="https://github.com/microsoft/vscode/assets/47495003/be3b86e0-4d5e-4736-bf89-4267fb4d6710"></picture></img></a>&nbsp;&nbsp;
   <!-- HTML + Django -->
   <a href="https://themeselection.com/item/materio-dashboard-pro-django/" target="_blank"><picture><source width="auto" height="74px" media="(prefers-color-scheme: dark)" srcset="https://github.com/microsoft/vscode/assets/47495003/3c87d33b-1223-4aaa-a652-388dcb714c98"><source width="auto" height="74px" media="(prefers-color-scheme: light)" srcset="https://github.com/microsoft/vscode/assets/47495003/51db1947-eac1-466f-87fd-5a209010fe9c"><img width="auto" height="74px" alt="html_django" src="https://github.com/microsoft/vscode/assets/47495003/51db1947-eac1-466f-87fd-5a209010fe9c"></picture></img></a>&nbsp;&nbsp;
   <!-- .Net Core -->
   <a href="https://themeselection.com/item/materio-aspnet-core-mvc-admin-template/" target="_blank"><picture><source width="auto" height="74px" media="(prefers-color-scheme: dark)" srcset="https://github.com/microsoft/vscode/assets/47495003/6327fd7b-9c54-4189-a852-28551ad0e002"><source width="auto" height="74px" media="(prefers-color-scheme: light)" srcset="https://github.com/microsoft/vscode/assets/47495003/9856e9d5-021f-4573-902a-702e80dd0102"><img width="auto" height="74px" alt="net_core" src="https://github.com/microsoft/vscode/assets/47495003/9856e9d5-021f-4573-902a-702e80dd0102"></picture></img></a>&nbsp;&nbsp;
   <!-- NextJS -->
   <a href="https://themeselection.com/item/materio-mui-react-nextjs-admin-template/" target="_blank"><picture><source width="auto" height="74px" media="(prefers-color-scheme: dark)" srcset="https://github.com/microsoft/vscode/assets/47495003/66344629-6d21-4f92-9078-f479b39cb34e"><source width="auto" height="74px" media="(prefers-color-scheme: light)" srcset="https://github.com/microsoft/vscode/assets/47495003/e1daf4e1-3fa5-4a44-969a-6143ddd67310"><img width="auto" height="74px" alt="next.js" src="https://github.com/microsoft/vscode/assets/47495003/e1daf4e1-3fa5-4a44-969a-6143ddd67310"></picture></img></a>&nbsp;&nbsp;
   <!-- React -->
   <a href="https://themeselection.com/item/materio-mui-react-nextjs-admin-template/" target="_blank"><picture><source width="auto" height="74px" media="(prefers-color-scheme: dark)" srcset="https://github.com/microsoft/vscode/assets/47495003/3877046e-c652-4b3d-99e9-2e134da1d6cf"><source width="auto" height="74px" media="(prefers-color-scheme: light)" srcset="https://github.com/microsoft/vscode/assets/47495003/8c8c940e-d8f9-4213-a7f7-f8bc4968f169"><img width="auto" height="74px" alt="react" src="https://github.com/microsoft/vscode/assets/47495003/8c8c940e-d8f9-4213-a7f7-f8bc4968f169"></picture></img></a>&nbsp;&nbsp;
   <!-- Vue -->
   <a href="https://themeselection.com/item/materio-vuetify-vuejs-admin-template/" target="_blank"><picture><source width="auto" height="74px" media="(prefers-color-scheme: dark)" srcset="https://github.com/microsoft/vscode/assets/47495003/881bbbb8-d1c9-421c-9bce-4ea43dfa9e6e"><source width="auto" height="74px" media="(prefers-color-scheme: light)" srcset="https://github.com/microsoft/vscode/assets/47495003/b02d6473-0345-42c2-be58-e648806104fa"><img width="auto" height="74px" alt="vue" src="https://github.com/microsoft/vscode/assets/47495003/b02d6473-0345-42c2-be58-e648806104fa"></picture></img></a>&nbsp;&nbsp;
   <!-- Vue + Laravel -->
   <a href="https://themeselection.com/item/materio-vuetify-vuejs-laravel-admin-template/" target="_blank"><picture><source width="auto" height="74px" media="(prefers-color-scheme: dark)" srcset="https://github.com/microsoft/vscode/assets/47495003/20b6428e-3fa5-4f80-a389-9e4cd732c2de"><source width="auto" height="74px" media="(prefers-color-scheme: light)" srcset="https://github.com/microsoft/vscode/assets/47495003/3008d3eb-7b5b-4d9c-8563-837744a901da"><img width="auto" height="74px" alt="vue_laravel" src="https://github.com/microsoft/vscode/assets/47495003/3008d3eb-7b5b-4d9c-8563-837744a901da"></picture></img></a>&nbsp;&nbsp;
</p>

<!-- Add other pro variants here. You can get the logo URL from here: https://icones.js.org/collection/logos -->

## Looking For Premium Admin Templates ?? 👀

**[ThemeSelection](https://themeselection.com/)** provides Selected high quality, modern design, professional and easy-to-use **Fully Coded Dashboard Templates & UI Kits** to create your applications faster!

- [Bootstrap Admin Templates](https://themeselection.com/item/category/bootstrap-admin-template/)
- [VueJS Admin Templates](https://themeselection.com/item/category/vuejs-admin-templates/)
- [Laravel Admin Templates](https://themeselection.com/item/category/laravel-admin-templates/)
- [Django Admin Templates](https://themeselection.com/item/category/django-admin-template/)
- [React (NextJS) Admin Templates](https://themeselection.com/item/category/next-js-admin-template/)
- [ASP.Net Core Admin Templates](https://themeselection.com/item/category/asp-net-dashboard/)
- [Free UI Kits](https://themeselection.com/item/category/free-ui-kits/)

If you want to [Free Admin Templates](https://themeselection.com/item/category/free-admin-templates/) like Materio then do visit [ThemeSelection](https://themeselection.com/).

## Useful Links 🎁

- [Vue CheatSheet](https://vue-cheatsheet.themeselection.com/)
- [Freebies](https://themeselection.com/item/category/freebies/)
- [Free Admin Templates](https://themeselection.com/item/category/free-admin-templates/)
- [Bootstrap 5 CheatSheet](https://bootstrap-cheatsheet.themeselection.com/)
- [FlyonUI](https://flyonui.com/)
- [JetShip](https://demos.themeselection.com/jetship-laravel-starter-kit/)

## Social Media :earth_africa:

- [x](https://x.com/Theme_Selection)
- [Facebook](https://www.facebook.com/ThemeSelections/)
- [Pinterest](https://www.pinterest.com/themeselection/)
- [Instagram](https://www.instagram.com/themeselection/)
- [Discord](https://discord.com/invite/kBHkY7DekX)
- [YouTube](https://www.youtube.com/channel/UCuryo5s0CW4aP83itLjIdZg)
