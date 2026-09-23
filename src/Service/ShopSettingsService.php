<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ShopSettingsService
{
    private string $filePath;

    public const DEFAULTS = [
        'phone' => '+261 34 00 000 00',
        'whatsapp' => '+261 34 00 000 00',
        'email' => 'hello@teako.mg',
        'address' => 'Lot IBF 9 Antsahavola, 6 Rue Elysée Ravelotsalama — Antananarivo',
        'hours' => 'Tous les jours · 8h00 – 20h00',
        'banner_text' => 'TEAKO GOURMET DRINKS · RETRAIT AU BAR — LOT IBF 9 ANTSAHAVOLA · LIVRAISON ANTANANARIVO',
        'delivery_zone' => 'Antananarivo',
        'prep_time' => '10-15 min',
        'instagram' => 'https://instagram.com/teako.mg',
        'facebook' => 'https://facebook.com/teako.mg',
        'tiktok' => '',
        'is_open' => true,
        'announcement' => '',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ) {
        $this->filePath = $projectDir . '/var/settings.json';
    }

    /** @return array<string, mixed> */
    public function getSettings(): array
    {
        if (!file_exists($this->filePath)) {
            return self::DEFAULTS;
        }

        $content = (string) file_get_contents($this->filePath);
        $data = json_decode($content, true);

        return array_merge(self::DEFAULTS, is_array($data) ? $data : []);
    }

    /** @param array<string, mixed> $data */
    public function saveSettings(array $data): void
    {
        $current = $this->getSettings();
        $merged = array_merge($current, $data);
        file_put_contents($this->filePath, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}