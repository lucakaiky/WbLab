<h1 align="center">WbLab</h1>

<p align="center">
  <strong>Run multiple websites on your own computer with PHP, a database and one simple dashboard.</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white" alt="PHP 8.3">
  <img src="https://img.shields.io/badge/Apache-2.4-D22128?logo=apache&logoColor=white" alt="Apache 2.4">
  <img src="https://img.shields.io/badge/MariaDB-11.4-003545?logo=mariadb&logoColor=white" alt="MariaDB 11.4">
  <img src="https://img.shields.io/badge/Docker%20Compose-ready-2496ED?logo=docker&logoColor=white" alt="Docker Compose">
  <img src="https://img.shields.io/badge/Use-local%20development-2E8B57" alt="Local development">
</p>

<p align="center">
  <a href="#quick-start"><b>Quick start</b></a> &nbsp;|&nbsp;
  <a href="#add-your-first-website"><b>Add a website</b></a> &nbsp;|&nbsp;
  <a href="#databases"><b>Databases</b></a> &nbsp;|&nbsp;
  <a href="#lan-access"><b>LAN access</b></a> &nbsp;|&nbsp;
  <a href="#troubleshooting"><b>Troubleshooting</b></a>
</p>

---

## What is this?

WbLab is a small Docker setup that works like a personal web host. Drop a project into the `sites/` folder, start the containers, and it appears in your browser. No need to install PHP, Apache or MySQL on your computer.

It is a good fit if you are learning web development, testing a project, or showing a demo to someone on the same network.

| Feature | What it does |
| :--- | :--- |
| **Multiple websites** | Every folder in `sites/` becomes its own local website. |
| **PHP 8.3 + Apache** | Runs plain HTML as well as PHP applications. |
| **MariaDB** | A shared database with persistent storage. |
| **phpMyAdmin** | Manage databases and import `.sql` files in the browser. |
| **Dashboard** | See all your projects and control LAN access in one place. |
| **Optional LAN access** | Test on your phone or tablet. Off by default. |

> [!IMPORTANT]
> This project is meant for **local development, demos and testing**. It is not suitable for public hosting. All sites share one container, so only run code you trust.

---

## Quick start

**You will need:** [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows / macOS) or Docker Engine with the Compose plugin (Linux).

**1. Get the project.** Clone this repository or download it, then open a terminal in the folder that contains `docker-compose.yml`.

**2. Create your settings file.**

```powershell
# Windows PowerShell
Copy-Item .env.example .env
```

```bash
# macOS / Linux
cp .env.example .env
```

Open `.env` and set a strong value for `MYSQL_ROOT_PASSWORD`.

**3. Start everything.**

```bash
docker compose up -d --build
```

**4. Open the dashboard** at **<http://localhost:8090/>**

| Service | Address | Who can open it |
| :--- | :--- | :--- |
| Dashboard | <http://localhost:8090/> | This computer only |
| phpMyAdmin | <http://localhost:8091/> | This computer only |
| LAN endpoint | `http://<your-ip>:8092/` | Other devices, only when LAN access is on |
| MariaDB (from your computer) | `127.0.0.1:3310` | This computer only |

If a port is already taken, change it in `.env` and run the start command again.

---

## Add your first website

1. Create a folder in `sites/`, for example `sites/my-website/`.
2. Put your files inside, with `index.html` or `index.php` in the root of that folder.
3. Refresh the dashboard, or open the site directly:

```text
http://my-website.localhost:8090/
```

The folder name becomes the address, so `sites/shop/` is available at `http://shop.localhost:8090/`. Use simple names made of letters, numbers and hyphens.

<details>
<summary><b>Project structure</b></summary>

```text
local-shared-hosting/
├── hosting/                 Dashboard and LAN switch
├── sites/                   Your websites go here
│   ├── example/             Included example project
│   └── my-website/          Your own project
├── docker/                  Dockerfile, Apache and PHP settings
├── .env.example             Template for your local settings
└── docker-compose.yml       Web, database and phpMyAdmin services
```

Your own folders in `sites/` are ignored by Git. Only `sites/example/` is tracked.

</details>

<details>
<summary><b>Optional: per-site <code>.htaccess</code></b></summary>

A site can include its own `.htaccess` file, for example:

```apache
DirectoryIndex index.html index.htm index.php
Options -Indexes
```

Not every Apache directive is allowed here. An unsupported one causes a `500` error, so add rules one at a time.

</details>

---

## Databases

### Open phpMyAdmin

1. Go to <http://localhost:8091/>.
2. Sign in as `root` with the `MYSQL_ROOT_PASSWORD` from your `.env`.
3. Create a database or import an `.sql` file.

### Create a database and user for your app

Use a separate user instead of putting the root password into your website code.

```sql
CREATE DATABASE shop_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'shop_user'@'%' IDENTIFIED BY 'replace-with-a-strong-password';
GRANT ALL PRIVILEGES ON shop_db.* TO 'shop_user'@'%';
FLUSH PRIVILEGES;
```

### Connect from PHP

