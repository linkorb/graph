<?php

namespace Graph;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Graph\Resource\ResourceInterface;
use Graph\Exception;
use Doctrine\Common\Inflector\Inflector;
use RuntimeException;

class Graph
{
    protected $types = [];
    protected $typeClassMap = [];
    protected $resources = [];
    protected $schema;
    protected $container;

    public function __construct()
    {
        $this->inflector = new Inflector();
    }

    public function init($container)
    {
        $this->container = $container;
        $this->schema = new Schema([
            'query' => $this->getType('Query'),
        ]);
    }

    public function getInflector()
    {
        return $this->inflector;
    }

    public function getContainer()
    {
        return $this->container;
    }
    public function getSchema()
    {
        return $this->schema;
    }

    public function registerType(string $className): void
    {
        $name = $this->getTypeName($className);
        $this->typeClassMap[$name] = $className;
    }

    public function getTypeName(string $className): string
    {
        $name = (new \ReflectionClass($className))->getShortName();
        $name = str_replace('Resource', '', $name);

        return $name;
    }

    public function getTypeNames(): array
    {
        $res = [];
        foreach ($this->typeClassMap as $key => $value) {
            $res[] = $key;
        }

        return $res;
    }

    public function getType($name): ObjectType
    {
        if (!isset($this->types[$name])) {
            if (!isset($this->typeClassMap[$name])) {
                throw new Exception\UnknownResourceTypeException($name);
            }
            $className = $this->typeClassMap[$name];
            $config = $className::getConfig($this);
            $obj = new ObjectType($config);
            $this->types[$name] = $obj;
        }

        return $this->types[$name];
    }

    public function hasType($name): bool
    {
        return isset($this->typeClassMap[$name]);
    }

    public function getTypeClass($name): string
    {
        if (!$this->hasType($name)) {
            throw new Exception\UnknownResourceTypeException($name);
        }

        return $this->typeClassMap[$name];
    }

    public function getCapitals($str)
    {
        if (preg_match_all('#([A-Z]+)#', $str, $matches)) {
            return implode('', $matches[1]);
        } else {
            return false;
        }
    }

    public function getTypeAliases($typeName)
    {
        $capitals = $this->getCapitals($typeName);
        $res = [
            $capitals,
            strtolower($capitals),
            $typeName,
            lcfirst($typeName),
            $this->inflector->pluralize($typeName),
            lcfirst($this->inflector->pluralize($typeName)),
        ];

        return $res;
    }

    public function getCanonicalTypeName($name)
    {
        foreach ($this->getTypeNames() as $typeName) {
            $aliases = $this->getTypeAliases($typeName);
            if (in_array($name, $aliases)) {
                return $typeName;
            }
        }

        return null;
    }

    public function getResourcesByType(string $typeName): array
    {
        return $this->resources[$typeName] ?? [];
    }

    public function getResource(string $typeName, string $name): ?ResourceInterface
    {
        if (!$this->hasResource($typeName, $name)) {
            throw new Exception\UnknownResourceException("$typeName/$name");
        }
        $typeResources = $this->getResourcesByType($typeName);

        return $typeResources[$name] ?? null;
    }

    public function hasResource(string $typeName, string $name): bool
    {
        $typeResources = $this->getResourcesByType($typeName);

        return isset($typeResources[$name]);
    }

    public function addResource(ResourceInterface $resource): void
    {
        if (!isset($this->typeClassMap[$resource->getTypeName()])) {
            throw new Exception\UnknownResourceTypeException($resource->getTypeName());
        }
        $this->resources[$resource->getTypeName()][$resource->getName()] = $resource;
    }

    // public function getResources(): 
    // {
    //     return $this->resources;
    // }

    public function getGraphQlTypeFieldConfig()
    {
        $graph = $this;
        $fieldConfig = [];

        foreach ($graph->getTypeNames() as $typeName) {
            $fieldConfig[lcfirst($typeName)] = [
                'type'        => $graph->getType($typeName),
                'description' => 'Returns ' . $typeName . ' by name',
                'args'        => [
                    'name' => Type::nonNull(Type::string()),
                ],
                'resolve'     => function ($root, $args) use ($graph, $typeName) {
                    $resource = $graph->getResource($typeName, $args['name']);

                    return $resource;
                },
            ];
            $fieldConfig['all' . $graph->getInflector()->pluralize($typeName)] = [
                'type'        => Type::listOf($graph->getType($typeName)),
                'description' => 'Returns all ' . $graph->getInflector()->pluralize($typeName),
                'resolve'     => function ($root, $args) use ($graph, $typeName) {
                    $resources = $graph->getResourcesByType($typeName);

                    return $resources;
                },
            ];
        }

        return $fieldConfig;
    }
}
