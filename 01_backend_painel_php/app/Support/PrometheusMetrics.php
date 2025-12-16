<?php
declare(strict_types=1);

namespace RedeAlabama\Support;

/**
 * PrometheusMetrics
 *
 * Registrador simples de métricas em formato Prometheus (text/plain; version=0.0.4).
 * Persistência em arquivo JSON dentro do projeto, para suportar múltiplas requisições.
 *
 * IMPORTANTE: implementação minimalista, não substitui uma lib oficial.
 */
final class PrometheusMetrics
{
    private static ?PrometheusMetrics $instance = null;

    /**
     * Estrutura:
     *  [
     *    'counters'  => [ metric_name => [ key => ['labels'=>[], 'value'=>float] ] ],
     *    'summaries' => [ metric_name => [ key => ['labels'=>[], 'sum'=>float, 'count'=>int] ] ],
     *    'gauges'    => [ metric_name => [ key => ['labels'=>[], 'value'=>float] ] ],
     *  ]
     *
     * @var array<string,mixed>
     */
    private array $data = [
        'counters'  => [],
        'summaries' => [],
        'gauges'    => [],
    ];

    private string $storageFile;

    private function __construct()
    {
        // Base: .../adm.redealabama/adm.redealabama/app/Support => sobe dois níveis
        $baseDir = \dirname(__DIR__, 2);
        $metricsDir = $baseDir . '/metrics_storage';
        if (!is_dir($metricsDir)) {
            @mkdir($metricsDir, 0775, true);
        }
        $this->storageFile = $metricsDir . '/prometheus_metrics.json';
        $this->load();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function load(): void
    {
        if (is_file($this->storageFile)) {
            $json = @file_get_contents($this->storageFile);
            if ($json !== false) {
                $data = json_decode($json, true);
                if (is_array($data)) {
                    $this->data = array_merge(
                        ['counters' => [], 'summaries' => [], 'gauges' => []],
                        $data
                    );
                }
            }
        }
    }

    private function persist(): void
    {
        @file_put_contents(
            $this->storageFile,
            json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function keyFromLabels(array $labels): string
    {
        ksort($labels);
        return md5(json_encode($labels));
    }

    public function incCounter(string $name, array $labels = [], float $value = 1.0): void
    {
        $key = $this->keyFromLabels($labels);
        if (!isset($this->data['counters'][$name][$key])) {
            $this->data['counters'][$name][$key] = [
                'labels' => $labels,
                'value'  => 0.0,
            ];
        }
        $this->data['counters'][$name][$key]['value'] += $value;
        $this->persist();
    }

    public function startTimer(string $name, array $labels = []): array
    {
        return [
            'name'   => $name,
            'labels' => $labels,
            'start'  => microtime(true),
        ];
    }

    public function endTimer(array $timer): void
    {
        if (!isset($timer['name']) || !isset($timer['start'])) {
            return;
        }
        $elapsed = microtime(true) - (float) $timer['start'];
        $this->observeDuration($timer['name'], $elapsed, $timer['labels'] ?? []);
    }

    public function observeDuration(string $name, float $seconds, array $labels = []): void
    {
        $key = $this->keyFromLabels($labels);
        if (!isset($this->data['summaries'][$name][$key])) {
            $this->data['summaries'][$name][$key] = [
                'labels' => $labels,
                'sum'    => 0.0,
                'count'  => 0,
            ];
        }
        $this->data['summaries'][$name][$key]['sum']   += $seconds;
        $this->data['summaries'][$name][$key]['count'] += 1;
        $this->persist();
    }

    public function setGauge(string $name, float $value, array $labels = []): void
    {
        $key = $this->keyFromLabels($labels);
        $this->data['gauges'][$name][$key] = [
            'labels' => $labels,
            'value'  => $value,
        ];
        $this->persist();
    }

    private function formatLabels(array $labels): string
    {
        if (!$labels) {
            return '';
        }
        $parts = [];
        foreach ($labels as $k => $v) {
            $v = (string) $v;
            $v = str_replace(['\\', '"'], ['\\\\', '\\"'], $v);
            $parts[] = $k . '="' . $v . '"';
        }
        return '{' . implode(',', $parts) . '}';
    }

    /**
     * Renderiza as métricas no formato de texto Prometheus.
     */
    public function render(): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/plain; version=0.0.4');
        }

        // Counters
        foreach ($this->data['counters'] as $name => $samples) {
            echo "# TYPE {$name} counter\n";
            foreach ($samples as $sample) {
                $labels = $this->formatLabels($sample['labels']);
                $value  = $sample['value'];
                echo $name . $labels . ' ' . $value . "\n";
            }
            echo "\n";
        }

        // Summaries (sum + count)
        foreach ($this->data['summaries'] as $name => $samples) {
            echo "# TYPE {$name} summary\n";
            foreach ($samples as $sample) {
                $labels = $this->formatLabels($sample['labels']);
                $sum    = $sample['sum'];
                $count  = $sample['count'];
                echo $name . '_sum' . $labels . ' ' . $sum . "\n";
                echo $name . '_count' . $labels . ' ' . $count . "\n";
            }
            echo "\n";
        }

        // Gauges
        foreach ($this->data['gauges'] as $name => $samples) {
            echo "# TYPE {$name} gauge\n";
            foreach ($samples as $sample) {
                $labels = $this->formatLabels($sample['labels']);
                $value  = $sample['value'];
                echo $name . $labels . ' ' . $value . "\n";
            }
            echo "\n";
        }
    }
}

