<div align="center">

# 🚧 ZynEarn - Turn Your Time Into Real Money

<img src="https://img.shields.io/badge/STATUS-UNDER_DEVELOPMENT-orange?style=for-the-badge" />
<img src="https://img.shields.io/badge/ERRORS-MULTIPLE-red?style=for-the-badge" />
<img src="https://img.shields.io/badge/OPEN_SOURCE-YES-success?style=for-the-badge" />
<img src="https://img.shields.io/badge/PHP-PROJECT-blue?style=for-the-badge" />

<br>
<br>

⚠️ Experimental Open Source PHP Project

This project is currently under heavy development and may contain bugs, broken pages, unfinished systems, security issues, and unstable functions.

</div>

---

# ⚠️ Warning

> This project is NOT production ready.
>
> Many systems are still incomplete and experimental.
>
> If you find any bugs or errors, feel free to report them or submit a pull request.

---

# 🛠 Current Status

| Feature | Status |
|---|---|
| Frontend UI | ✅ Working |
| Backend System | ⚠️ Partial |
| Authentication | ⚠️ Unstable |
| Database | ⚠️ Experimental |
| API Integration | ❌ Incomplete |
| Security | ❌ Needs Improvement |
| Mobile Responsive | ⚠️ Partial |

---

# 📌 Known Issues

- ❌ Random PHP errors
- ❌ Some pages may crash
- ❌ Database bugs
- ❌ Session/login issues
- ❌ UI responsiveness issues
- ❌ Missing validations
- ❌ Performance problems
- ❌ Unoptimized code structure

---

# 🤝 Open Source Contributions

If you are a developer and interested in improving this project, contributions are welcome.

You can help by:

- 🐛 Fixing bugs
- ⚡ Optimizing code
- 🎨 Improving UI/UX
- 🔐 Improving security
- 🚀 Adding new features
- 📱 Improving responsiveness

---

# 🧪 Development Notice

This project is still experimental.

Development may continue slowly depending on:
- Free time
- Testing
- Feature planning
- Bug fixing

---

# ⭐ Support

If you like this project:

- ⭐ Star the repository
- 🍴 Fork the project
- 🛠 Contribute improvements
- 🐞 Report issues

---

<div align="center">

## 🚀 Made with PHP & Open Source Community

<img src="https://img.shields.io/github/stars/imvinx/REPOSITORY?style=social" />
<img src="https://img.shields.io/github/forks/imvinx/REPOSITORY?style=social" />
<img src="https://img.shields.io/github/issues/imvinx/REPOSITORY" />

</div>

# INFO OF THIS PROJECT

ZynEarn is a premium online earning platform where users complete tasks, surveys, offers, shortlinks, and other activities to earn real money. Built with PHP, MySQL, and a modern glassmorphic UI, it connects users with advertisers who pay for attention and actions.

---

## ✨ Features

### Earning Methods
- **Offerwall** — Complete offers from top advertisers (up to $50/offer)
- **Shortlinks** — Earn by shortening and sharing links ($0.50–$2/click)
- **Faucet** — Claim free rewards every few minutes (up to $0.10/claim)
- **Spin Wheel** — Daily lucky spin with prizes up to $100
- **Scratch Cards** — Instant-win scratch cards (up to $25/card)
- **Quizzes** — Answer questions and earn rewards
- **Surveys** — Paid market research ($1–$15/survey)
- **Tasks** — Simple signups, downloads, visits ($0.50–$10/task)
- **Videos** — Watch video ads ($0.01–$0.10/video)
- **Referrals** — Lifetime 20% commission on referrals

### Platform Features
- User dashboard with real-time balance tracking
- Membership tiers: Free, Silver, Gold, Platinum, VIP (up to 6x earnings multiplier)
- XP & leveling system (15 levels)
- Achievement system with rewards
- PWA support (installable on mobile/desktop)
- Live activity feed & notifications
- AI-powered earning recommendations
- AI chat assistant
- Two-factor authentication (2FA)
- Email verification
- Dark/Light theme
- Responsive glassmorphic UI design
- Multi-language ready
- Newsletter system
- Blog system
- Contact form
- FAQ system (database-driven)
- Admin panel with full management
- Rate limiting & brute-force protection
- CSRF protection
- Session management
- Activity logging

