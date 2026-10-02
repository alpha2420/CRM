<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * The public pages search engines should list. Everything else is behind
 * a login.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $pages = [route('home') => '1.0', route('register') => '0.6', route('legal', 'privacy') => '0.3', route('legal', 'terms') => '0.3'];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($pages as $url => $priority) {
            $xml .= '  <url><loc>'.e($url).'</loc><priority>'.$priority."</priority></url>\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
