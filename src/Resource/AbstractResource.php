<?php

namespace Graph\Resource;

use Graph\Graph;
use ArrayAccess;
use RuntimeException;

abstract class AbstractResource implements ResourceInterface
{
    protected $name;
    protected $description;
    protected $graph;
    protected $typeName;
    protected $spec = [];
    protected $metadata = [];

    protected function __construct(Graph $graph)
    {
        $this->graph = $graph;
    }
    
    public static function fromConfig(Graph $graph, array $config)
    {
        $resource = new static($graph);
        if (!isset($config['metadata'])) {
            throw new RuntimeException("Metadata missing on resource");
        }

        $metadata = $config['metadata'];
        if (!isset($metadata['name'])) {
            throw new RuntimeException("Name missing on resource");
        }
        $resource->metadata = $metadata;
        $resource->name = $metadata['name'];

        if (!isset($config['kind'])) {
            throw new RuntimeException("Kind missing. " . $resource->name);
        }
        $resource->typeName = $config['kind'];


        $resource->description = $config['metadata']['description'] ?? null;
        if (!isset($config['spec'])) {
            throw new RuntimeException("Spec missing. " . $resource->name);
        }
        $resource->spec = $config['spec'] ?? [];
        return $resource;
    }

    public function getTypeName()
    {
        return $this->typeName;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getSpec()
    {
        return $this->spec;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

    public function offsetSet($offset, $value) {
        throw new RuntimeException("Read only array access");
    }

    public function offsetUnset($offset) {
        throw new RuntimeException("Read only array access");
    }

    public function offsetExists($offset) {
        return true;
    }

    public function offsetGet($offset) {
        $offset = $this->graph->inflector->camelize($offset);
        $method = 'get' . ucfirst($offset);
        return $this->{$method}();
    }

    public function serialize()
    {
        $data = [
            'kind' => $this->getTypeName(),
            'metadata' => $this->getMetadata(),
            'spec' => $this->spec
        ];
        return $data;
    }

    public function getLabels()
    {
        $data = [];
        foreach ($this->getMetadata()['labels'] as $k=>$v) {
            $data[] = [
                'key' => $k,
                'value' => $v,
            ];
        }
        return $data;
    }
}