### Payment Gateways
- PayPal (REST API)
- Stripe
- Razorpay
- Binance Pay
- FaucetPay

### Social Authentication
- Google OAuth 2.0
- Discord OAuth 2.0
- Telegram Login Widget

---

## 📸 Screenshots

> Screenshots coming soon. Place your screenshots in `assets/images/screenshots/`.

| Page | Preview |
|------|---------|
| Landing Page | `screenshot-1.png` |
| Dashboard | `screenshot-2.png` |
| User Area | — |

---

## 🛠 Tech Stack

| Layer | Technology |
|-------|-----------|
| **Frontend** | HTML5, CSS3, Vanilla JavaScript, Font Awesome 6 |
| **Backend** | PHP 8.0+ (PDO, Argon2id password hashing) |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ |
| **Server** | Apache 2.4+ / Nginx 1.18+ |
| **Security** | CSRF tokens, Argon2id, pepper, rate limiting, 2FA |
| **PWA** | Service Worker, Web Manifest, offline caching |
| **Payments** | PayPal, Stripe, Razorpay, Binance, FaucetPay |

---

## 📋 Requirements

- **PHP** 8.0 or higher (recommended: 8.1+)
  - Extensions: `pdo_mysql`, `mbstring`, `openssl`, `json`, `gd`, `fileinfo`, `curl`, `xml`
- **MySQL** 5.7+ or **MariaDB** 10.3+
- **Web Server**: Apache 2.4+ (with `mod_rewrite`) or Nginx 1.18+
- **SSL/TLS** certificate for production (HTTPS required for payments & PWA)
- **Composer** (optional, for dependency management)

---

## 🔧 Installation Guide

### 1. Clone / Download Files

```bash
git clone https://github.com/imvinx/zynearn.git
cd zynearn
```

Or download the ZIP and extract it to your web root (e.g., `htdocs`, `www`, or `/var/www/html`).

### 2. Create MySQL Database

```sql
CREATE DATABASE zynearn_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'zynearn_user'@'localhost' IDENTIFIED BY 'your-strong-password';
GRANT ALL PRIVILEGES ON zynearn_db.* TO 'zynearn_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Import SQL Schema

```bash
mysql -u zynearn_user -p zynearn_db < sql/schema.sql
```

If a `sql/seeder.sql` file exists, import it for sample data:

```bash
mysql -u zynearn_user -p zynearn_db < sql/seeder.sql
```

### 4. Configure Database Connection

Copy the example environment file and edit it:

```bash
cp .env.example .env
```

Edit `.env` or directly edit `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'zynearn_db');
define('DB_USER', 'zynearn_user');
define('DB_PASS', 'your-strong-password');
```

Generate a random pepper string and update `PEPPER` in `.env`:

```
PEPPER=your-64-char-random-hex-string
```

### 5. Set Up Virtual Host or Deploy to Web Server

#### Apache

```apache
<VirtualHost *:80>
    ServerName zynearn.local
    DocumentRoot "C:/xampp/htdocs/zyn-earn"
    <Directory "C:/xampp/htdocs/zyn-earn">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Enable `mod_rewrite` and restart Apache.

#### Nginx

```nginx
server {
    listen 80;
    server_name zynearn.local;
    root /var/www/zyn-earn;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### 6. Configure Cron Jobs

Add these entries to your crontab:

```cron
* * * * * php /path/to/zyn-earn/cron/faucet.php
* * * * * php /path/to/zyn-earn/cron/daily_bonus.php
*/5 * * * * php /path/to/zyn-earn/cron/cleanup.php
0 * * * * php /path/to/zyn-earn/cron/process_withdrawals.php
0 0 * * * php /path/to/zyn-earn/cron/reset_daily.php
```

### 7. Set Up PWA

The PWA manifest is at `pwa/manifest.json` and service worker at `pwa/sw.js`.
Update the `start_url` and icon paths if deploying to a subdirectory.

### 8. Configure Payment Gateways

Edit `.env` with your API keys:

```
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_CLIENT_SECRET=your_paypal_secret
PAYPAL_MODE=sandbox

