# Aurum Organics — Laravel e-commerce store

A luxury single-category store (one product or many products in one category)
with a complete admin panel, Cash on Delivery, manual payments (EasyPaisa,
JazzCash, bank transfer, USDT TRC20) and a plug-in system for online gateways.

Built with Laravel 12, Blade, plain CSS/JS (no build step), MySQL.

## Requirements

PHP 8.2+ (extensions: pdo_mysql, mbstring, openssl, fileinfo, gd), Composer 2,
MySQL / MariaDB. No Node.js build is needed.

## Install (XAMPP)

```bash
git clone https://github.com/knsoftic/aurum-organics-store.git C:/xampp/htdocs/organic
cd C:/xampp/htdocs/organic
composer install
cp .env.example .env
php artisan key:generate
```

1. Start **Apache** and **MySQL** in XAMPP and create a database named
   `organic_store` (utf8mb4) — or change `DB_*` in `.env`.
2. Set `ADMIN_EMAIL` / `ADMIN_PASSWORD` in `.env` for the first admin account.
3. Create the tables and demo data (also resets everything later):

```bash
php artisan migrate:fresh --seed
```

4. Open the store: <http://localhost/organic/public> (or <http://localhost/organic>)
5. Admin panel: <http://localhost/organic/public/admin> — sign in with the
   `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env` (defaults: `admin@aurum.test` /
   `Admin@12345`). Change the password from **My Profile** right away.

> Locally, don't run `php artisan optimize` / `route:cache` while the app is
> served from a sub-folder (`localhost/organic/public`) — Laravel's cached
> router then returns 405 on the home page. It works normally at a domain root
> (production). Undo with `php artisan optimize:clear`.

## Features

**Storefront** — luxury responsive design (4 colour themes), hero, collection
or single-product spotlight mode, product gallery, reviews with ratings, slide-out
cart, coupon codes, free-delivery progress bar, checkout with live totals per
payment method, payment screenshot upload, order confirmation / tracking page,
track order by number + phone, contact form, policy pages, WhatsApp buttons,
SEO meta tags.

**Admin** — dashboard (sales chart, pending orders, payments to verify, low
stock), orders (filters, status workflow with stock restore, payment
approval with screenshot preview, record payments, courier + tracking, printable
invoice, CSV export, WhatsApp the customer), products (gallery, compare-at
price, stock), categories, payment methods, coupons, reviews moderation,
pages, contact messages, store settings (branding, hero, story, FAQ, delivery
fees, theme, SEO, social), admin profile.

**Payments** — see [docs/PAYMENT_GATEWAYS.md](docs/PAYMENT_GATEWAYS.md).

## Deploy on aaPanel

Step-by-step guide (Roman Urdu) for `knorganic.site`:
[docs/DEPLOY_AAPANEL.md](docs/DEPLOY_AAPANEL.md). Later updates on the server
are one command: `bash deploy.sh`.

## Going live checklist

* `.env`: `APP_ENV=production`, `APP_DEBUG=false`, real `APP_URL`, database
  credentials, and SMTP `MAIL_*` settings (emails are written to
  `storage/logs/laravel.log` while `MAIL_MAILER=log`).
* Point the domain's document root at the `public/` folder.
* Replace the demo account details in **Payment Methods** with your real
  EasyPaisa / JazzCash / bank / wallet details.
* Keep the sandbox **Debit / Credit Card** method disabled (it is a test gateway).
* Uploaded images live in `public/uploads`; payment screenshots in
  `storage/app/private/payment-proofs` (not publicly accessible).
* `php artisan config:cache route:cache view:cache` for speed.
