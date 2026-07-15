<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;

final class ClassifierUrl
{
    public const OKPD2_PREFIX = 'okpd2-';

    public const TNVED_PREFIX = 'tnved-';

    public static function okpd2Slug(string $code): string
    {
        return self::OKPD2_PREFIX.$code;
    }

    public static function tnvedSlug(string $code): string
    {
        return self::TNVED_PREFIX.$code;
    }

    public static function okpd2PublicPath(?string $code = null): string
    {
        if ($code === null || $code === '') {
            return '/p/okpd2/';
        }

        return '/p/okpd2/'.self::okpd2Slug($code);
    }

    public static function tnvedPublicPath(?string $code = null): string
    {
        if ($code === null || $code === '') {
            return '/p/okpd2/tnved';
        }

        return '/p/okpd2/tnved/'.self::tnvedSlug($code);
    }

    public static function absolute(string $path): string
    {
        $appUrl = rtrim((string) config('app.url', 'https://avaks.online/okpd2'), '/');
        $origin = preg_replace('#/okpd2$#', '', $appUrl) ?: $appUrl;

        return rtrim($origin, '/').$path;
    }

    public static function redirectTo(string $path): RedirectResponse
    {
        return redirect()->away(self::absolute($path), 301);
    }
}
