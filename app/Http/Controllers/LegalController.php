<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Policy pages, written in Markdown under resources/markdown so they can be
 * edited without touching code.
 */
class LegalController extends Controller
{
    private const PAGES = ['privacy' => 'Privacy Policy', 'terms' => 'Terms of Service'];

    public function __invoke(string $page): View
    {
        abort_unless(array_key_exists($page, self::PAGES), 404);
        $path = resource_path("markdown/{$page}.md");

        $markdown = str_replace(
            ['{app}', '{support_email}'],
            [config('app.name'), config('crm.support_email') ?: '[support email]'],
            File::get($path),
        );

        return view('legal', [
            'title' => self::PAGES[$page],
            'html' => Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            'updated' => date('d F Y', File::lastModified($path)),
        ]);
    }
}
