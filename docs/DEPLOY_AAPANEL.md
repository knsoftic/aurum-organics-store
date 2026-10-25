# aaPanel par Deployment Guide — knorganic.site

Yeh guide **Aurum Organics** Laravel store ko aaPanel wale server par
**https://knorganic.site** domain ke saath live karne ke liye hai.

| Cheez | Value |
|-------|-------|
| Domain | `knorganic.site` (aur `www.knorganic.site`) |
| Site folder | `/www/wwwroot/knorganic.site` |
| Running directory | `/public` |
| GitHub repo | `https://github.com/knsoftic/aurum-organics-store` |
| PHP | 8.2 ya 8.3 |
| Database | MySQL 5.7+ / 8.x ya MariaDB 10.4+ |

> **Note:** Neeche jahan bhi `php` likha hai, wahan aapke site ka PHP version hona chahiye.
> `php -v` chala kar check karein. Agar version 8.2 se kam dikhaye to pura path use karein,
> misal ke taur par `/www/server/php/82/bin/php`.

---

## 1. Domain ki DNS set karein

Apne domain provider (Namecheap, GoDaddy, Cloudflare waghera) mein yeh records banayein:

| Type | Name | Value |
|------|------|-------|
| A | `@` | `SERVER_KA_IP` |
| A | `www` | `SERVER_KA_IP` |

DNS update hone mein 5 minute se kuch ghante lag sakte hain. Check karne ke liye:

```bash
ping knorganic.site
```

> **Cloudflare use kar rahe hain?** SSL/TLS mode **Full (strict)** rakhein, *Flexible* nahi.

---

## 2. aaPanel mein zaroori software

**App Store** mein jaa kar yeh install karein (agar pehle se nahi hain):

* **Nginx** (recommended) ya Apache
* **MySQL** 5.7 / 8.0 ya **MariaDB**
* **PHP 8.2** ya **PHP 8.3**

### 2.1 PHP extensions

**App Store → PHP 8.x → Setting → Install extensions** mein yeh install karein:

* `fileinfo` — **zaroori hai** (iske baghair image aur screenshot upload kaam nahi karega)
* `opcache` — speed ke liye (recommended)

Yeh extensions aam taur par pehle se hoti hain: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `gd`, `zip`.

### 2.2 Disabled functions

**PHP 8.x → Setting → Disabled functions** mein se yeh **remove** karein (Composer ko inki zaroorat hai):

* `putenv`
* `proc_open`

### 2.3 Upload limit

**PHP 8.x → Setting → Configuration file** mein:

```ini
upload_max_filesize = 8M
post_max_size = 16M
```

Save karke PHP **restart** karein.

---

## 3. Composer aur Git install karein

aaPanel ke **Terminal** ya SSH se (root user):

```bash
# Git
yum install -y git || apt-get install -y git

# Composer
cd /tmp
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
composer --version
```

---

## 4. aaPanel mein website aur database banayein

**Website → Add site** par jaa kar:

* **Domain:** `knorganic.site` aur nayi line mein `www.knorganic.site`
* **Root directory:** `/www/wwwroot/knorganic.site`
* **PHP version:** PHP-82 (ya 83)
* **Database:** MySQL select karein, charset **utf8mb4** rakhein
  * Database name: `knorganic`
  * Username: `knorganic`
  * Password: aaPanel jo strong password de, usay **copy kar ke safe jagah** rakh lein

**Submit** karein.

> Agar database baad mein banana ho: **Databases → Add database**, charset `utf8mb4`, access `Local server`.

---

## 5. Code server par lana (git clone)

aaPanel naye site folder mein kuch default files rakhta hai. Pehle unhein hatayein, phir code clone karein:

```bash
cd /www/wwwroot/knorganic.site

# aaPanel ki default files hatayein (.user.ini locked hoti hai, pehle unlock karein)
chattr -i .user.ini 2>/dev/null || true
rm -f .user.ini .htaccess index.html 404.html

# Code clone karein (aakhir mein dot "." zaroor lagayein)
git clone https://github.com/knsoftic/aurum-organics-store.git .
```