> [!TIP]
> In your PHP code, use `db` as the host, **not** `localhost`. Inside the container, `localhost` points to the web server itself.

```php
<?php
$pdo = new PDO(
    'mysql:host=db;port=3306;dbname=shop_db;charset=utf8mb4',
    'shop_user',
    'replace-with-a-strong-password',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
```

A database tool running directly on your computer connects to `127.0.0.1` on port `3310` instead.

---

## LAN access

Want to check your site on a phone or tablet? Turn on LAN access. It is **off by default**.

1. Switch on **LAN access** in the dashboard (on the hosting computer).
2. Find the computer's IPv4 address. On Windows, run `ipconfig` and look for **IPv4 Address**.
3. On the other device, open the address with the `/sites/<site-name>/` path:

```text
http://192.168.1.50:8092/sites/example/
```

`my-website.localhost` only works on the hosting computer. Other devices must use the IP address and the `/sites/<site-name>/` path.

<details>
<summary><b>Windows Firewall rule (if the other device cannot connect)</b></summary>

Open PowerShell **as Administrator** and allow the port on private networks only:

```powershell
New-NetFirewallRule `
  -DisplayName "WbLab" `
  -Direction Inbound `
  -Protocol TCP `
  -LocalPort 8092 `
  -Action Allow `
  -Profile Private
```

If you changed `LAN_PORT`, use your value instead of `8092`.

</details>

> [!WARNING]
> While LAN access is on, any device on the network can open the dashboard and change the switch. Traffic is not encrypted. Use it on a trusted network only and turn it off when you are done.

---

## Configuration

Edit the `.env` file to change these values:

| Variable | Default | Purpose |
| :--- | :--- | :--- |
| `WEB_PORT` | `8090` | Dashboard and websites (this computer only) |
| `PHPMYADMIN_PORT` | `8091` | phpMyAdmin (this computer only) |
| `LAN_PORT` | `8092` | Endpoint for other devices on your network |
| `DB_PORT` | `3310` | MariaDB port for tools on your computer |
| `MYSQL_ROOT_PASSWORD` | placeholder | Database root password. Change it before first start. |
| `PMA_UPLOAD_LIMIT` | `256M` | Upload limit in phpMyAdmin |
| `TZ` | `UTC` | Time zone of the containers |

These values change the ports on your computer only. Services still talk to each other on their usual internal ports.

---

## Useful commands

| Task | Command |
| :--- | :--- |
| Start (and rebuild) | `docker compose up -d --build` |
| Check status | `docker compose ps` |
| Web logs | `docker compose logs -f web` |
| Database logs | `docker compose logs -f db` |
| Test Apache config | `docker compose exec web apache2ctl configtest` |
| Restart | `docker compose restart` |
| Stop (keeps your data) | `docker compose down` |
| Stop and **delete all data** | `docker compose down -v` |

> [!CAUTION]
> `docker compose down -v` permanently deletes your databases and the saved LAN setting.

---

## Troubleshooting

<details>
<summary><b>A site shows "500 Internal Server Error"</b></summary>

Look at the logs first:

```bash
docker compose logs --tail=100 web
```

If the message mentions `.htaccess`, rename that file temporarily. If the error disappears, add the rules back one by one to find the problem.

</details>

<details>
<summary><b>The site does not load, or shows the wrong page</b></summary>

- Make sure `index.html` or `index.php` is directly inside the site folder.
- Check that the address matches the folder name, for example `http://my-website.localhost:8090/`.
- If both `index.php` and `index.html` exist, Apache picks one based on `DirectoryIndex`.

</details>

<details>
<summary><b>CSS, JavaScript or images are missing</b></summary>

- Open the browser Developer Tools, go to the **Network** tab and look for `404` errors.
- Check that file paths and letter case match exactly.
- Libraries loaded from a CDN (such as Bootstrap) need an internet connection. For offline use, keep the files inside your site folder.

</details>

<details>
<summary><b>A site cannot connect to the database</b></summary>

- Use host `db`, port `3306`, and the database user you created.
- Check that the database and the user's privileges exist.
- Run `docker compose ps` and `docker compose logs --tail=100 db`.

</details>

<details>
<summary><b>Another device cannot open the site</b></summary>

- Confirm that LAN access is switched on in the dashboard.
- Use the host's IPv4 address, `LAN_PORT` and the `/sites/<site-name>/` path.
- Make sure both devices are on the same network and the firewall allows the port.

</details>

<details>
<summary><b>A port is already in use</b></summary>

Change the port in `.env`, then run `docker compose up -d --build` again.

</details>

---

## Good to know

- **Shared environment:** all sites run in one PHP 8.3 / Apache container with the same extensions.
- **No isolation between sites:** only run projects you trust.
- **Secrets stay private:** `.env` is ignored by Git, and database data lives in Docker volumes, not in the project folder.
- **Not for production:** HTTPS, public DNS and hardening are outside the scope of this project.

<p align="center">
  <br>
  <sub>Put your files in <code>sites/</code>, start Docker Compose and start building.</sub>
</p>
