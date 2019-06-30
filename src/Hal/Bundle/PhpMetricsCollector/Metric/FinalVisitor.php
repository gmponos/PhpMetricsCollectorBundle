<?php

namespace App\Metric;

use Hal\Metric\ClassMetric;
use Hal\Metric\Metrics;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

class FinalVisitor extends NodeVisitorAbstract
{
    /**
     * @var Metrics
     */
    private $metrics;

    public function __construct(Metrics $metrics)
    {
        $this->metrics = $metrics;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Stmt\Class_) {
            $name = (string)(isset($node->namespacedName) ? $node->namespacedName : 'anonymous@' . spl_object_hash($node));

            if ($this->metrics->has($name)) {
                $class = $this->metrics->get($name);
            } else {
                $class = new ClassMetric($name);
            }
            $class->set('final', $node->isFinal());

            $this->metrics->attach($class);
        }
    }
}
