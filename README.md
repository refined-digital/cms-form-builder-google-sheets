# Form Builder — Google Sheets

A Google Sheets integration for [RefinedCMS Form Builder](https://gitlab.com/refineddigital/cms-form-builder). On submit, a form's selected field values are appended as a new row to a Google Sheet. You pick which fields go to the sheet and in what column order from the form's Integrations panel; the normal submission email still sends unless you turn it off there.

Requires `refineddigital/cms-form-builder` and `google/apiclient`.

## Install

```bash
composer require refineddigital/cms-form-builder-google-sheets
php artisan refinedCMS:install-form-builder-google-sheets
```

The install command publishes the config (`config/google-sheets.php`) and appends these keys to your `.env` (only if missing — it won't overwrite existing values):

```
GOOGLE_SHEETS_ID=
GOOGLE_API_KEY=
GOOGLE_AUTH_CONFIG=
```

| Variable | What it is |
|----------|------------|
| `GOOGLE_SHEETS_ID` | The spreadsheet ID — the long string in the sheet URL between `/d/` and `/edit`. |
| `GOOGLE_API_KEY` | A Google API key (developer key). |
| `GOOGLE_AUTH_CONFIG` | Filename of the service-account JSON, relative to `storage/app/`. |

## Google setup

1. In the [Google Cloud Console](https://console.cloud.google.com/), enable the **Google Sheets API**.
2. Create a **service account**, download its JSON key, and place it in `storage/app/` (e.g. `storage/app/sheets-service-account.json`). Set `GOOGLE_AUTH_CONFIG` to just the filename.
3. **Share the target spreadsheet** with the service account's email address (`...@...iam.gserviceaccount.com`) as an **Editor**. Without this the write will fail.
4. Add a header row to the sheet — the integration appends *below* existing content, so an empty sheet with no header row has nothing to anchor to and the write is skipped.

## Connecting a form

Once the package is installed it appears automatically in every form's **Integrations** panel — there's nothing to wire up per-class.

In the CMS admin, edit a form and open the **Integrations** tab:

1. Toggle **Google Sheets** on.
2. Click **Configure** to open the settings modal. It has two tabs:
   - **Fields** — every form field, plus a built-in **Date / Time** column. Toggle each on/off and **drag to set the column order** in the sheet. The Date column is a synthetic column (not a form field) and can be dragged anywhere in the order, the same as a real field.
   - **Config** — the **Send email** toggle (leave on to still send the normal notification; turn off to write to the sheet *only*).

That's it — on submit, the enabled columns are appended as a row to the sheet **in the order you set**. All Google credentials come from `.env`; only the column selection/order is per form.

## Column ordering

Columns are written in the exact order shown on the **Fields** tab. Drag a row up or down to move that value left or right in the sheet; toggle a row off to omit it entirely. The **Date / Time** column is just another draggable row — put it first (the default) or anywhere else.

If a form has **never been configured** (the Configure modal was never saved), the integration falls back to legacy behaviour: every field with a **Merge Field** value, in field position order, with a leading UTC timestamp (controlled by the `timestamp` config flag).

## Config

`config/google-sheets.php`:

```php
return [
    'spreadsheet_id' => env('GOOGLE_SHEETS_ID'),
    'api_key'        => env('GOOGLE_API_KEY'),
    'auth_config'    => env('GOOGLE_AUTH_CONFIG'),
    'timestamp'      => true, // fallback only: prepend a UTC timestamp when a form has no saved column config
];
```

## How it works

The service provider registers the integration with the core `FormBuilderIntegrationAggregate`, which is what makes it show up in the panel. `Process` implements `FormBuilderIntegrationInterface`; when an enabled form is submitted, the core form-builder calls `Process::process($request, $form, $settings)`, which:

1. Reads `$settings['config']['fields']` — the ordered list of columns saved from the Configure modal (`[{key, enabled}]`). Each `key` is either a form field name (`field{id}`) or the synthetic `__date`.
2. Builds a single row from the enabled entries, in order: `__date` becomes a UTC `Y-m-d H:i:s` timestamp, every other key reads `$request->get($key)`. Array values (e.g. multi-checkbox) are joined with `, `. Everything is UTF-8 encoded.
3. If no columns are configured, falls back to `FormsRepository::formatWithMergeFields()` (merge-field'd fields in position order, optional leading timestamp).
4. Appends the row to the sheet via `Classes\Google::put()`, which finds the next empty row and writes a `RAW` value range.

The integration does **not** send email — notifications are the form's own responsibility (controlled by the **Send email** toggle in the panel).

Sheet write errors are caught and reported (via `report()`), never surfaced to the visitor — a failed sync will not block a form submission or the email. Returning a failure result (or throwing) would abort the whole submission; we deliberately don't, so Google being down never costs you a lead.
