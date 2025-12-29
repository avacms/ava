# Ava CMS

Ava is a friendly, developer-first flat-file CMS for PHP. Content is Markdown files (with YAML frontmatter), and Ava builds a fast cache so pages render quickly without a database.

It’s designed for bespoke sites where you want full ownership: your content and config live in your repo, and themes are plain PHP/HTML.

## Quick Start

```bash
composer install

./ava rebuild

php -S localhost:8000 -t public
```

## Documentation

Main docs: https://ava.addy.zone/

Key sections:

- https://ava.addy.zone/#/configuration
- https://ava.addy.zone/#/content
- https://ava.addy.zone/#/themes
- https://ava.addy.zone/#/admin
- https://ava.addy.zone/#/cli
- https://ava.addy.zone/#/routing
- https://ava.addy.zone/#/shortcodes
- https://ava.addy.zone/#/bundled-plugins
- https://ava.addy.zone/#/updates

## Contributing

Ava is still fairly early and moving quickly, so I’m not looking for pull requests or additional contributors right now.

If you’d like to help, the best way is to [open an issue](https://github.com/adamgreenough/ava/issues) (bug reports, feature requests, ideas, and support questions are all welcome). It’s genuinely useful feedback at this stage.

Discord (discussion/support): https://discord.gg/Z7bF9YeK

## License

MIT (free and open source). See LICENSE.
