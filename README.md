# Google Sheet for JetFormBuilder

Send JetFormBuilder form submissions directly to Google Sheets using the Google Sheets API v4 and Service Account authentication.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- [JetFormBuilder](https://jetformbuilder.com/) plugin
- PHP OpenSSL extension
- Google Cloud project with Sheets API enabled

## Setup

1. Go to the [Google Cloud Console](https://console.cloud.google.com/) and create or select a project
2. Enable the [Google Sheets API](https://console.cloud.google.com/apis/library/sheets.googleapis.com)
3. Navigate to **IAM & Admin > Service Accounts > Create Service Account**
4. Open the new Service Account > **Keys** tab > Add Key > Create new key > **JSON**
5. In WordPress, go to **JetFormBuilder > Settings > Google Sheet** tab
6. Paste the downloaded JSON key contents and save
7. **Important:** Share each target Google Sheet with the Service Account email address (Editor permission)

## Features

- **Post-submit action** — Add "Google Sheet" as a post-submit action in any JetFormBuilder form
- **Field mapping** — Map form fields to spreadsheet column headers with auto-detection
- **Sheet tab selector** — Fetch and select sheet tabs directly from the editor
- **Duplicate check** — Optionally skip submission if a field value already exists in the sheet (e.g. prevent duplicate emails)
- **Custom success message** — Override the default form success message per action
- **WYSIWYG support** — HTML from rich text fields is automatically stripped to plain text
- **Debug logging** — Toggle detailed API logging for troubleshooting
- **Service Account auth** — Secure JWT-based authentication, no OAuth consent screen needed

## How it works

1. Add the **Google Sheet** action to your form's post-submit actions
2. Paste the Spreadsheet ID or full URL
3. Fetch and select the target sheet tab
4. Click **Fetch column headers** to auto-populate the field mapping from the sheet's first row
5. Map form fields to the corresponding columns
6. Optionally configure duplicate checking and custom messages

The first row of your Google Sheet must contain column headers. New submissions are appended as rows below.

## License

GPLv2 or later
