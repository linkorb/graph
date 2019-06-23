<?php

namespace Graph\Command;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Graph\Resource\ResourceInterface;
use Graph\Graph;

class GetCommand extends Command
{
    protected $highlighter;
    protected $graph;

    public function __construct(Graph $graph, $highlighter)
    {
        $this->graph = $graph;
        $this->highlighter = $highlighter;;
        parent::__construct();
    }
    
    public function configure()
    {
        $this->setName('get')
            ->setDescription('Get type index or instance')
            ->addArgument(
                'typeName',
                InputArgument::OPTIONAL,
                'Type name'
            )
            ->addArgument(
                'resourceName',
                InputArgument::OPTIONAL,
                'Resource Name'
            )
            ->addArgument(
                'propertyName',
                InputArgument::OPTIONAL,
                'Property Name'
            )
        ;
    }

    public function writeYaml(OutputInterface $output, $yaml)
    {
        $output->write($this->highlighter->highlight($yaml, 'yaml'));
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $typeName = $input->getArgument('typeName');
        $resourceName = $input->getArgument('resourceName');
        $propertyName = $input->getArgument('propertyName');
        if (!$typeName) {
            foreach($this->graph->getTypeNames() as $typeName) {
                $aliases = $this->graph->getTypeAliases($typeName);
                $output->writeLn(' * <info>' . $typeName . '</info> (' . implode(', ', $aliases) . ')');
                // print_r($aliases);
            }
            return;
        }
        
        $typeName = $this->graph->getCanonicalTypeName($typeName);
        if (!$typeName) {
            exit("Unknown type: " . $typeName . PHP_EOL);
        }
        $resources = $this->graph->getResourcesByType($typeName);

        if (!$resourceName) {
            foreach ($resources as $resource) {
                $output->writeLn(" * <info>" . $resource->getName() . '</info>');
            }
            return;
        }
        $resource = $this->graph->getResource($typeName, $resourceName);
        if (!$propertyName) {
            $yaml = Yaml::dump($resource->serialize(), 10, 2);
            $this->writeYaml($output, $yaml);
            return;
        }

        $value = $resource[$propertyName];
        if (is_string($value) || is_numeric($value)) {
            $output->writeLn($value);
            return;
        }
        if (is_array($value)) {
            foreach ($value as $k=>$v) {
                if (is_string($v)) {
                    $output->writeLn($v);
                } else {
                    if (is_a($v, ResourceInterface::class)) {
                        $output->writeLn($v->getName());
                    }
                }
            }
            return;
        }
        exit("Unsupported property type...\n");
    }
}
