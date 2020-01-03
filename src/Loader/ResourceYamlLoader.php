<?php

namespace Graph\Loader;

use Graph\Graph;
use Graph\Resource\ResourceInterface;
use Symfony\Component\Yaml\Yaml;
use RuntimeException;

class ResourceYamlLoader
{
    private function rglob($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = array_merge($files, $this->rglob($dir . '/' . basename($pattern), $flags));
        }

        return $files;
    }

    public function loadPath(Graph $graph, string $path)
    {
        if (!is_dir($path)) {
            throw new RuntimeException("Path is not a directory: " . $path);
        }
        // === Load resources ===
        $filenames = $this->rglob($path . '/resources/*.yaml');
        foreach ($filenames as $filename) {
            if (basename($filename)[0] != '_') { // allow to quickly disable a configuration by prefixing it with an underscore
                $this->loadResourceFile($graph, $filename);
            }
        }

        return true;
    }

    public function loadResourceFile(Graph $graph, string $filename): void
    {
        if (!file_exists($filename)) {
            throw new Exception\FileNotFoundException($filename);
        }
        $yaml = file_get_contents($filename);

        $documents = explode("\n---\n", $yaml);

        foreach ($documents as $yaml) {
            if (trim($yaml, " \n\r")) {
                $config = Yaml::parse($yaml);
                $this->loadResourceConfig($graph, $config);
            }
        }
    }

    public function loadResourceConfig(Graph $graph, array $config): void
    {
        $kind = $config['kind'];
        $className = $graph->getTypeClass($kind);
        $resource = $className::fromConfig($graph, $config);
        if (!$resource) {
            throw new RuntimeException("fromConfig did not return a resource");
        }
        $graph->addResource($resource);
    }
}