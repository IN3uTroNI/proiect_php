<?php
declare(strict_types=1);

class Translator
{
    private array $translations = [];
    private string $language;

    public function __construct(string $language = 'en')
    {
        $this->language = $this->validateLanguage($language);
        $this->loadLanguage($this->language);
    }

    private function validateLanguage(string $lang): string
    {
        $supported = ['en', 'es', 'fr'];
        return in_array($lang, $supported) ? $lang : 'en';
    }

    private function loadLanguage(string $lang): void
    {
        $file = __DIR__ . '/../translations/' . $lang . '.json';
        if (file_exists($file)) {
            $json = file_get_contents($file);
            $this->translations = json_decode($json, true) ?? [];
        }
    }

    public function t(string $key, string $default = ''): string
    {
        return htmlspecialchars($this->translations[$key] ?? $default, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $lang): void
    {
        $this->language = $this->validateLanguage($lang);
        $this->loadLanguage($this->language);
    }

    public function getSupportedLanguages(): array
    {
        return ['en' => 'English', 'es' => 'Español', 'fr' => 'Français'];
    }
}
