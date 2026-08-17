# Future Revisions

WordPress feature plugin for **public historical revisions** and **future-revision fork/merge**.

Pew Research Center working copy: [pewresearch/wp-feature-future-revisions](https://github.com/pewresearch/wp-feature-future-revisions). Plugin URI still points at the intended WordPress.org home.

Also vendored in [prc-platform](https://github.com/pewresearch/prc-platform) at `plugins/wp-feature-future-revisions/`.

## Features

Two independent `post_type_supports` keys:

- `public-revisions` — flag a revision public and serve it at `/post-slug/revision/{id}/`
- `future-revisions` — fork a published post into a draft and merge it back on publish (keeps the original `post_date`)

`post` and `page` get both. Other types opt in with `add_post_type_support()`.

## Development

```bash
npm install
npm run build
```

PHPUnit lives in `tests/phpunit/` and runs against `wordpress-tests-lib` / `@wordpress/env` (see `.wp-env.json`).

`package-lock.json` is not in this repo yet (payload too large for the publish path). Run `npm install` from this folder to generate one locally.
