<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    public function test_public_pages_have_unique_metadata_and_sitemap_entries(): void
    {
        $titles = [];
        $descriptions = [];
        foreach (config('marketing.pages') as $path => [$title, $description]) {
            $response = $this->get($path)->assertOk()
                ->assertSee('rel="canonical"', false)
                ->assertSee('property="og:title"', false)
                ->assertSee('name="twitter:card"', false)
                ->assertSee('application/ld+json', false)
                ->assertSee('<h1>', false)
                ->assertSee($title.' | Utilynk Lists')
                ->assertSee($description);
            $html = $response->getContent();
            preg_match('/<title>(.*?)<\/title>/s', $html, $titleMatch);
            preg_match('/<meta name="description" content="([^"]+)"/', $html, $descriptionMatch);
            $titles[] = $titleMatch[1];
            $descriptions[] = $descriptionMatch[1];
        }
        $this->assertCount(count($titles), array_unique($titles));
        $this->assertCount(count($descriptions), array_unique($descriptions));
        $sitemap = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($sitemap->getContent());
        $this->assertCount(count($titles), $xml->url);
        $this->get('/robots.txt')->assertSee('/sitemap.xml');
    }

    public function test_offline_shell_and_manifest_are_available(): void
    {
        $this->get('/offline')->assertOk()->assertSee('data-screen="offline"', false);
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('/lists', $manifest['start_url']);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
        $this->assertFileExists(public_path('sw.js'));
    }
}
