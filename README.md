<p align="center">
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://ava.addy.zone/media/dark.png">
  <source media="(prefers-color-scheme: light)" srcset="https://ava.addy.zone/media/light.png">
  <img alt="Fallback image description" src="https://ava.addy.zone/media/light.png">
</picture>
</p>

<p align="center">
  <strong>A fast, flexible, file-based CMS.</strong><br>
</p>

<p align="center">
  <a href="https://github.com/ava-cms/ava/releases"><img src="https://img.shields.io/github/v/release/ava-cms/ava" alt="Release"></a>
  <a href="https://github.com/ava-cms/ava/issues"><img src="https://img.shields.io/github/issues/ava-cms/ava" alt="Issues"></a>
  <a href="https://github.com/ava-cms/ava"><img src="https://img.shields.io/github/languages/code-size/ava-cms/ava" alt="Code size"></a>
  <a href="https://discord.gg/fZwW4jBVh5"><img src="https://img.shields.io/discord/1028357262189801563" alt="Discord"></a>
  <a href="https://github.com/ava-cms/ava"><img src="https://img.shields.io/github/stars/ava-cms/ava" alt="GitHub Repo stars"></a>
</p>

---

# Ava CMS

Ava is a modern flat-file CMS built for developers and content creators who want simplicity without sacrificing power. Your content lives as **Markdown files** on disk. Your theme is just **PHP & HTML**. Your configuration is a simple array.

There is **no database** to manage, **no complex build pipeline** to configure, and **no vendor lock-in**. If you can write Markdown and edit files, you can build a site with Ava.

### ✨ Key Features

*   **📂 Flat-File Architecture**: Your content is portable, version-controllable, and human-readable.
*   **⚡ Blazing Fast**: Heavy caching layer ensures your site loads instantly.
*   **🔌 Zero-Database**: No MySQL, PostgreSQL, or SQLite connection to robustly manage.
*   **🛠️ Powerful CLI**: A friendly command-line tool for managing your site, clearing cache, and more.
*   **🎛️ Admin Dashboard**: Optional built-in admin panel for quick edits and content management on the go.
*   **🎨 Flexible Theming**: Use standard PHP templates. No new templating language to learn.
*   **🧩 Plugin System**: Extend functionality with hooks and bundled plugins (Sitemap, Redirects, etc.).

## 📸 Screenshots

![Ava CMS screenshots](https://ava.addy.zone/media/screenshots.png)

## 🛠️ Requirements

*   **PHP 8.3** or higher
*   **Composer**

That's it. Ava runs on almost any shared hosting, VPS, or local machine that supports modern PHP.

## 🏁 Quick Start

### 1. Installation

Clone the repository and install dependencies:

```bash
git clone https://github.com/ava-cms/ava.git my-site
cd my-site
composer install
```

### 2. Run Locally

Start the built-in PHP development server:

```bash
./ava start
# OR simply
php -S localhost:8000 -t public
```

Visit `http://localhost:8000` to see your new Ava site!

### 3. Create Content

Add a new page by creating a Markdown file in `content/pages/`:

**File:** `content/pages/hello.md`

```markdown
---
title: Hello World
slug: hello-world
status: published
---

# Welcome to Ava!

This is my first page. It's just a text file.
```

Visit `http://localhost:8000/hello-world` to see it live.

## 📚 Documentation

Detailed documentation is available at **[ava.addy.zone](https://ava.addy.zone/)**.

*   [**Getting Started**](https://ava.addy.zone/docs)
*   [**Configuration**](https://ava.addy.zone/docs/configuration)
*   [**Theming Guide**](https://ava.addy.zone/docs/theming)
*   [**CLI Reference**](https://ava.addy.zone/docs/cli)
*   [**Plugin Development**](https://ava.addy.zone/docs/creating-plugins)

## 🏗️ Project Structure

Here is what an Ava project looks like:

```text
my-site/
├── app/
│   └── config/          # Site configuration (ava.php, content types, etc.)
├── content/
│   ├── pages/           # Your Markdown content
│   └── ...
├── themes/              # PHP themes
├── plugins/             # Site plugins
├── public/              # Web root (assets, index.php)
├── storage/             # Cache and logs
├── vendor/              # Composer dependencies
└── ava                  # CLI tool
```

## 🤝 Contributing

Ava is still fairly early and moving quickly, so I'm not looking for undiscussed pull requests or additional contributors just yet.

That said, I'd genuinely love your feedback:

- If you run into a bug, get stuck, or have a "this could be nicer" moment, please [open an issue](https://github.com/ava-cms/ava/issues).
- Feature requests, ideas, and suggestions are very welcome.

If you prefer a more conversational place to ask questions and share ideas, join the [Discord community](https://discord.gg/fZwW4jBVh5).

## 📄 License

Ava is open-source software licensed under the [MIT license](LICENSE).
