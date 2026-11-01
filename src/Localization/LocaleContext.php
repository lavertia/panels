<?php

declare(strict_types=1);

namespace Lavertia\Panels\Localization;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Lavertia\Panels\Pages\Page;
use Lavertia\Panels\PanelRegistry;

final class LocaleContext
{
    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    public static function validate(array $codes): array
    {
        if (! array_is_list($codes) || $codes === [] || count($codes) > 200) {
            throw new InvalidArgumentException('Locales must be a non-empty list of at most 200 language codes.');
        }

        $normalized = [];
        $seen = [];

        foreach ($codes as $code) {
            if (! is_string($code) || preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/D', $code) !== 1) {
                throw new InvalidArgumentException('Unsupported locale code format.');
            }

            if (isset($seen[strtolower($code)])) {
                throw new InvalidArgumentException('Duplicate locale code.');
            }

            $normalized[] = $code;
            $seen[strtolower($code)] = true;
        }

        return $normalized;
    }

    /** @return list<string> */
    public static function allowed(PanelRegistry $registry, string $panelId, ?string $pageClass = null): array
    {
        $options = $registry->panel($panelId)?->configuration() ?? [];
        $codes = $options['locales'] ?? [];

        if ($codes === []) {
            $codes = [(string) ($options['default_locale'] ?? config('app.locale', 'en'))];
        }

        $codes = self::validate($codes);

        if ($pageClass !== null && is_subclass_of($pageClass, Page::class)) {
            $pageCodes = $pageClass::locales();

            if ($pageCodes !== null) {
                $pageCodes = self::validate($pageCodes);
                $codes = array_values(array_intersect($codes, $pageCodes));

                if ($codes === []) {
                    throw new InvalidArgumentException('Page locales must overlap with the Panel locales.');
                }
            }
        }

        return $codes;
    }

    public static function pageClass(Request $request): ?string
    {
        $class = $request->route()?->getActionName();

        return is_string($class) && is_subclass_of($class, Page::class) ? $class : null;
    }

    /**
     * @return array{locale: string, locales: list<string>, direction: string}
     */
    public static function resolve(Request $request, PanelRegistry $registry, string $panelId, bool $persist = false): array
    {
        $available = self::allowed($registry, $panelId, self::pageClass($request));
        $configDefault = $registry->panel($panelId)?->configuration()['default_locale'] ?? null;
        $default = in_array($configDefault, $available, true)
            ? $configDefault
            : (in_array(config('app.locale'), $available, true) ? config('app.locale') : $available[0]);

        $sessionKey = 'lavertia_locales.'.$panelId;
        $candidate = count($available) > 1 ? $request->query('lang') : null;

        if (is_string($candidate) && in_array($candidate, $available, true)) {
            if ($persist && $request->hasSession()) {
                $request->session()->put($sessionKey, $candidate);
            }
        } else {
            $candidate = $request->hasSession() ? $request->session()->get($sessionKey) : null;
        }

        // A Page-specific subset must not modify the user's Panel-wide preference.
        $locale = is_string($candidate) && in_array($candidate, $available, true) ? $candidate : $default;

        return [
            'locale' => $locale,
            'locales' => $available,
            'direction' => self::direction($locale),
        ];
    }

    public static function direction(string $locale): string
    {
        $language = strtolower(explode('-', $locale)[0]);

        return in_array($language, ['ar', 'fa', 'he', 'ur', 'ps', 'sd', 'ug', 'dv'], true)
            ? 'rtl'
            : 'ltr';
    }
}
