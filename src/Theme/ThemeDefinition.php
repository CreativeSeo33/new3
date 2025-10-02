<?php

namespace App\Theme;

final class ThemeDefinition
{
    public function __construct(
        private readonly string $code,
        private readonly string $name,
        private readonly string $path,
        private readonly bool $enabled = true,
        private readonly ?string $parent = null,
        private readonly array $metadata = [],
        private readonly array $features = [],
        private readonly array $parameters = [],
    ) {}

    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getPath(): string { return $this->path; }
    public function isEnabled(): bool { return $this->enabled; }
    public function getParent(): ?string { return $this->parent; }

    public function getTemplatePath(): string { return $this->path . '/templates'; }
    public function getAssetPath(): string { return $this->path . '/assets'; }
    public function getPublicPath(): string { return 'themes/' . $this->code; }

    public function getMetadata(string $key = null): mixed
    { return $key ? ($this->metadata[$key] ?? null) : $this->metadata; }

    public function hasFeature(string $feature): bool
    { return (bool)($this->features[$feature] ?? false); }

    public function getParameter(string $key, mixed $default = null): mixed
    { return $this->parameters[$key] ?? $default; }
}