---

## 6. `.env` file banayein

```bash
cd /www/wwwroot/knorganic.site
cp .env.example .env
nano .env
```

`.env` mein yeh values set karein (baqi lines waise hi rehne dein):

```dotenv
APP_NAME="KN Organic"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://knorganic.site
APP_TIMEZONE=Asia/Karachi

# Pehla admin account (seed se banega)
ADMIN_EMAIL=admin@knorganic.site
ADMIN_PASSWORD=YahanKoiStrongPassword123!

LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=knorganic
DB_USERNAME=knorganic
DB_PASSWORD=aaPanel_wala_database_password

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Save karne ke liye `Ctrl + O` → `Enter` → `Ctrl + X`.

> ⚠️ `APP_DEBUG=true` live site par **kabhi na rakhein** — is se passwords aur code customers ko dikh sakte hain.

Email ki settings ke liye section 11 dekhein.

---

## 7. Install commands

```bash
cd /www/wwwroot/knorganic.site

# PHP packages
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader

# Encryption key (sirf pehli dafa)
php artisan key:generate

# Tables + demo data (products, payment methods, settings, pages) — SIRF PEHLI DAFA
php artisan migrate --seed --force

# Speed ke liye cache
php artisan optimize
```

> ⚠️ **Zaroori:** `php artisan db:seed` ya `migrate --seed` sirf **pehli dafa** chalayein.
> Dobara chalane se admin mein ki hui settings, products ka demo text aur reviews wapas reset ho jayenge.
> `php artisan migrate:fresh` live site par **kabhi na chalayein** — is se saare orders delete ho jate hain.

---

## 8. Permissions

```bash
cd /www/wwwroot/knorganic.site
chown -R www:www /www/wwwroot/knorganic.site
chmod -R 775 storage bootstrap/cache public/uploads
chmod +x deploy.sh
```

---

## 9. Running directory, Anti-XSS aur URL rewrite

**Website → knorganic.site → Conf** kholein:

### 9.1 Site directory

* **Running directory:** `/public` select karein → **Save**
* **Anti-XSS attack (open_basedir):** isay **OFF** kar dein
  (warna Laravel ko "open_basedir restriction" error aata hai kyunki app `public` se bahar ki files parhti hai)

### 9.2 URL rewrite

* **URL rewrite** tab → dropdown se **`laravel5`** select karein → **Save**

Agar `laravel5` option na ho to yeh khud paste karein:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

> **Apache use kar rahe hain?** Rewrite ki zaroorat nahi — `public/.htaccess` pehle se mojood hai.

---

## 10. SSL (HTTPS)

**Website → knorganic.site → Conf → SSL**:

1. **Let's Encrypt** select karein
2. `knorganic.site` aur `www.knorganic.site` dono tick karein → **Apply**
3. Certificate ban jane ke baad **Force HTTPS** ON karein

Ab browser mein kholein: **https://knorganic.site** 🎉

---

## 11. Email (order notifications)

Order hone par customer aur admin ko email jati hai. Apne email provider ki SMTP details `.env` mein daalein:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=orders@knorganic.site
MAIL_PASSWORD=email_ka_password
MAIL_FROM_ADDRESS=orders@knorganic.site
MAIL_FROM_NAME="${APP_NAME}"
```

* Port **587** use kar rahe hain to `MAIL_SCHEME=smtp` rakhein.
* Gmail ke liye normal password nahi, **App Password** banana parta hai.

`.env` mein koi bhi tabdeeli ke baad yeh zaroor chalayein:

```bash
php artisan optimize
```

---

## 12. Live hone ke baad pehla kaam (checklist)

