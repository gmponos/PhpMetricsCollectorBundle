<?php

namespace App\Collector;

use App\Metric\FinalVisitor;
use Exception;
use Hal\Metric\Class_\ClassEnumVisitor;
use Hal\Metric\Class_\Complexity\CyclomaticComplexityVisitor;
use Hal\Metric\Class_\Complexity\KanDefectVisitor;
use Hal\Metric\Class_\Component\MaintainabilityIndexVisitor;
use Hal\Metric\Class_\Coupling\ExternalsVisitor;
use Hal\Metric\Class_\Structural\LcomVisitor;
use Hal\Metric\Class_\Structural\SystemComplexityVisitor;
use Hal\Metric\Class_\Text\HalsteadVisitor;
use Hal\Metric\Class_\Text\LengthVisitor;
use Hal\Metric\ClassMetric;
use Hal\Metric\Consolidated;
use Hal\Metric\Metrics;
use Hal\Metric\Package\PackageCollectingVisitor;
use Hal\Violation\ViolationParser;
use PhpParser\Lexer\Emulative;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser\Php7;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

class PhpMetricsCollector extends DataCollector implements LateDataCollectorInterface
{
    /**
     * @var string
     */
    private $path;

    public function __construct(string $path)
    {
        $this->path = $path . '/src';
    }

    public function collect(Request $request, Response $response, Exception $exception = null)
    {
    }

    public function getAverage()
    {
        return $this->data['consolidated']->getAvg();
    }

    public function getSum()
    {
        return $this->data['consolidated']->getSum();
    }

    public function getMetrics()
    {
        return $this->data['metrics'];
    }

    public function getClassMetrics()
    {
        return array_filter($this->data['metrics']->all(), function ($el) {
            return $el instanceof ClassMetric;
        });
    }

    public function getName()
    {
        return 'phpmetrics_collector';
    }

    public function reset()
    {
        // TODO: Implement reset() method.
    }

    /**
     * Collects data as late as possible.
     */
    public function lateCollect()
    {
        $finder = new Finder();
        $files = $finder->files()->in($this->path)->name('*.php');

        $metrics = new Metrics();
        $metrics->all();

        $parser = new Php7(new Emulative());

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor(new ClassEnumVisitor($metrics));
        $traverser->addVisitor(new FinalVisitor($metrics));
        $traverser->addVisitor(new CyclomaticComplexityVisitor($metrics));
        $traverser->addVisitor(new ExternalsVisitor($metrics));
        $traverser->addVisitor(new LcomVisitor($metrics));
        $traverser->addVisitor(new HalsteadVisitor($metrics));
        $traverser->addVisitor(new LengthVisitor($metrics));
        $traverser->addVisitor(new CyclomaticComplexityVisitor($metrics));
        $traverser->addVisitor(new MaintainabilityIndexVisitor($metrics));
        $traverser->addVisitor(new KanDefectVisitor($metrics));
        $traverser->addVisitor(new SystemComplexityVisitor($metrics));
        $traverser->addVisitor(new PackageCollectingVisitor($metrics));

        foreach ($files as $file) {
            $code = file_get_contents($file);
            $stmts = $parser->parse($code);
            $traverser->traverse($stmts);
        }
        // violations
        (new ViolationParser())->apply($metrics);

        $this->data['consolidated'] = new Consolidated($metrics);
        $this->data['metrics'] = $metrics;
        $classMetrics = $this->getClassMetrics();
        $finals = array_filter($classMetrics, function (ClassMetric $classMetric) {
            return $classMetric->get('final') === true;
        });

        $this->data['final'] = count($finals) . "/" . count($classMetrics);
    }

    public function getFinal()
    {
        return $this->data['final'];
    }
}