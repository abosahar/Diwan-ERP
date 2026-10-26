# تثبيت «الديوان» على لينكس (Ubuntu / Mint / Debian)

دليل خطوة بخطوة لتثبيت النظام على جهاز لينكس مكتبي للاستخدام في المكتب. لا تحتاج خبرة تقنية. يستغرق 5-15 دقيقة.

> **لخادم على الإنترنت** (مثل DigitalOcean)، مع نطاق وشهادة SSL لعدة منشآت، استخدم [SERVER-SETUP.md](SERVER-SETUP.md) بدلاً من هذا الدليل.

## قبل البدء

- **النظام:** Ubuntu 22.04 أو أحدث، أو Linux Mint 21 أو أحدث، أو Debian 12.
- **المساحة:** 3 جيجابايت فارغة على الأقل.
- **الإنترنت:** اتصال أثناء التثبيت.
- **كلمة مرور حسابك:** التي تدخل بها إلى الجهاز، ويجب أن يملك حسابك صلاحية `sudo`. الحساب الأول على الجهاز يملكها عادة.

## الخطوة 1: افتح الطرفية (Terminal)

اضغط **Ctrl + Alt + T**، أو ابحث عن **Terminal** في قائمة البرامج.

## الخطوة 2: الصق أمر التثبيت

انسخ السطر كاملاً، والصقه في الطرفية بـ **Ctrl + Shift + V**، ثم اضغط **Enter**:

```bash
sudo bash -c "$(curl -fsSL https://github.com/turkialawlqy/Diwan-ERP/releases/latest/download/install-linux.sh)"
```

- **إن ظهر `curl: command not found`:** نفّذ أولاً `sudo apt install -y curl`، ثم الصق الأمر مرة أخرى.
- **عند `[sudo] password`:** اكتب كلمة مرور حسابك، واضغط Enter. لن تظهر الحروف أثناء الكتابة، وهذا طبيعي.

## الخطوة 3: انتظر حتى ينتهي

الرسائل تظهر بالإنجليزية، لأن الطرفية لا تعرض العربية جيداً:

| ما يظهر | معناه |
|---|---|
| `1/6 Installing PHP and the database` | تثبيت PHP وقاعدة البيانات MySQL. |
| `2/6 Downloading the latest Diwan release` | تنزيل النظام. |
| `3/6 Database and settings` | تجهيز البيانات والإعدادات. |
| `4/6 Starting Diwan automatically with the computer` | التشغيل التلقائي مع الجهاز. |
| `5/6 Allowing the office devices to connect` | السماح لأجهزة المكتب بالوصول. |
| `6/6 Applications menu shortcut` | اختصار في قائمة البرامج. |
| `Installed successfully!` | **تم التثبيت بنجاح.** |

## الخطوة 4: الإعداد الأول

افتح المتصفح على `http://localhost:8000`، أو ابحث عن **الديوان** في قائمة البرامج. ستظهر صفحة **«تم تثبيت النظام بنجاح»**:

1. اكتب اسم المنشأة، والدولة، والعملة. العملة لا تتغير بعد البدء.
2. اكتب حساب المدير: الاسم، والبريد (اسم الدخول)، وكلمة المرور.
3. اضغط **«ابدأ استخدام النظام»**.

## الاستخدام اليومي

- **تشغيل النظام:** يعمل تلقائياً مع تشغيل الجهاز، حتى قبل تسجيل الدخول.
- **أجهزة المكتب والجوالات:** العنوان ورمز QR في **الإعدادات** داخل النظام، في مربع «الوصول من أجهزة المكتب».
- **التحديث:** يتحدّث تلقائياً كل ليلة الساعة 3:30. يأخذ نسخة احتياطية قبل تغيير قاعدة البيانات، ويرجع للإصدار السابق تلقائياً إن حدثت مشكلة.

| المطلوب | الأمر |
|---|---|
| تحديث الآن | `sudo bash /opt/diwan/current/deploy/linux/update.sh` |
| نسخة احتياطية الآن | `cd /opt/diwan/current && sudo -u diwan php artisan erp:backup` |
| إعادة تشغيل النظام | `sudo systemctl restart diwan-web diwan-queue diwan-scheduler` |
| حالة النظام | `systemctl status diwan-web` |

## أماكن الملفات

| المجلد أو الملف | المحتوى |
|---|---|
| `/opt/diwan/secrets.txt` | **كلمات المرور.** انسخه إلى مكان آمن (`sudo cat /opt/diwan/secrets.txt`). |
| `/opt/diwan/shared/storage/app/backups/` | **النسخ الاحتياطية الليلية.** انسخها أسبوعياً إلى فلاش أو تخزين سحابي. |
| `/opt/diwan/shared/.env` | الإعدادات، مثل البريد. للتعديل: `sudo nano /opt/diwan/shared/.env`، ثم «إعادة تشغيل النظام» من الجدول أعلاه. |

لإعداد البريد، انظر أسطر `MAIL_` في [دليل ويندوز](INSTALL-WINDOWS.md#إرسال-الفواتير-بالبريد-اختياري). بعد تعديلها نفّذ:
```bash
cd /opt/diwan/current && sudo -u diwan php artisan optimize && sudo systemctl restart diwan-web diwan-queue diwan-scheduler
```

## حل المشكلات

| المشكلة | الحل |
|---|---|
| `This installer is for Ubuntu, Debian and Mint` | نظامك يستخدم مدير حزم آخر. استخدم [دليل الخادم](SERVER-SETUP.md) كمرجع للحزم المطلوبة. |
| `Could not download Diwan` | تحقق من الإنترنت، ثم أعد لصق الأمر. إعادة التشغيل آمنة. |
| أجهزة المكتب لا تفتح العنوان | تأكد أنها على نفس الراوتر. إن كان لديك جدار حماية غير `ufw`، اسمح بالمنفذ 8000. |
| النظام لا يفتح | `sudo systemctl restart diwan-web`، ثم راجع: `journalctl -u diwan-web -n 50`. |

لأي مشكلة أخرى: صوّر الطرفية وافتح طلباً في [صفحة المشروع](https://github.com/turkialawlqy/Diwan-ERP/issues).
