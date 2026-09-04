# قرعه‌کشی — LuckyDraw

سامانه قرعه‌کشی مدرن، **کاملاً آفلاین** و مناسب شبکه داخلی (بدون هیچ وابستگی به اینترنت).

| ابزار | توضیح |
|---|---|
| 🪙 **شیر یا خط** | سکه سه‌بعدی، نام دلخواه برای هر روی سکه، پرتاب چندتایی با آمار |
| 🎲 **عدد تصادفی** | بازه دلخواه، چند عدد هم‌زمان، بدون تکرار، مرتب‌سازی |
| ✅ **انتخاب از لیست** | انتخاب چند نفر از یک لیست، با/بدون حذف انتخاب‌شده‌ها |
| 🎡 **گردونه شانس** | گردونه چرخان با نشانگر سمت راست، صدا، کانفتی، حذف برنده از لیست |
| 👥 **گروه‌بندی تصادفی** | تقسیم متوازن یک لیست به N گروه یا گروه‌های K نفره، با نام دلخواه برای هر گروه |

ویژگی‌های مشترک:

- **پخش زنده با لینک/کد ورود** — بیننده‌ها همان انیمیشن و همان نتیجه را هم‌زمان می‌بینند. آدرس لینک به شکل `آدرس-سایت/live/کد` است (مثلاً `http://192.168.1.10/live/A7K2QX`). کد یا **خودکار** (۶ حرفی) ساخته می‌شود یا میزبان خودش یک **کد دلخواه** (۴ تا ۱۶ حرف/عدد، مثل `JASHN-1404`) انتخاب می‌کند؛ اگر کد در حال استفاده باشد خطا می‌گیرد. لینک ۵ تا ۶۰ دقیقه (قابل تمدید تا ۶ ساعت) اعتبار دارد و بعد خودکار حذف می‌شود. QR آفلاین برای اسکن با موبایل.
- نتایج با **مولد امن تصادفی** (`random_int`) سمت سرور تولید می‌شود.
- **وزن‌دهی**: `علی*3` یعنی سه برابر شانس؛ وزن اعشاری هم پذیرفته می‌شود (`سارا*0.5`، `رضا*0.25`).
- **ثبت‌نام جهت قرعه‌کشی** — در ابزارهای «انتخاب از لیست»، «گردونه شانس» و «گروه‌بندی»، مدیر یک **لینک ثبت‌نام** می‌سازد (`آدرس-سایت/signup/کد`، کد خودکار یا دلخواه، با QR) و برای افراد می‌فرستد. شرکت‌کننده بدون ورود به سیستم فقط **نام**، **کد** (مثلاً کد پرسنلی) یا هر دو را وارد می‌کند. ثبت‌نام‌ها به‌صورت پیش‌فرض **در انتظار تأیید مدیر** می‌مانند (یا با گزینه «تأیید خودکار» مستقیم پذیرفته می‌شوند)؛ مدیر در همان پنل، تک‌تک یا یکجا تأیید/رد می‌کند و با یک دکمه نام‌های تأییدشده را به لیست شرکت‌کننده‌ها اضافه می‌کند. مهلت ثبت‌نام ۱ ساعت تا ۷ روز (قابل تمدید) است، مدیر می‌تواند هر وقت خواست ثبت‌نام را ببندد و فرم پس از پایان مهلت خودکار حذف می‌شود. جلوی ثبت‌نام تکراری (نام/کد تکراری) و ثبت‌نام انبوه از یک دستگاه گرفته می‌شود.
- **دو زبانه (فارسی / English)** — دکمه «EN / فا» در نوار بالا کل رابط (صفحه‌ها، پیام‌ها، خطاهای API، نمونه‌ها) را عوض می‌کند؛ فارسی راست‌چین با اعداد فارسی، انگلیسی چپ‌چین با اعداد لاتین. انتخاب زبان برای هر بازدیدکننده جداگانه (کوکی) ذخیره می‌شود؛ بنابراین میزبان می‌تواند فارسی کار کند و بیننده همان پخش زنده را انگلیسی ببیند. با افزودن `?lang=en` یا `?lang=fa` به هر آدرس هم می‌توان زبان را تعیین کرد. زبان پیش‌فرض در `config.php` قابل تغییر است.
- تم تیره/روشن، اعداد فارسی/انگلیسی، صدا (بدون فایل صوتی، با Web Audio)، حالت تمام‌صفحه برای پروژکتور.
- تاریخچه با کپی و خروجی CSV، ذخیره خودکار لیست‌ها در مرورگر.
- فونت **وزیرمتن** و **Font Awesome** داخل پروژه بسته‌بندی شده‌اند.

---

## نیازمندی‌ها

- PHP **7.4** یا بالاتر (۸.x توصیه می‌شود)
- افزونه `json` (همیشه هست). `mbstring` و `pdo_sqlite` اختیاری هستند — اگر SQLite نبود، خودکار از فایل JSON استفاده می‌شود.
- پوشه `storage/` باید **قابل نوشتن** باشد.
- هیچ نیازی به Composer، دیتابیس جداگانه یا اینترنت نیست.