STRIPE_PUBLIC_KEY=your_stripe_publishable_key
STRIPE_SECRET_KEY=your_stripe_secret_key
STRIPE_WEBHOOK_SECRET=your_stripe_webhook_secret

RAZORPAY_KEY_ID=your_razorpay_key_id
RAZORPAY_KEY_SECRET=your_razorpay_key_secret

BINANCE_API_KEY=your_binance_api_key
BINANCE_SECRET_KEY=your_binance_secret_key

FAUCETPAY_API_KEY=your_faucetpay_api_key
FAUCETPAY_SECRET=your_faucetpay_secret
```

### 9. Configure Email

Set SMTP settings in `.env`:

```
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your_email@gmail.com
SMTP_PASS=your_app_password
SMTP_FROM=noreply@zynearn.com
```

### 10. Secure the Installation

- Set proper file permissions:
  ```bash
  chmod 755 /path/to/zyn-earn
  chmod 644 /path/to/zyn-earn/config/*.php
  chmod 644 /path/to/zyn-earn/.env
  chmod -R 777 /path/to/zyn-earn/system/cache
  chmod -R 777 /path/to/zyn-earn/uploads
  chmod -R 777 /path/to/zyn-earn/system/logs
  ```
- Enable HTTPS (Let's Encrypt free SSL)
- Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`
- Change the default admin password immediately
- Configure firewall rules
- Enable logging and monitor `system/logs/`
- Set strong `PEPPER` value
- Configure reCAPTCHA keys for login/register forms

---

## 🔐 Default Admin Account

After installation, log in with:

| Field | Value |
|-------|-------|
| **URL** | `http://yourdomain.com/auth/login.php` |
| **Username** | `admin` |
| **Password** | `admin123` |

> **⚠️ IMPORTANT:** Change the default password immediately after first login.

---

## 📁 Folder Structure

```
zyn-earn/
├── .htaccess                 # Apache rewrite rules & security headers
├── .env.example              # Environment configuration template
├── index.php                 # Landing page entry point
├── robots.txt                # Search engine crawling rules
├── admin/                    # Admin panel
│   ├── dashboard.php
│   ├── users.php
│   ├── earnings.php
│   ├── withdrawals.php
│   ├── settings.php
│   └── ...
├── api/                      # API endpoints (JSON)
│   ├── earnings.php
│   ├── notifications.php
│   ├── user.php
│   └── ...
├── assets/                   # Static assets
│   ├── css/
│   │   ├── style.css         # Main stylesheet
│   │   ├── landing.css       # Landing page specific
│   │   └── admin.css         # Admin panel styles
│   ├── js/
│   │   ├── app.js            # Main application JS
│   │   └── admin.js          # Admin JS
│   ├── images/               # Icons, logos, screenshots
│   └── fonts/                # Custom fonts
├── auth/                     # Authentication pages
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── verify.php
│   ├── forgot.php
│   ├── reset.php
│   ├── two-factor.php
│   ├── google.php
│   ├── discord.php
│   └── telegram.php
├── components/               # Reusable UI components
├── config/                   # Configuration files
│   ├── app.php
│   ├── database.php
│   ├── earnings.php
│   ├── payments.php
│   └── security.php
├── cron/                     # Cron job scripts
│   ├── faucet.php
│   ├── daily_bonus.php
│   ├── cleanup.php
│   ├── process_withdrawals.php
│   └── reset_daily.php
├── includes/                 # Shared PHP includes
│   ├── functions.php
│   ├── header.php
│   ├── footer.php
│   ├── sidebar.php
│   ├── auth_middleware.php
│   └── security.php
├── modules/                  # Feature modules
│   ├── ai/
│   │   ├── chat.php
│   │   └── recommend.php
│   ├── faucet/
│   ├── offerwall/
│   ├── payment/
│   ├── quiz/
│   ├── referral/
│   ├── scratch/
│   ├── shortlink/
│   ├── spin/
│   ├── surveys/
│   ├── tasks/
│   └── videos/
├── pages/                    # Static & dynamic pages
│   ├── privacy.php
│   ├── terms.php
│   ├── contact.php
│   └── faq.php
├── pwa/                      # Progressive Web App files
│   ├── manifest.json
│   ├── sw.js
│   └── offline.html
├── sql/                      # Database schemas
│   ├── schema.sql
│   └── seeder.sql
├── system/                   # System files
│   ├── cache/
│   └── logs/
└── user/                     # User dashboard
    ├── dashboard.php
    ├── profile.php
    └── ...
```

---

## 📡 API Documentation

The platform exposes RESTful JSON API endpoints under `/api/`.

### Authentication
All API requests (except public endpoints) require an API key:
```
Authorization: Bearer YOUR_API_KEY
X-CSRF-Token: YOUR_CSRF_TOKEN
```

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/user.php` | Get current user profile & balance |
| GET | `/api/earnings.php` | List earning history |
| POST | `/api/earnings.php` | Create earning record |
| GET | `/api/notifications.php` | List notifications |
| GET | `/api/withdrawals.php` | List withdrawal history |
| POST | `/api/withdrawals.php` | Submit withdrawal request |
| GET | `/api/offers.php` | List available offers |
| POST | `/api/offers.php` | Complete an offer |
| GET | `/api/leaderboard.php` | Top earners leaderboard |

### Response Format

```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

Error responses:

```json
{
  "success": false,
  "error": "Error description"
}
```

HTTP status codes: `200` (success), `400` (bad request), `401` (unauthorized), `403` (forbidden), `404` (not found), `429` (rate limited), `500` (server error).

---

## 🛡 Security Features

- **Password Hashing**: Argon2id with pepper prefix
- **CSRF Protection**: Per-session tokens on all state-changing requests
- **Rate Limiting**: Per-IP and per-endpoint limits
- **Brute Force Protection**: Account lockout after failed attempts
- **Session Management**: Database-backed sessions with token rotation
- **2FA**: Time-based one-time password (TOTP) support
- **Input Validation**: Strict sanitization and validation
- **XSS Prevention**: Output escaping with `htmlspecialchars`
- **SQL Injection**: Prepared statements throughout
- **Security Headers**: CSP, HSTS, X-Frame-Options, X-Content-Type-Options
- **File Upload Restrictions**: Extension whitelist, size limits
- **Admin IP Restriction**: Optional IP whitelist for admin panel
- **Bot Detection**: User-Agent analysis and header validation
- **Cookie Security**: HTTP-only, Secure, SameSite=Strict cookies
- **HTTPS Enforcement**: Strict Transport Security header

---

## 💳 Payment Gateways Supported

| Gateway | Type | Features |
|---------|------|----------|
| **PayPal** | REST API | Payments, payouts, subscriptions |
| **Stripe** | API + Webhooks | Cards, bank transfers, Connect |
| **Razorpay** | API | Indian Rupee support, UPI, cards |
| **Binance Pay** | API | Crypto payments, USDT, BNB |
| **FaucetPay** | API | Micropayments, crypto withdrawals |

### Withdrawal Methods
- PayPal
- Bitcoin (BTC)
- Ethereum (ETH)
- USDT (TRC-20 / BEP-20)
- Binance Pay
- Bank Transfer (manual)
- Gift Cards (Amazon, Google Play, Steam)

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Commit changes: `git commit -am 'Add my feature'`
4. Push to the branch: `git push origin feature/my-feature`
5. Submit a Pull Request

### Code Style
- Follow PSR-12 coding standards
- Use meaningful variable and function names
- Add CSRF tokens to all forms
- Use prepared statements for all database queries
- Escape all output with `htmlspecialchars`
- Keep functions focused and single-purpose

---

## 📄 License

This project is licensed under the MIT License. See the `LICENSE` file for details.

---

<p align="center">Made with ❤️ by the ZynEarn Team</p>
