# Tamizhmart 🛍️
### White-Label E-Commerce Platform


> Built by **[@sivabalaji-tn](https://github.com/sivabalaji-tn)** -😄
> Readme Content by AI

A complete, multi-tenant e-commerce platform where each shop owner gets their own fully branded storefront — customizable colors, fonts, logo, products, and orders — all from a single codebase.

No frameworks. No npm install taking 4 hours. Just PHP, MySQL, and vibes. ✨

---

## ⚠️ License & Usage

This project is **source-available** — you can read it, learn from it, run it locally, and build on top of it.

**However:**
- ❌ Do NOT redistribute, resell, or publish this as your own work
- ❌ Do NOT use this in a commercial product without permission
- ❌ Do NOT remove this README or the author credit
- ✅ You CAN fork it for personal use
- ✅ You CAN learn from the code
- ✅ You CAN contribute improvements

**For permissions, collaborations, or licensing — contact the author first.**

> 📩 Reach out to **Sivabalaji** before you do anything funny with this code. He will know. 👀

---

## 👨‍💻 Author

**Sivabalaji**
- GitHub: [@sivabalaji-tn](https://github.com/sivabalaji-tn)
- Built in: Tamil Nadu, India 🇮🇳
- Powered by: Coffee, curiosity, and late nights ☕🌙


---

## ✨ Features

### 🧑‍💼 Shop Owner Dashboard
| Feature | Description |
|---|---|
| **Analytics** | Revenue charts, order trends, top products, customer stats |
| **Orders** | View, filter, and update order statuses with one click |
| **Products** | Add/edit/delete with images or URL, pricing, stock, discounts |
| **Categories** | Organize products into banner-supported categories |
| **Popups & Offers** | Schedule promotional popups with images and CTAs |
| **Store Settings** | Name, logo, banner, announcement bar, phone, address |
| **Theme Customizer** | Live-preview color picker + 8 font choices + 8 quick presets |
| **Social Links** | WhatsApp, Instagram, Facebook, X, YouTube, Website |
| **Setup Wizard** | Guided 5-step onboarding for new shop owners |

### 🛒 Customer Storefront
| Feature | Description |
|---|---|
| **Home Page** | Hero banner, categories grid, featured products, promo strip |
| **Product Listing** | Filters, price range slider, sort, pagination |
| **Product Detail** | Full image, quantity selector, related products |
| **Cart** | AJAX live updates, quantity changes, line totals |
| **Checkout** | Address form, order notes, COD, animated success screen |
| **Order History** | Accordion cards with animated status timeline |
| **Profile** | Edit name/phone/address, change password, order stats |
| **Search** | Real-time product search across the shop |

### 📱 PWA (Progressive Web App)
- Installable on Android & iOS home screen
- Service worker with offline support
- "Install App" smart banner
- Offline fallback page
- Background sync ready
- Push notifications scaffold

> Yes, it works offline. Your users can browse products even when their internet is as reliable as a government website. 🙃

---

## 🏗️ Tech Stack

- **Backend:** PHP 8.x (procedural — no Laravel, no Symfony, no drama)
- **Database:** MySQL via phpMyAdmin / XAMPP
- **Frontend:** Bootstrap 5.3 + Bootstrap Icons + custom CSS variables
- **Charts:** Chart.js (owner analytics)
- **Fonts:** Google Fonts (dynamic per shop theme)
- **PWA:** Service Worker + Web App Manifest

> No Node.js. No `node_modules` folder that weighs more than your laptop. 🙏

---

## 📁 Project Structure

```
Tamizhmart/
├── index.php                  # Root entry — routes customer straight to their shop
├── manifest.php               # Dynamic PWA manifest (per-shop themed)
├── sw.js                      # Service Worker (offline caching)
├── offline.php                # Offline fallback page
├── 404.php                    # Custom 404 (prettier than your ex's apology)
├── .htaccess                  # Apache config (security, caching, compression)
├── Tamizhmart_schema.sql        # ← Run this first. Seriously. First.
│
├── config/
│   └── db.php                 # Database connection (put your credentials here)
│
├── auth/                      # Customer authentication
│   ├── login.php              # Customer login (per-shop themed)
│   ├── register.php           # Customer registration
│   └── logout.php             # Bye bye session 👋
│
├── owner/                     # Shop owner dashboard
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── setup.php              # 5-step onboarding wizard (runs on first login)
│   ├── dashboard.php
│   ├── orders.php
│   ├── products.php
│   ├── categories.php
│   ├── popups.php
│   ├── settings.php
│   ├── theme.php
│   ├── analytics.php
│   ├── social.php
│   └── includes/
│       ├── sidebar.php        # Shared dashboard layout
│       └── footer.php         # Closing tags + flash alerts
│
├── shop/                      # Customer-facing storefront
│   ├── index.php              # Home page
│   ├── products.php           # Product listing with filters
│   ├── product.php            # Product detail page
│   ├── cart.php               # Shopping cart
│   ├── cart_action.php        # AJAX cart handler
│   ├── checkout.php           # Checkout + order placement
│   ├── orders.php             # Customer order history
│   ├── profile.php            # Customer profile
│   └── includes/
│       ├── shop_head.php      # Navbar, PWA meta, global CSS
│       ├── shop_foot.php      # Footer, social links, PWA JS
│       └── product_card.php   # Reusable product card component
│
├── email/
│   └── order_email.php        # HTML email template builder
│
└── assets/
    ├── js/
    │   └── pwa.js             # Service worker registration + install prompt
    ├── icons/                 # PWA icons (generate with realfavicongenerator.net)
    │   ├── icon-72.png
    │   ├── icon-96.png
    │   ├── icon-128.png
    │   ├── icon-192.png
    │   └── icon-512.png
    └── uploads/
        ├── logos/             # Shop logos
        ├── banners/           # Shop banners
        ├── products/          # Product images
        └── popups/            # Popup offer images
```

---

## 🚀 Installation (XAMPP / Local)

### Step 1 — Place files
```bash
C:/xampp/htdocs/Tamizhmart/
# Mac/Linux:
/Applications/XAMPP/htdocs/Tamizhmart/
```

### Step 2 — Create the database
1. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Create a new database named `Tamizhmart_db`
3. Select it → **Import** → upload `Tamizhmart_schema.sql` → **Go**

### Step 3 — Configure database connection
Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // your MySQL username
define('DB_PASS', '');          // blank for XAMPP default
define('DB_NAME', 'Tamizhmart_db');
```

### Step 4 — Create upload folders
```bash
mkdir -p assets/uploads/logos
mkdir -p assets/uploads/banners
mkdir -p assets/uploads/products
mkdir -p assets/uploads/popups
mkdir -p assets/icons
```
Or on Windows, right-click → New Folder. You know how it works. 😄

### Step 5 — PWA Icons (optional but recommended)
1. Go to https://realfavicongenerator.net
2. Upload a 512×512 PNG logo
3. Copy icons to `assets/icons/` named: `icon-72.png`, `icon-96.png`, `icon-128.png`, `icon-192.png`, `icon-512.png`

### Step 6 — Launch!
```
http://localhost/Tamizhmart/
```
🎉 If it works on the first try, you're either very lucky or a genius.

---

## 🔑 First Time Setup

1. Visit `http://localhost/Tamizhmart/` and register as a shop owner
2. Login → auto-redirected to the **5-step Setup Wizard**:
   - Step 1: Shop name & description
   - Step 2: Upload logo
   - Step 3: Pick theme colors & font
   - Step 4: Add first category & product
   - Step 5: Announcement bar + phone → **Launch!**
3. Your shop is live at: `http://localhost/Tamizhmart/?shop=YOUR_SLUG`

---

## 🌐 Customer Flow

```
Visit shop URL
    ↓
Browse products / search / filter by category
    ↓
View product detail → Add to cart
    ↓
[Not logged in?] → Register / Login
    ↓
Cart → Checkout → Enter address → Place Order (COD)
    ↓
Order confirmed → Track in "My Orders"
    ↓
Owner updates status → Customer sees live timeline
    ↓
Customer gets their cake. Everyone is happy. 🎂
```

---

## 🎨 Dynamic Theme System

Each shop has its own theme stored in the database. The owner picks colors in the dashboard and the entire storefront updates instantly — no redeploy, no cache clear, no crying.

```css
:root {
  --primary:   [owner picked this];
  --secondary: [and this];
  --bg:        [and this];
  --text:      [even this];
}
```

---

## 📱 PWA Setup

| Feature | Status |
|---|---|
| Web App Manifest | ✅ Dynamic per shop |
| Service Worker | ✅ `sw.js` at root |
| Offline Page | ✅ `offline.php` |
| Install Banner | ✅ Auto-shown after 4s |
| iOS Meta Tags | ✅ apple-mobile-web-app |
| Push Notifications | 🔧 Scaffold ready |
| Background Sync | 🔧 Scaffold ready |

> ⚠️ Service workers need HTTPS in production. On localhost they work fine. Don't blame the code if your hosting has no SSL — just get a free Let's Encrypt cert.

---

## 🔒 Security

- Prepared statements everywhere — SQL injection is not welcome here
- Passwords hashed with bcrypt — not MD5, we're not living in 2008
- Session-based auth with `shop_id` binding — one shop can't peek at another
- File uploads validated by type + size — you can't upload a `.php` disguised as a `.jpg`
- `.htaccess` blocks direct access to config, SQL dumps, and `.env`

---

## 🛠️ Rename the Root Folder

Want to rename `Tamizhmart` to something else? Only 3 files need updating — `sw.js`, `pwa.js`, and `.htaccess`. Everything else uses relative paths and is completely safe.

---

## 🐛 Troubleshooting

| Problem | Fix |
|---|---|
| Blank page | Add `ini_set('display_errors',1);` to `config/db.php` |
| Images not uploading | `chmod 755 assets/uploads/*` |
| Can't login | Clear browser cookies/session. Classic. |
| DB error | Check credentials in `config/db.php` |
| Service worker broken | Needs HTTPS in production |
| Fonts not loading | You're offline or Google is having a bad day |
| Everything is broken | Deep breath. Check error log. You got this. 💪 |

---

## 📦 Database Tables

| Table | Purpose |
|---|---|
| `owners` | Shop administrator accounts |
| `shops` | One shop per owner — name, slug, theme, logo, banner |
| `shop_settings` | Key-value store for social links, phone, setup status |
| `users` | Customer accounts (scoped per shop) |
| `categories` | Product categories |
| `products` | Products with pricing, stock, images |
| `cart` | Active shopping cart |
| `orders` | Placed orders with status and total |
| `order_items` | Line items per order |
| `popups` | Scheduled promotional popups |

---

## 🚢 Deploying to Production

1. Upload files via cPanel / FTP / Git
2. Create MySQL database and import `Tamizhmart_schema.sql`
3. Update `config/db.php` with production credentials
4. Enable HTTPS — Let's Encrypt via cPanel is free, no excuses
5. Update `RewriteBase` in `.htaccess` if not at root
6. `chmod 755 assets/uploads/*`
7. Update PWA scope in `sw.js` if folder name changed

---

## ⭐ Support the Project

If this saved you time, money, or sanity — drop a ⭐ on GitHub.

If you found a bug — open an issue.

If you want to use this commercially — talk to **Sivabalaji** first.

If you steal this and sell it — karma is real and so is copyright law. 🙏

---

*Built with ❤️ in Tamil Nadu — Tamizhmart v1.0*
*© 2025 Sivabalaji. All rights reserved.*