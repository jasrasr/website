# Kingdom Connections 1.2.4

[Open Kingdom Connections on jasr.me](https://jasr.me/github/kingdom-connections/)
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
Open the sibling folder's login.php. The command prints generated credentials for demo-super, ten business owners and ten customers. It creates ten fictional businesses, ten customers, ten reviews from different customers backed by simulated verified payments, plus one pending payment and one verified payment still awaiting a review; all pages carry a demo banner. The installer refuses to replace an existing folder. Use a private staging host for demos. Keep demo output and runtime files out of Git.

Sign in as demo-customer to review the settled plumbing visit. The pending one has no review button. Use demo-super to verify the pending payment, edit customer data or manage listings. Use demo-trades to upload a photo and edit skills. Demo logins, listings and payments are independent of the production application.

Run php tests/directory.test.php and php tests/workflow.test.php. Also verify HTTPS, directory access denial, GD uploads and responsive layout on the deployment host.

## Load demo data from the website
Sign in as a super admin, open the account dashboard, check the demo-data confirmation and click **Load demo data**. It adds ten fictional businesses, ten customer accounts/profiles and ten customer reviews. Each business has its own owner account and each review has a different customer and a simulated verified payment. All business names and review comments identify their demo status. Two extra service records demonstrate a pending payment and a verified payment awaiting a review.

Business owners use demo-trades, demo-wood, demo-hvac, demo-landscape, demo-paint, demo-cleaning, demo-roof, demo-auto, demo-tech and demo-catering. Customers use demo-customer and demo-customer-02 through demo-customer-10. The dashboard displays generated credentials to the super admin after loading.

The loader stores its plan, including generated demo passwords, in private ignored storage/demo-seed.json. Keep storage inaccessible over HTTP. Repeat loads reuse their accounts and listings, leave existing fields intact and avoid duplicate payments/reviews. Interrupted runs can resume using the saved plan. An unrelated account using one of these usernames blocks loading instead of being adopted. A demo account whose password has been changed also blocks a repeat load. Live accounts and other listings are retained. Super admins may manage seeded records using the same account, listing and review controls.

## First admin from a phone
On an empty install, open login.php, enter the privately supplied temporary password, and choose and confirm a new password of 12–72 bytes. Sign in as super-admin with the new password. Setup is unavailable once an account exists. The temporary password is never stored in the account or granted a session.

## Included demonstration artwork

The repository includes 10 generated demonstration business logos and 10 AI-generated fictional representative portraits in `assets/images/demo/logos/` and `assets/images/demo/avatars/` (400px WebP). The directory automatically displays the matching assets for the ten known seeded businesses when no custom image URL or uploaded image is present. No live `storage` files, business records, ratings, verified payments, or account credentials are rewritten. Custom logo/picture URLs and uploads take priority for all other listings. For the exact fictional pairing **Demo Faithful Home Services / Sam Mercer**, the bundled logo and avatar are deliberately shown even when the stored demo listing has old URLs or upload-removal markers. This is a display-only exception and does not alter the saved record; rename the demo business before converting it to a real listing.

These portraits are synthetic placeholders, not photographs of real owners. Demo names, testimonials, and reviews are fictional. Replace the images before turning a demonstration entry into a real business listing.
