<?php

namespace IProDev\Sitemap;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class WorkerPool
{
    private int $workerCount;
    private array $workers = [];
    private array $queue = [];
    private LoggerInterface $logger;
    private bool $running = false;

    public function __construct(int $workerCount = 5, ?LoggerInterface $logger = null)
    {
        $this->workerCount = $workerCount;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Add task to queue
     */
    public function addTask(callable $task, array $data = []): void
    {
        $this->queue[] = [
            'task' => $task,
            'data' => $data,
            'id' => uniqid('task_', true)
        ];
    }

    /**
     * Process queue with workers
     */
    public function process(): array
    {
        $this->running = true;
        $results = [];

        $this->logger->info("Starting worker pool with {$this->workerCount} workers");

        $chunks = array_chunk($this->queue, ceil(count($this->queue) / $this->workerCount));

        foreach ($chunks as $i => $chunk) {
            $this->workers[$i] = [
                'id' => $i,
                'tasks' => $chunk,
                'completed' => 0,
                'failed' => 0
            ];
        }

        // Process tasks
        foreach ($this->workers as $workerId => $worker) {
            foreach ($worker['tasks'] as $task) {
                try {
                    $result = call_user_func($task['task'], $task['data']);
                    $results[] = [
                        'task_id' => $task['id'],
                        'worker_id' => $workerId,
                        'result' => $result,
                        'status' => 'success'
                    ];
                    $this->workers[$workerId]['completed']++;
                } catch (\Throwable $e) {
                    $this->logger->error("Task {$task['id']} failed", [
                        'error' => $e->getMessage()
                    ]);
                    $results[] = [
                        'task_id' => $task['id'],
                        'worker_id' => $workerId,
                        'error' => $e->getMessage(),
                        'status' => 'failed'
                    ];
                    $this->workers[$workerId]['failed']++;
                }
            }
        }

        $this->running = false;
        $this->logger->info("Worker pool completed", [
            'total_tasks' => count($this->queue),
            'workers' => $this->workerCount
        ]);

        return $results;
    }

    /**
     * Get worker statistics
     */
    public function getStats(): array
    {
        $stats = [
            'worker_count' => $this->workerCount,
            'total_tasks' => count($this->queue),
            'running' => $this->running,
            'workers' => []
        ];

        foreach ($this->workers as $workerId => $worker) {
            $stats['workers'][$workerId] = [
                'tasks' => count($worker['tasks']),
                'completed' => $worker['completed'],
                'failed' => $worker['failed']
            ];
        }

        return $stats;
    }

    /**
     * Clear queue
     */
    public function clear(): void
    {
        $this->queue = [];
        $this->workers = [];
    }
}
