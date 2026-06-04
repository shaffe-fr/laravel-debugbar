<?php

declare(strict_types=1);

namespace Fruitcake\LaravelDebugbar\DataCollector;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\ObjectCountCollector;

/**
 * Extends ObjectCountCollector to optionally track the source (caller) of each model event.
 * When source tracking is disabled, behaves identically to ObjectCountCollector.
 * When enabled, groups occurrences by the first non-vendor frame in the backtrace.
 */
class ModelsCollector extends ObjectCountCollector implements AssetProvider
{
    protected bool $findSource = false;

    /**
     * @var array<string, array<string, array{file: string, line: int, count: int}>>
     */
    protected array $classSources = [];

    /** @var string[] */
    protected array $backtraceExcludePaths = [
        '/vendor/',
    ];

    public function setFindSource(bool $enabled): void
    {
        $this->findSource = $enabled;
    }

    /**
     * @param string[] $paths
     */
    public function mergeBacktraceExcludePaths(array $paths): void
    {
        $this->backtraceExcludePaths = array_merge($this->backtraceExcludePaths, $paths);
    }

    public function countClass(string|object $class, int $count = 1, string $key = 'value'): void
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        parent::countClass($class, $count, $key);

        if ($this->findSource) {
            $source = $this->findSource();
            if ($source !== null) {
                $sourceKey = $source['label'];
                if (!isset($this->classSources[$class])) {
                    $this->classSources[$class] = [];
                }
                if (!isset($this->classSources[$class][$sourceKey])) {
                    $this->classSources[$class][$sourceKey] = [
                        'file' => $source['file'],
                        'line' => $source['line'],
                        'count' => 0,
                    ];
                }
                $this->classSources[$class][$sourceKey]['count'] += $count;
            }
        }
    }

    /**
     * Find the first non-vendor caller from the backtrace.
     *
     * @return array{file: string, line: int, label: string}|null
     */
    protected function findSource(): ?array
    {
        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, (int) app('config')->get('debugbar.debug_backtrace_limit', 50));

        foreach ($stack as $frame) {
            if (!isset($frame['file'])) {
                continue;
            }

            $file = str_replace('\\', '/', $frame['file']);

            if ($this->fileIsInExcludedPath($file)) {
                continue;
            }

            $line = $frame['line'] ?? 1;
            $label = $this->normalizeFilePath($frame['file']) . ':' . $line;

            return [
                'file' => $frame['file'],
                'line' => $line,
                'label' => $label,
            ];
        }

        return null;
    }

    protected function fileIsInExcludedPath(string $file): bool
    {
        foreach ($this->backtraceExcludePaths as $excludedPath) {
            if (str_contains($file, $excludedPath)) {
                return true;
            }
        }

        return false;
    }

    public function reset(): void
    {
        parent::reset();
        $this->classSources = [];
    }

    public function collect(): array
    {
        $data = parent::collect();

        if ($this->findSource && $this->classSources !== []) {
            foreach ($this->classSources as $class => $sources) {
                uasort($sources, fn(array $a, array $b) => $b['count'] <=> $a['count']);

                $collected = [];
                foreach ($sources as $label => $source) {
                    $entry = [
                        'label' => $label,
                        'count' => $source['count'],
                    ];

                    $link = $this->getXdebugLink($source['file'], $source['line']);
                    if ($link !== null) {
                        $entry['xdebug_link'] = $link;
                    }

                    $collected[] = $entry;
                }

                $data['data'][$class]['sources'] = $collected;
            }
        }

        return $data;
    }

    /**
     * When source tracking is disabled, use the default TableVariableListWidget.
     * When enabled, use the custom ModelsWidget with expandable sources.
     */
    public function getWidgets(): array
    {
        if (!$this->findSource) {
            return parent::getWidgets();
        }

        $name = $this->getName();

        return [
            "$name" => [
                'icon' => 'box',
                'widget' => 'PhpDebugBar.Widgets.ModelsWidget',
                'map' => "$name",
                'default' => '{}',
            ],
            "$name:badge" => [
                'map' => "$name.count",
                'default' => 0,
            ],
        ];
    }

    /**
     * Only load custom assets when source tracking is enabled.
     */
    public function getAssets(): array
    {
        if (!$this->findSource) {
            return [];
        }

        return [
            'js' => __DIR__ . '/../../resources/models/widget.js',
            'css' => __DIR__ . '/../../resources/models/widget.css',
        ];
    }
}
