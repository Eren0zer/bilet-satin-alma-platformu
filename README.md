Aşağıdaki metni doğrudan `README.md` olarak kullanabilirsin. XAMPP ve Docker kurulum adımları, proje mimarisi, roller, veri modeli, eklediğin ilave özellikler ve sorun giderme dahil **çok detaylı** hazırlanmıştır.

---

# Bilet Satın Alma Platformu

Basit, hızlı ve anlaşılır bir PHP (PDO + SQLite) uygulaması. Yolcular sefer arar, koltuk seçer, kupon uygular ve cüzdan bakiyesiyle bilet satın alır.
Firma yetkilileri sefer ve kuponlarını yönetir, biletleri görür ve iptal edebilir.
Admin firmaları ve kullanıcıları yönetir.

Arayüz Pico.css ile sade tutuldu. Router tek dosyadan (public/index.php) çalışır. Veritabanı SQLite olup ilk çalıştırmada otomatik oluşur.

---

## İçindekiler

* [Özellikler](#özellikler)
* [Eklenen İlave Özellikler](#eklenen-ilave-ozellikler)
* [Teknolojiler](#teknolojiler)
* [Gereksinimler](#gereksinimler)
* [Kurulum – Docker](#kurulum--docker)
* [Kurulum – XAMPP/Apache](#kurulum--xamppapache)
* [Varsayılan Hesaplar ve Roller](#varsayılan-hesaplar-ve-roller)
* [Uygulama Akışları](#uygulama-akışları)
* [Koltuk Düzeni ve Numaralandırma](#koltuk-düzeni-ve-numaralandırma)
* [Kupon Yönetimi ve Kullanımı](#kupon-yönetimi-ve-kullanımı)
* [PDF / Yazdır](#pdf--yazdır)
* [Güvenlik Notları](#güvenlik-notları)
* [JSON Uç Noktaları](#json-uç-noktaları)
* [Geliştirme İpuçları](#geliştirme-ipuçları)
* [Sorun Giderme](#sorun-giderme)
* [Web Sitesinden Görüntüler](#web-sitesinden-görüntüler)
* [Dizin Yapısı](#dizin-yapısı)
* [Lisans](#lisans)

---

## Özellikler

* Ziyaretçi olarak sefer arama ve detay görüntüleme
* Giriş yapılmamış kullanıcı için sefer detayında **koltuk haritasını salt-okunur** görme (dolu/boş)
* Kayıt, giriş, çıkış
* Yolcu cüzdanı: bakiye görüntüleme ve bakiye yükleme
* Kupon kodu ile indirimli satın alma
* 2+2 koltuk düzeni (yatay), kare ve eşit kutular, anlaşılır numaralandırma
* Satın alınan biletleri listeleme, detay ve iptal (kalkıştan ≥ 1 saat önce iade)
* Firma paneli: sefer CRUD, bilet listesi/iptali, kupon yönetimi
* Admin paneli: firmalar CRUD, firmaya yetkili atama, kullanıcıları aktif/pasif yapma

---

## Eklenen İlave Özellikler

* **Firma bazlı kupon yönetimi:** Kuponlar firmaya bağlanabilir (`firm_id`), bağlanmamışsa **global** kabul edilir.
* **Kupon önizleme:** Sefer detayında “Uygula” butonuyla kupon indirimi ve **yeni fiyat** anında gösterilir.
* **Kullanıcı yönetimi (admin):** Hesapları aktif/pasif yapma; son aktif admin güvenliği, adminin kendini pasifleştirememesi.
* **Giriş yapmayan için koltuk haritası:** Satın alma pasif, ama dolu/boş net görünür.
* **Basit güvenlik katmanı:** CSRF token, girişte session id yenileme, parola rehash, opsiyonel login rate-limit ve audit log.
* **PDF bağımlılığı opsiyonel:** Varsayılan olarak “Yazdır” sayfası; istersen dompdf ekleyerek gerçek PDF üretebilirsin.

---

## Teknolojiler

* PHP 8.x
* PDO + SQLite
* Apache (XAMPP veya Docker imajı)
* Pico.css (CDN)
* Vanilla JavaScript
* Docker / docker-compose
* (Opsiyonel) dompdf – PDF çıktısı için

---

## Gereksinimler

* Docker Desktop veya Docker Engine + docker-compose
  veya
* XAMPP (Apache + PHP 8.x), PHP’de `pdo_sqlite` açık olmalı

---

## Kurulum – Docker

1. Projeyi klonla:

   ```bash
   git clone <repo-url> bilet-platform
   cd bilet-platform
   ```

2. `Dockerfile` (özet)

   ```dockerfile
   FROM php:8.2-apache
   RUN apt-get update && apt-get install -y libsqlite3-dev \
     && docker-php-ext-install pdo pdo_sqlite \
     && a2enmod rewrite

   RUN printf '%s\n' \
     '<VirtualHost *:80>' \
     '  DocumentRoot /var/www/html/public' \
     '  <Directory /var/www/html/public>' \
     '    AllowOverride All' \
     '    Require all granted' \
     '    Options Indexes FollowSymLinks' \
     '  </Directory>' \
     '</VirtualHost>' \
     > /etc/apache2/sites-available/000-default.conf

   WORKDIR /var/www/html
   COPY . /var/www/html

   RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data \
    && chmod -R 775 /var/www/html/data
   ```

3. `docker-compose.yml`

   ```yaml
   services:
     web:
       build: .
       ports:
         - "8080:80"
       volumes:
         - ./data:/var/www/html/data
       restart: unless-stopped
   ```

4. Çalıştır:

   ```bash
   docker compose up --build
   ```

   Aç: `http://localhost:8080`

> İlk istekle birlikte `data/app.sqlite` ve tablo şeması otomatik oluşur.

---

## Kurulum – XAMPP/Apache

1. Proje klasörünü XAMPP `htdocs` altına kopyala (veya sanal host oluştur).
2. DocumentRoot’u `public/` yap. `.htaccess` içinde basit kural:

   ```
   Options -Indexes
   <IfModule mod_rewrite.c>
     RewriteEngine On
     RewriteCond %{REQUEST_FILENAME} !-f
     RewriteCond %{REQUEST_FILENAME} !-d
     RewriteRule ^ index.php [QSA,L]
   </IfModule>
   ```
3. PHP’de `pdo_sqlite` aktif olmalı.
4. `data/` dizini web sunucusu kullanıcısı tarafından yazılabilir olmalı.
5. Tarayıcıdan `http://localhost/<klasor>` ile aç.

---

## Varsayılan Hesaplar ve Roller

| Rol       | E-posta     | Parola   |
| --------- | ----------- | -------- |
| Admin     | admin@local | admin123 |
| Firma     | firma@local | firma123 |
| Kullanıcı | user@local  | user123  |

* Admin panelinden yeni firma eklediğinde otomatik bir firma yetkilisi oluşturulur: `<slug>@local / <slug>123`
* Roller:

  * `user`: bilet satın alır, iptal eder, bakiye & kupon kullanır
  * `firm_admin`: kendi firmasının sefer, bilet ve kuponlarını yönetir
  * `admin`: firmalar ve kullanıcılar üzerinde tam yetki

---

## Uygulama Akışları

* **Ziyaretçi**

  * Sefer arar, detaya girince koltuk haritasını dolu/boş görür.
  * Satın alma için giriş/kayıt gerekir.
* **Kullanıcı**

  * Sefer detayında koltuk seçer.
  * Kupon kodunu “Uygula” ile dener; indirim ve yeni fiyat anında görünür.
  * Cüzdandan ödeme yaparak bileti alır; biletlerini görüntüler/PDF yerine Yazdır sayfasından çıktı alır.
  * Kalkıştan ≥ 1 saat önce ise iptal edebilir; ücret cüzdana iade edilir.
* **Firma Yetkilisi**

  * Sefer CRUD, bilet listesi ve iptal.
  * Kupon CRUD (sadece kendi firmasına bağlı kuponlar).
* **Admin**

  * Firmalar CRUD, firmaya yetkili atama/kaldırma.
  * Kullanıcıları aktif/pasif yapma (son aktif admin kilitlenemez; admin kendini pasifleştiremez).

---

## Koltuk Düzeni ve Numaralandırma

* 2+2 yatay düzen, sabit koridor.
* Her sütunda üstten aşağı: 4, 3, koridor, 2, 1.
* Sütunlar sağdan sola akarak artar; koltuk sayısı arttıkça düzen bozulmadan devam eder.
* Tüm koltuk kutuları kare ve eşit boyuttadır; dolu koltuklar pasifleştirilir.

---

## Kupon Yönetimi ve Kullanımı

* Kuponlar `coupons` tablosunda tutulur; `firm_id` dolu ise yalnız o firmada, `NULL` ise global geçerlidir.
* Firma panelinde kupon oluşturma, düzenleme, aktif/pasif, silme işlemleri yapılır.
* Satın alma sırasında sadece **ilgili seferin firmasına** ait (veya global) kupon kabul edilir.
* Sefer detayındaki “Uygula” butonu ile indirim önizlemesi yapılır; üstte görünen fiyat yeni fiyata güncellenir.

---

## PDF / Yazdır

* Varsayılan: `views/tickets/print.php` ile **Yazdır** sayfası kullanılır; tarayıcıdan PDF’e kaydedebilirsin.
* Gerçek PDF çıktısı istersen:

  1. `composer require dompdf/dompdf`
  2. PDF rotasını (`ticket-pdf`) aktif ederek linki “PDF” yap.
  3. Docker imajında composer step’i ekliyse vendor otomatik kurulacaktır.

> Sade kurulum için `vendor/` depoya eklenmez. PDF istemiyorsan `vendor/`’a ihtiyaç yoktur.

---

## Güvenlik Notları

* Tüm formlarda **CSRF token**.
* Girişte `session_regenerate_id(true)` ile oturum sabitleme önlemi.
* Parola doğrulanınca `password_needs_rehash` ile otomatik rehash.
* `login_with_password` sadece **aktif** kullanıcıları kabul eder.
* Opsiyonel: oturum tabanlı **login rate-limit** ve **audit log**.
* Geliştirme modunda CSP kapalı; prod’da CSS/JS’yi dış dosyaya taşıyıp CSP açılabilir.

---

## JSON Uç Noktaları

### Kupon Önizleme

```
GET index.php?r=apply-coupon&trip_id=<id>&code=<KOD>
```

Yanıt alanları:
`ok`, `discount_percent`, `price_original(_fmt)`, `price_discounted(_fmt)`, `message`.

---

## Geliştirme İpuçları

* Stil/JS’i `views/layout/header.php` içinde tutabilir; prod’da `public/assets/app.css/js` içine taşıyıp CSP’yi etkinleştirebilirsin.
* Veritabanı migrasyonu ilk istekle tetiklenir; `migrate()` içinde eski şema için **PRAGMA/ALTER** ile geriye dönük uyumluluk yazılıdır.
* Kupon önizlemesi yalnızca hesaplama yapar ve state değiştirmez; satın almada tekrar doğrulanır.

---

## Sorun Giderme

* **Docker 403 Forbidden**
  Apache vhost’ta DocumentRoot ve Directory izinleri doğru olmalı. Bu depoda `Dockerfile` bunu açıkça `public/` olarak ayarlar:

  ```
  <VirtualHost *:80>
    DocumentRoot /var/www/html/public
    <Directory /var/www/html/public>
      AllowOverride All
      Require all granted
      Options Indexes FollowSymLinks
    </Directory>
  </VirtualHost>
  ```

  Yeniden derle:
  `docker compose build --no-cache && docker compose up`

* **Port çakışması**
  8080 doluysa `docker-compose.yml` içinde `8081:80` yapıp `http://localhost:8081` ile aç.

* **mod_rewrite kapalı**
  XAMPP’ta etkinleştir; Docker imajında aktiftir.

* **SQLite yazma izni**
  `data/` dizini web sunucusu kullanıcısı tarafından yazılabilir olmalı.

---
## Uygulama İçi Görüntüler


---

## Dizin Yapısı

```
.
├─ app/
│  ├─ bootstrap.php       # Oturum, helper, migrate çağrısı
│  ├─ db.php              # SQLite migrate() ve bağlantı
│  ├─ auth.php            # Kimlik doğrulama, rol kontrolü
│  ├─ helpers.php         # render, redirect, csrf_field, money_fmt vb.
│  └─ (opsiyonel) security.php
├─ public/
│  ├─ index.php           # Router (switch-case)
│  ├─ .htaccess           # rewrite rules
│  └─ assets/             # (İstersen CSS/JS dışa taşı)
├─ views/
│  ├─ layout/
│  │  ├─ header.php
│  │  └─ footer.php
│  ├─ home.php
│  ├─ trips/
│  │  ├─ list.php
│  │  └─ detail.php       # Koltuk haritası + kupon önizleme
│  ├─ tickets/
│  │  ├─ my.php
│  │  ├─ show.php
│  │  └─ print.php        # Yazdır görünümü (PDF alternatifi)
│  ├─ firm/
│  │  ├─ index.php        # Sefer CRUD
│  │  ├─ tickets.php
│  │  └─ coupons.php      # Kupon CRUD (firma bazlı)
│  └─ admin/
│     ├─ index.php
│     └─ users.php        # Aktif/pasif
├─ data/
│  └─ app.sqlite          # Otomatik oluşur
├─ Dockerfile
├─ docker-compose.yml
├─ composer.json          # (PDF istersen dompdf eklemek için)
└─ README.md
```
---

## Lisans

MIT

---

Bu README, tek başına projeyi derleyip çalıştırmak, geliştirmek ve değerlendirmek için yeterlidir. İhtiyaç duyduğun ekran görüntülerini `docs/screenshots/` dizinine ekleyip README’ye referans verebilirsin.
