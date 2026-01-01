# Hosting Ava

This guide walks you through getting Ava live on the internet. Whether you're hosting your first website ever or you're a seasoned developer, there's an option that fits your needs and budget.

## Before You Start

Ava needs two things:

1. **PHP 8.3 or later**
2. **Composer** (PHP's package manager)

That's it. No database, no special server software, no complex stack to configure.

<div class="beginner-box">

### What's Composer?

[Composer](https://getcomposer.org/) manages PHP dependencies (the libraries Ava uses). Most hosts have it pre-installed. You only need to run `composer install` once after uploading Ava—it downloads everything into the `vendor/` folder.

</div>

---

## Local Development (Optional)

You don't need a full web server to work on your Ava site locally. PHP includes a built-in development server that's perfect for previewing changes on your own computer.

<div class="beginner-box">

### What is "Local Development"?

It means running your website on your own computer instead of a server on the internet. You can test changes privately before making them public.

**Important:** The built-in PHP server is for development only—it's not designed for public traffic. For a live website, you'll need real hosting (keep reading!).

</div>

### Running Ava Locally

First, check you have PHP installed:

```bash
php -v
```

If you see a version number (8.3 or higher), you're good. If not:

| Platform | How to Install PHP |
|----------|-------------------|
| **macOS** | `brew install php` (requires [Homebrew](https://brew.sh)) |
| **Windows** | Download from [windows.php.net](https://windows.php.net/download) or use [XAMPP](https://www.apachefriends.org/) |
| **Linux** | `sudo apt install php` (Debian/Ubuntu) or your distro's package manager |

Once PHP is installed, navigate to your Ava folder and start the server:

```bash
cd /path/to/your/ava-site
php -S localhost:8000 -t public
```

Open `http://localhost:8000` in your browser. You're running Ava!

**Tip:** You don't need Apache, Nginx, or LAMP/MAMP/WAMP for local development. The built-in server handles everything.

---

## Shared Hosting

Shared hosting is the easiest and most affordable way to get Ava live. You get some of your own protected space on a server, but the hosting company handles all the technical stuff. This is a very common way for small to medium-sized websites to get online.

### What You Get

- **PHP pre-installed** — Usually just works
- **Control panel** — Manage files, domains, and settings through a web interface
- **File manager** — Upload files without extra software
- **One-click SSL** — Free HTTPS certificates (Let's Encrypt)

### What You Need

- **SSH access** — Essential for running Ava's basic CLI commands (most good hosts include this, you may have to ask for it to be enabled)
- **PHP 8.3+** — Check before signing up

### Recommended Providers

| Provider | Starting Price | Notes |
|----------|----------------|-------|
| [Krystal Hosting](https://krystal.uk/web-hosting) | From £7/month | Premium UK host, vastly scalable without moving hosts, excellent support, 100% renewable energy |
| [Porkbun Easy PHP](https://porkbun.com/products/webhosting/managedPHP) | From $10/month (with discounts often available) | Simple, great domain provider, good for getting started in one place |

Both include SSH access and modern PHP versions.

Had a good experience with Ava on another host? Let us know in the [Discord community](https://discord.gg/Z7bF9YeK) so we can update this list!

### File Structure on Shared Hosting

Shared hosts typically give you a structure like this:

```
/home/yourusername/
├── public_html/          ← Your "web root" (publicly accessible)
│   └── index.php         ← Ava's entry point goes here
│
├── ava/                  ← Put Ava here (ABOVE public_html)
│   ├── app/
│   ├── content/
│   ├── core/
│   ├── themes/
│   ├── storage/
│   └── ...
│
└── logs/                 ← Server logs (usually auto-created)
```

**Key insight:** Only the `public/` folder contents should be web-accessible. Everything else stays above the web root for security—this means your config files, content, and storage are never directly downloadable by visitors.

### Setting Up on Shared Hosting

1. **Upload Ava** to a folder *above* your web root (e.g., `/home/you/ava/`)
2. **Move or symlink** the contents of `public/` into your web root (`public_html/`)
3. **Update paths** in `public/index.php` to point to your Ava installation
4. **Connect via SSH** and run `./ava rebuild` (see [CLI Guide](cli.md) if you're new to command line)

Alternatively, if your host lets you change the document root, point it directly at Ava's `public/` folder—cleaner and easier.

---

## SSH: Your New Superpower

SSH (Secure Shell) lets you run commands on your server as if you were sitting in front of it. You'll need it to run Ava's CLI tools.

<div class="beginner-box">

### Don't Be Scared of the Terminal!

SSH looks intimidating at first—a black screen with blinking cursor. But it's just typing commands instead of clicking buttons. Once you get the hang of it, you'll wonder how you lived without it.

**You only need a few commands:**
- `cd folder-name` — Go into a folder
- `ls` — See what's in the current folder
- `./ava status` — Check if Ava is happy

That's honestly 90% of what you'll do. Even if you've never used a command line before, this is a perfect place to start—Ava's commands are friendly and helpful.

**Want the full reference?** See the [CLI Guide](cli.md) for all available commands and more terminal basics.

</div>

### Connecting via SSH

```bash
ssh username@your-domain.com
```

Replace `username` with your hosting account username and `your-domain.com` with your server address (your host will tell you both).

The first time you connect, you'll see a message about the server's fingerprint. Type `yes` to continue. Then enter your password.

**Tip:** Most hosts show SSH connection details in your control panel under "SSH Access" or similar.

### SSH Clients

| Platform | Options |
|----------|---------|
| **macOS** | Built-in Terminal app (just works) |
| **Linux** | Built-in terminal (just works) |
| **Windows** | Windows Terminal, PowerShell, or [PuTTY](https://www.putty.org/) |

### Using Ava Over SSH

Once connected, navigate to your Ava installation:

```bash
cd ~/ava
./ava status
```

You'll see something like this:

<pre><samp><span class="t-magenta">   ▄▄▄  ▄▄ ▄▄  ▄▄▄     ▄▄▄▄ ▄▄   ▄▄  ▄▄▄▄
  ██▀██ ██▄██ ██▀██   ██▀▀▀ ██▀▄▀██ ███▄▄
  ██▀██  ▀█▀  ██▀██   ▀████ ██   ██ ▄▄██▀</span>

  <span class="t-dim">───</span> <span class="t-bold">Site Status</span> <span class="t-dim">─────────────────────────────────────</span>

  <span class="t-cyan">▸ PHP Version</span>         8.3.14
  <span class="t-cyan">▸ Content Index</span>       Fresh (rebuilt 2 mins ago)
  <span class="t-cyan">▸ Content Items</span>       47 published, 3 drafts
  <span class="t-cyan">▸ Page Cache</span>          Enabled (23 cached pages)

  <span class="t-green">✓ Everything looks good!</span></samp></pre>

Pretty cool, right?

If you're new to command line interfaces, don't worry—Ava's CLI is designed to be beginner-friendly. The [CLI Reference](cli.md) has more commands and tips for getting comfortable with the terminal.

### Checking if Your Host Supports SSH

Look in your hosting control panel for:
- "SSH Access" or "Shell Access"
- "Terminal" or "Command Line"
- A section about "SSH Keys"

If you can't find it, ask support: "Do you offer SSH access?" Most quality hosts do.

### Troubleshooting: "composer: command not found"

If you get this error, Composer isn't installed on your server. Most quality shared hosts include Composer, but if yours doesn't:

**Option 1: Install Composer locally** (recommended for shared hosting)

```bash
# Download Composer to your Ava folder
curl -sS https://getcomposer.org/installer | php

# Now use it with php composer.phar instead of composer
php composer.phar install
```

This installs Composer just for your project—no server-wide installation needed.

**Option 2: Ask your host**

Contact support and ask: "Can you enable Composer for my account?" Many hosts can enable it on request.

**Option 3: Install dependencies locally**

Run `composer install` on your local computer, then upload the entire `vendor/` folder along with your Ava files. This works but makes updates slightly more manual.

<div class="beginner-box">

### Stuck?

Join the [Discord community](https://discord.gg/Z7bF9YeK)—we're happy to help you get set up, even if you're brand new to all this!

</div>

---

## VPS Hosting (Level Up)

A VPS (Virtual Private Server) gives you your own slice of a server. More control, more power, but more responsibility.

### When to Consider a VPS

- Your site is getting a lot of traffic that shared hosting can't handle
- You want to host multiple sites with different configurations
- You need specific PHP extensions or configurations for advanced functionality
- You just want to learn more about servers (this is a great way!)

### What You Get

- **Root access** — Full control over the server
- **Dedicated resources** — CPU and RAM just for you
- **Any software** — Install whatever you need

### What You'll Need to Learn

- Basic server administration (or use a management tool—see below)
- How to secure a server
- How to set up a web server (Nginx or Apache)

### Recommended VPS Providers

| Provider | Starting Price | Notes |
|----------|----------------|-------|
| [Hetzner Cloud](https://www.hetzner.com/cloud) | From €4/month | Excellent value, EU-based, great performance |
| [Krystal Cloud VPS](https://krystal.io/cloud-vps) | From £10/month | UK-based, renewable energy, managed options |

### Making VPS Easy: Server Management Panels

If managing a server sounds daunting, use a management tool:

**[Ploi.io](https://ploi.io/)** — Connects to your VPS and handles all the server setup for you. Deploy sites, manage SSL, run commands—all through a friendly dashboard. Perfect for developers who want VPS power without the sysadmin work.

With Ploi, setting up Ava on a VPS is almost as easy as shared hosting.

---

## Scaling Up (Optional)

Ava is already fast—cached pages serve in ~0.1ms. But if you're getting serious traffic or want extra resilience, here are some options.

### Free CDN with Cloudflare

[Cloudflare](https://www.cloudflare.com/) sits between your visitors and your server, caching static files at data centers worldwide.

**Benefits:**
- Faster load times for visitors far from your server
- DDoS protection
- Free SSL certificate
- Analytics

**Setup:** Point your domain's nameservers to Cloudflare, configure caching rules, done. The free tier is plenty for most sites.

### Other CDNs

- **BunnyCDN** — Pay-as-you-go, very affordable
- **KeyCDN** — Simple, developer-friendly
- **Fastly** — More advanced, used by large sites

### Do You Need This?

Ava's built-in page cache is already blazing fast. A CDN helps most when:
- You're serving lots of large images or files
- Your audience is globally distributed
- You want DDoS protection

Start simple. Add complexity only when you need it.

---

## Deployment Workflows

How you get files from your computer to your server is up to you. Here are common approaches:

### Manual Upload (SFTP)

Use an SFTP client to drag and drop files.

**Good for:** Quick changes, beginners, occasional updates

**Tools:** FileZilla, Cyberduck, WinSCP, Transmit

### Git-Based Deployment

Push to a Git repository, then pull on your server.

```bash
# On your server
cd ~/ava
git pull origin main
./ava rebuild
```

**Good for:** Version control, team collaboration, rollback capability

### Automated Deployment

Services like Ploi, Forge, or GitHub Actions can automatically deploy when you push code.

**Good for:** Frequent updates, CI/CD workflows, hands-off deployment

---

## Quick Checklist

Before going live, make sure:

- [ ] PHP 8.3+ is running (`php -v`)
- [ ] Required extensions are enabled (`./ava status` will tell you)
- [ ] Content index is built (`./ava rebuild`)
- [ ] Your domain points to the right folder
- [ ] **HTTPS is enabled** (free with Let's Encrypt—required for admin access)
- [ ] Page cache is enabled in config (for performance)
- [ ] Debug mode is disabled (`display_errors => false`)

---

## Need Help?

- [CLI Reference](cli.md) — All the commands you can run
- [Configuration](configuration.md) — Site settings and options
- [Discord Community](https://discord.gg/Z7bF9YeK) — Ask questions, get help

Remember: everyone starts somewhere. The Ava community is friendly and happy to help you get your site live.