## نصب

### ۱) XAMPP / WAMP / Laragon (ویندوز)

1. پوشه پروژه را در `htdocs` (یا `www`) کپی کنید، مثلاً `C:\xampp\htdocs\luckydraw`.
2. مطمئن شوید `mod_rewrite` فعال است (در XAMPP پیش‌فرض فعال است). فایل `.htaccess` همراه پروژه است.
3. در مرورگر باز کنید: `http://localhost/luckydraw/`
4. برای دستگاه‌های دیگر شبکه: `http://<IP-سرور>/luckydraw/` — مثلاً `http://192.168.1.10/luckydraw/`
   - در فایروال ویندوز، پورت 80 را برای Apache باز کنید.
   - اگر با `localhost` وارد شده باشید، پنجره «لینک زنده» آدرس IP شبکه را هم پیشنهاد می‌دهد.

> اگر `mod_rewrite` در دسترس نبود، برنامه به‌صورت خودکار از لینک‌های `index.php?p=wheel` و `/?r=CODE` (به‌جای `/live/CODE`) استفاده می‌کند و همه‌چیز کار می‌کند.

### ۲) بدون وب‌سرور — سرور داخلی PHP

```bash
cd luckydraw
php -S 0.0.0.0:8080 index.php
```

سپس `http://<IP-سرور>:8080/` را باز کنید. (در ویندوز فایل `start-server.bat` را دوبار کلیک کنید.)

> برای چند بیننده هم‌زمان بهتر است `PHP_CLI_SERVER_WORKERS=8` را قبل از دستور تنظیم کنید (PHP 7.4+ روی لینوکس/مک). فایل `start-server.sh` این کار را انجام می‌دهد.

### ۳) لینوکس + Apache

```bash
sudo apt install apache2 php libapache2-mod-php php-sqlite3 php-mbstring
sudo a2enmod rewrite && sudo systemctl restart apache2
sudo cp -r luckydraw /var/www/html/
sudo chown -R www-data:www-data /var/www/html/luckydraw/storage
```

در تنظیمات سایت `AllowOverride All` باشد تا `.htaccess` اعمال شود.

### ۴) Nginx + PHP-FPM

نمونه تنظیم در `nginx.example.conf` است (شامل `try_files` و `fastcgi_param LD_REWRITE 1`).

## تنظیمات اختیاری

`config.example.php` را به `config.php` کپی کنید:

```php
return [
    'store' => 'auto',          // auto | sqlite | file
    'default_lang' => 'fa',     // زبان پیش‌فرض بازدیدکننده جدید: fa | en
    'max_rooms_total' => 300,   // سقف اتاق‌های زنده فعال روی سرور (0 = نامحدود)
    'max_rooms_per_ip' => 30,   // سقف اتاق‌های زنده به‌ازای هر آدرس کلاینت
    'max_signups_total' => 200, // سقف فرم‌های ثبت‌نام فعال روی سرور
    'max_signups_per_ip' => 20, // سقف فرم‌های ثبت‌نام به‌ازای هر آدرس کلاینت
    'frame_ancestors' => '',    // محدودکردن نمایش در iframe: '' = آزاد، "'self'" = فقط همین سایت
    'allowed_origins' => [],    // دامنه‌های اضافی مجاز برای فراخوانی API (پشت reverse proxy)
];
```

ثابت‌های دیگر (حداکثر اعضای لیست، گزینه‌های زمان اعتبار، حداکثر عمر لینک، طول کد دلخواه `LD_CODE_MIN/MAX`) در ابتدای `app/bootstrap.php` قابل تغییرند.

## ساختار پروژه

```
index.php            صفحه‌ها (front controller)
api.php              JSON API
app/                 منطق سرور (Draw, Room, Signup, Store) و قالب‌ها
app/lang/            رشته‌های ترجمه (fa.php / en.php) — برای افزودن زبان جدید همین فایل‌ها را کپی کنید
assets/              CSS/JS، فونت وزیرمتن، Font Awesome، QR و کانفتی (همه لوکال)
storage/             دیتابیس SQLite یا فایل‌های JSON اتاق‌ها (خودکار ساخته می‌شود)
.htaccess            تنظیمات Apache (URL تمیز + محافظت از storage و app)
nginx.example.conf   نمونه تنظیم Nginx
start-server.bat/.sh اجرای سریع با سرور داخلی PHP
```

## API (خلاصه)

