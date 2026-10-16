# v0.2 package migration and backwards compatibility

This PR stages the package extraction **without changing the original v0.2 PR**.

- Canonical fields/forms now live in `lavertia/forms`.
- Canonical table engine, filters and column definitions live in `lavertia/tables`.
- Panels retains all eight existing `Lavertia\Panels\Components\*` class names as PHP `class_alias` shims. The wire protocol `type`, `key`, `props` and `components` shape does not change.
- Composer adds explicit `lavertia/forms:^0.2` and `lavertia/tables:^0.2` dependencies.
- These packages are **private**, and a local path repository version of `0.2.0` is only a development/testing alias, **not a release tag**.

For a monorepo-style **local development workspace** with sibling `support/`, `forms/`, `tables/`, `panels/`:

```bash
cd panels
composer config repositories.lavertia-support '{"type":"path","url":"../support","options":{"symlink":true,"versions":{"lavertia/support":"0.1.0"}}}'
composer config repositories.lavertia-forms '{"type":"path","url":"../forms","options":{"symlink":true,"versions":{"lavertia/forms":"0.2.0"}}}'
composer config repositories.lavertia-tables '{"type":"path","url":"../tables","options":{"symlink":true,"versions":{"lavertia/tables":"0.2.0"}}}'
composer install
composer test
composer lint
```

`LAVERTIA_READ_TOKEN` must read **Support, Forms and Tables** from Panels Actions while these repositories remain private. The integration PR must remain draft until PHPUnit/Composer package autoload is executed successfully on PHP 8.3 and 8.4, as well as consumer integration in Playground React/Vue/Svelte.

### Migration strategy

1. Test canonical packages and alias adapters in this integration branch.
2. Merge Forms and Tables package PRs after green PHP tests.
3. Merge stacked Panels #4, #5 and #6 in order, then the package integration PR into main.
4. Regenerate consumer `composer.lock` files with `composer update lavertia/* --with-all-dependencies` and verify Laravel apps; **do not manually edit Composer lockfiles**.
5. Tag release versions only after no mandatory PHP/Laravel job is skipped.
