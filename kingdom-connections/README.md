# Kingdom Connections 1.1.0
A standalone PHP 8.1+ Christian business directory. Copy this folder to its own repository or host. Login, sessions, accounts, data and images are app-local. The bundled JSON helper follows the original framework's locking and atomic replacement conventions.

## Install
Serve over HTTPS on PHP with GD for JPEG, PNG or WebP uploads. Default storage is the writable storage directory. Apache/LiteSpeed must honor its deny rule. Verify HTTP 403 for a sample storage file before opening registration. On other servers deny all HTTP access to storage or set storage_path in config.local.php to an existing private directory outside the web root. Preserve the folder during updates. Back up the entire directory of runtime files together.

Initialize the first super admin before opening registration. Provide the password via stdin from a private file (12–72 bytes), then remove the file.
```sh
php setup.php super YOUR_USERNAME < /private/initial-password.txt
```
Existing business listings and legacy feedback stay readable. Existing shared site accounts do not automatically become local accounts. Legacy listings without a local owner are managed by the local super admin. No production credentials are included.

## Roles
- Business owners register and manage their listings, descriptions, services, testimony, contact details, logo and employee photo. Uploads are resized and re-encoded before storage. Hosted image URLs also work.
- Customers register, save a profile, and submit a completed service with its receipt reference. A super admin independently checks the merchant’s settled payment record and confirms that payer, business and service match. Only that signed-in customer may submit one 1–5 star review per verified payment. No card details are collected. Payment verification is manual.
- Super admins manage all listings, local customer/business accounts, customer contact profiles, payment records, and feedback. Editing payment details resets verification and removes its linked review. Removing an account deletes its associated app data. Legacy and admin-entered feedback carry distinct labels.

## Standalone demonstration
From the app directory, generate a fresh sibling application:
```sh
php setup.php demo ../kingdom-connections-demo
```
Open the sibling folder's login.php. The command prints unique credentials once for demo-super, demo-trades, demo-wood and demo-customer. It creates two fictional businesses, one pending payment and one simulated verified payment; all pages carry a demo banner. The installer refuses to replace an existing folder. Use a private staging host for demos. Keep demo output and runtime files out of Git.

Sign in as demo-customer to review the settled plumbing visit. The pending one has no review button. Use demo-super to verify the pending payment, edit customer data or manage listings. Use demo-trades to upload a photo and edit skills. Demo logins, listings and payments are independent of the production application.

Run php tests/directory.test.php and php tests/workflow.test.php. Also verify HTTPS, directory access denial, GD uploads and responsive layout on the deployment host.