| متد | آدرس | توضیح |
|---|---|---|
| GET | `api.php?a=info` | اطلاعات سرور (زمان، IP شبکه، نوع ذخیره‌سازی) |
| POST | `api.php?a=roll` | قرعه بدون اتاق `{tool, state}` |
| POST | `api.php?a=create` | ساخت اتاق زنده `{tool, state, ttl, title, code?}` → `{room, token}` — `code` اختیاری = کد دلخواه (اگر تکراری باشد: `409 code_taken`) |
| GET | `api.php?a=room&code=&v=&vid=` | دریافت وضعیت اتاق (polling سبک؛ اگر تغییری نباشد فقط متادیتا برمی‌گردد) |
| POST | `api.php?a=draw` | اجرای قرعه توسط میزبان `{code, token, state?}` |
| POST | `api.php?a=state` / `title` / `extend` / `clear` / `end` | مدیریت اتاق توسط میزبان |
| POST | `api.php?a=signup_create` | ساخت فرم ثبت‌نام `{tool, title, fields: name|code|both, auto, ttl, code?}` → `{signup, token}` |
| GET/POST | `api.php?a=signup` | اطلاعات عمومی فرم `{code}`؛ با `token` میزبان: فهرست ثبت‌نام‌ها (`v` = آخرین نسخه دیده‌شده) |
| POST | `api.php?a=signup_register` | ثبت‌نام شرکت‌کننده `{code, name, code_value}` (تکراری: `409 duplicate`) |
| POST | `api.php?a=signup_moderate` / `signup_set` / `signup_extend` / `signup_end` | مدیریت فرم توسط میزبان (`op: approve|reject|delete`, `entry` یا `'*'`; `open`, `auto`; `minutes`) |

توکن میزبان فقط در مرورگر میزبان نگهداری می‌شود و روی سرور فقط هش آن ذخیره است.

## امنیت

- همه ورودی‌ها سمت سرور پاک‌سازی و محدود می‌شوند (طول لیست‌ها، طول نام‌ها، بازه اعداد، حجم بدنه درخواست حداکثر ۵۱۲KB، عمق JSON).
- خروجی‌ها در قالب‌ها با `htmlspecialchars` و در جاوااسکریپت با `textContent` درج می‌شوند (بدون تزریق HTML).
- هدرهای `Content-Security-Policy` (فقط منابع same-origin)، `X-Content-Type-Options`، `Referrer-Policy` و `Permissions-Policy` روی همه صفحات ارسال می‌شود.
- درخواست‌های POST از دامنه‌های دیگر (CSRF) با بررسی `Sec-Fetch-Site`/`Origin` رد می‌شوند.
- پوشه‌های `app/` و `storage/` و فایل‌های پروژه (`.md`, `.sqlite`, `.json`, …) از وب قابل دسترسی نیستند (`.htaccess`، نمونه nginx و روتر سرور داخلی).
- سقف تعداد اتاق‌های زنده و فرم‌های ثبت‌نام (کل سرور و به‌ازای هر IP) و تعداد بیننده‌های ردیابی‌شده در هر اتاق در `config.php` قابل تنظیم است.
- فرم ثبت‌نام: نام حداکثر ۶۰ و کد حداکثر ۳۲ نویسه، حداکثر ۲۰۰۰ ثبت‌نام در هر فرم، حداکثر ۲۰ ثبت‌نام از هر آدرس کلاینت، جلوگیری از نام/کد تکراری؛ شرکت‌کننده‌ها فهرست یکدیگر را نمی‌بینند و همه داده‌ها با پایان مهلت حذف می‌شوند.
- پیام خطاهای داخلی به کاربر نشان داده نمی‌شود؛ جزئیات فقط در لاگ سرور (`error_log`) ثبت می‌شود.

## مجوزها

- کد پروژه: MIT
- فونت Vazirmatn: SIL Open Font License (`assets/fonts/vazirmatn/LICENSE-OFL.txt`)
- Font Awesome Free: Font Awesome Free License (`assets/fontawesome/LICENSE.txt`)
- qrcode-generator (MIT) و canvas-confetti (ISC)

---

## English

**LuckyDraw** is a modern, fully **offline** raffle toolkit for local networks (no internet dependency, fonts bundled): coin flip, random number, pick from list, wheel of fortune (pointer on the right, winner can be removed from the list) and random teams. Every tool can broadcast a **live link** (`http://<server>/live/<CODE>`, auto or custom code, expires after 5–60 minutes, offline QR) so all viewers see the same animation and result. The list tools can also open a **registration link** (`http://<server>/signup/<CODE>`) where participants sign up with their name and/or a code, the host approves them (or enables auto-approve) and imports them into the list with one click. Weights accept decimals (`Alice*0.5`). Results come from PHP's CSPRNG (`random_int`).

The UI is **bilingual (Persian / English)**: use the `EN / فا` button in the header, or append `?lang=en` / `?lang=fa` to any URL. The choice is stored per visitor in a cookie; `default_lang` in `config.php` sets the language for first-time visitors.

Quick start: PHP 8.1+ with `pdo_sqlite` (falls back to JSON files), point the web server document root at the project folder (see `.htaccess` / `nginx.example.conf`), or run `start-server.sh` / `start-server.bat` for the built-in PHP server.