1. **https://knorganic.site/admin** par login karein (`.env` wala `ADMIN_EMAIL` / `ADMIN_PASSWORD`)
2. **My Profile** → password check / tabdeel karein
3. **Settings** → store name, logo, WhatsApp number, phone, email, address, delivery charges
4. **Settings → General → "New-order alerts to"** mein apni email daalein
5. **Payment Methods** → EasyPaisa, JazzCash, Bank, USDT ke **demo numbers hata kar apne asli account details** daalein (ya jo use nahi karne unhein band kar dein)
6. **Debit / Credit Card (Sandbox)** method **band hi rakhein** — yeh sirf testing ke liye hai
7. **Products** → demo products edit karein ya apne products daalein, **asli photos** upload karein
8. **Pages** → About, Privacy, Refund, Shipping policies apne hisaab se edit karein
9. Ek **test order** (COD) daal kar dekhein, phir admin se usay **Cancelled** kar dein

---

## 13. Code update karna (aage ke liye)

Jab bhi GitHub par naya code push ho, server par sirf yeh ek command chalayein:

```bash
bash /www/wwwroot/knorganic.site/deploy.sh
```

Yeh script khud se: maintenance mode on → `git pull` → composer install → migrations → cache rebuild → permissions → site wapas online karti hai.
Database aur orders safe rehte hain.

Agar `php` command purana version chala raha ho:

```bash
PHP_BIN=/www/server/php/82/bin/php bash /www/wwwroot/knorganic.site/deploy.sh
```

---

## 14. Backup

**Cron** menu mein do backup tasks banayein:

* **Backup Database** → `knorganic` → rozana (Daily), kam az kam 7 copies rakhein
* **Backup Site** → `knorganic.site` → haftawar (Weekly)

Sab se ahem cheezein:

* Database (orders, customers, settings)
* `public/uploads` (product images)
* `storage/app/private/payment-proofs` (customers ke payment screenshots)
* `.env` file

---

## 15. Masle aur unka hal (Troubleshooting)

| Masla | Hal |
|-------|-----|
| **500 error / blank page** | `tail -n 50 /www/wwwroot/knorganic.site/storage/logs/laravel.log` se asal error dekhein. Aksar permissions ka masla hota hai — section 8 dobara chalayein. |
| **"open_basedir restriction in effect"** | Section 9.1: Anti-XSS **OFF** karein. |
| **Home page chalta hai lekin `/shop`, `/admin` par 404** | URL rewrite set nahi — section 9.2. |
| **Laravel ki files ki list ya "403 Forbidden"** | Running directory `/public` set nahi — section 9.1. |
| **"No application encryption key"** | `php artisan key:generate` phir `php artisan optimize`. |
| **Image / screenshot upload fail, "Unable to guess the MIME type"** | `fileinfo` extension install karein — section 2.1. |
| **Composer: "putenv() has been disabled"** | Disabled functions se `putenv`, `proc_open` hatayein — section 2.2. |
| **"413 Request Entity Too Large"** | **Website → Conf → Config file** mein `client_max_body_size 20m;` aur PHP upload limit (section 2.3). |
| **Page kharab dikhta hai, CSS nahi load hoti (mixed content)** | `.env` mein `APP_URL=https://knorganic.site` ho, phir `php artisan optimize`. |
| **"419 Page Expired" form submit par** | Browser cookies clear karein; `.env` mein `SESSION_SECURE_COOKIE=true` sirf tab rakhein jab SSL laga ho; phir `php artisan optimize`. |
| **`.env` badla lekin asar nahi hua** | `php artisan optimize` chalayein (settings cache hoti hain). |
| **Emails nahi ja rahin** | `MAIL_*` settings check karein; error `storage/logs/laravel.log` mein hoga. Order phir bhi place ho jata hai. |
| **Admin password bhool gaye** | `.env` mein `ADMIN_PASSWORD` naya likhein, phir: `php artisan tinker --execute="App\Models\User::where('email', config('app.admin_email'))->first()->update(['password' => config('app.admin_password')]);"` |

---

## 16. Security tips

* `APP_DEBUG=false` hamesha (live par)
* Admin ka password mazboot rakhein aur kisi se share na karein
* aaPanel ka apna login bhi strong rakhein aur default port badal dein
* **Security** menu mein sirf zaroori ports khule rakhein (22, 80, 443, aaPanel port)
* Har mahine `bash deploy.sh` se updates lagate rahein aur backups check karte rahein
