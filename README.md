# Form Builder — Google Sheets

A Google Sheets integration for [RefinedCMS Form Builder](https://gitlab.com/refineddigital/cms-form-builder). On submit, a form's field values are appended as a new row to a Google Sheet, then the normal submission email is still sent.

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
2. Leave **Send email** on if you still want the normal submission notification to go out (turn it off to write to the sheet *only*).
3. For each field you want written to the sheet, set its **Merge Field** value (on the field's settings). Fields with no merge field are ignored.

That's it — on submit, each merge-field'd value is appended as a row to the sheet. All Google credentials come from `.env`; there's nothing to configure per form.

## Column ordering

The row is built from the form's merge-field'd fields **in field position order** — i.e. the order the fields appear in the form. There is no separate column-mapping UI: column 1 is the first merge-field'd field, column 2 the second, and so on.

To change which sheet column a value lands in, reorder the fields in the form so their order matches your sheet columns.

If `timestamp` is enabled in the config (default), a UTC `Y-m-d H:i:s` timestamp is prepended as the first column — so your first form field maps to column **B**, not A.

## Config

`config/google-sheets.php`:

```php
return [
    'spreadsheet_id' => env('GOOGLE_SHEETS_ID'),
    'api_key'        => env('GOOGLE_API_KEY'),
    'auth_config'    => env('GOOGLE_AUTH_CONFIG'),
    'timestamp'      => true, // prepend a UTC timestamp column to each row
];
```

## How it works

The service provider registers the integration with the core `FormBuilderIntegrationAggregate`, which is what makes it show up in the panel. `Process` implements `FormBuilderIntegrationInterface`; when an enabled form is submitted, the core form-builder calls `Process::process($request, $form, $settings)`, which:

1. Maps submitted values to columns via the core `FormsRepository::formatWithMergeFields()` helper (keyed by each field's `merge_field`).
2. Builds a single row (optional leading timestamp), UTF-8 encoded.
3. Appends it to the sheet via `Classes\Google::put()`, which finds the next empty row and writes a `RAW` value range.

The integration does **not** send email — notifications are the form's own responsibility (controlled by the **Send email** toggle in the panel).

Sheet write errors are caught and reported (via `report()`), never surfaced to the visitor — a failed sync will not block a form submission or the email. Returning a failure result (or throwing) would abort the whole submission; we deliberately don't, so Google being down never costs you a lead.
