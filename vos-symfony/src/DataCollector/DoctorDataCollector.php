<?php

namespace App\DataCollector;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class DoctorDataCollector extends DataCollector
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $connection = $this->em->getConnection();
        $platform = $connection->getDatabasePlatform();

        // Get Doctrine version
        try {
            $doctrineMeta = class_exists('Composer\InstalledVersions') 
                ? \Composer\InstalledVersions::getVersion('doctrine/orm')
                : '3.6.3';
        } catch (\Throwable $e) {
            $doctrineMeta = '3.6.3';
        }

        // Database info
        $databaseName = $connection->getDatabase();
        $databaseVersion = $connection->getWrappedConnection()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        $databaseDriver = $platform->getName();

        // Collect queries from profiler
        $queryCount = 0;
        $slowestQueries = [];
        
        if ($connection->getDriver() instanceof \Doctrine\DBAL\Driver\PDO\Driver) {
            // Try to get executed queries from the logger
            $logger = $connection->getConfiguration()->getSQLLogger();
            if ($logger instanceof \Doctrine\DBAL\Logging\DebugStack) {
                $queryCount = count($logger->queries);
                usort($logger->queries, fn($a, $b) => ($b['executionMS'] ?? 0) <=> ($a['executionMS'] ?? 0));
                $slowestQueries = array_slice($logger->queries, 0, 5);
            }
        }

        // Analyze for issues
        $issues = $this->analyzeQueries($connection);

        $this->data = [
            'database_engine' => ucfirst($databaseDriver),
            'database_version' => $databaseVersion,
            'database_name' => $databaseName,
            'doctrine_version' => $doctrineMeta,
            'query_count' => $queryCount,
            'total_issues' => count($issues['all']),
            'critical_issues' => count($issues['critical']),
            'warnings' => count($issues['warnings']),
            'info' => count($issues['info']),
            'slowest_queries' => $slowestQueries,
            'issues' => $issues,
        ];
    }

    private function analyzeQueries($connection): array
    {
        $issues = [
            'critical' => [],
            'warnings' => [],
            'info' => [],
            'all' => [],
        ];

        // Check for common SQL injection patterns
        if ($connection->getDriver() instanceof \Doctrine\DBAL\Driver\PDO\Driver) {
            $logger = $connection->getConfiguration()->getSQLLogger();
            if ($logger instanceof \Doctrine\DBAL\Logging\DebugStack) {
                foreach ($logger->queries as $query) {
                    $sql = $query['sql'];
                    
                    // Check for string concatenation instead of parameters
                    if (preg_match('/\s+WHERE\s+.*\'?\s*\.\s*\$|CONCAT\(/', $sql)) {
                        $issue = [
                            'type' => 'QUERY_BUILDER_SQL_INJECTION',
                            'title' => 'Potential SQL Injection in QueryBuilder',
                            'message' => 'The query appears to use string concatenation instead of parameter binding. Always use setParameter() to prevent SQL injection vulnerabilities.',
                            'severity' => 'critical',
                        ];
                        $issues['critical'][] = $issue;
                        $issues['all'][] = $issue;
                    }

                    // Check for N+1 queries
                    if (preg_match('/SELECT.*FROM.*WHERE.*IN\s*\(/i', $sql)) {
                        $issue = [
                            'type' => 'POTENTIAL_N_PLUS_ONE',
                            'title' => 'Potential N+1 Query Pattern',
                            'message' => 'This query uses IN clause which might indicate N+1 query problem. Consider using JOIN or LEFT JOIN instead.',
                            'severity' => 'warning',
                        ];
                        $issues['warnings'][] = $issue;
                        $issues['all'][] = $issue;
                    }

                    // Check for missing indexes
                    if (preg_match('/WHERE\s+.*(?:!=|<>)/i', $sql)) {
                        $issue = [
                            'type' => 'MISSING_INDEX',
                            'title' => 'Query with Negative Condition',
                            'message' => 'Queries with negative conditions (!= or <>) may not use indexes efficiently.',
                            'severity' => 'info',
                        ];
                        $issues['info'][] = $issue;
                        $issues['all'][] = $issue;
                    }
                }
            }
        }

        return $issues;
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function getName(): string
    {
        return 'app.doctor';
    }

    public function getTabName(): string
    {
        $total = $this->data['total_issues'] ?? 0;
        $badge = $total > 0 ? " <span style=\"color: #d42f2f;\">$total</span>" : '';
        return "Doctrine Doctor$badge";
    }

    public function getTabLabel(): string
    {
        return 'Doctrine Doctor';
    }

    public function getTabIcon(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAAsQAAALEBrcwpuQAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAADcSURBVDiPY/hPABgGMaGUMaGU8qGUMiEkFVBKGZFSGRHKWBFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRFKGRF';
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getDatabaseEngine(): string
    {
        return $this->data['database_engine'] ?? 'N/A';
    }

    public function getDatabaseVersion(): string
    {
        return $this->data['database_version'] ?? 'N/A';
    }

    public function getDoctrinVersion(): string
    {
        return $this->data['doctrine_version'] ?? 'N/A';
    }

    public function getTotalIssues(): int
    {
        return $this->data['total_issues'] ?? 0;
    }

    public function getCriticalIssues(): int
    {
        return $this->data['critical_issues'] ?? 0;
    }

    public function getWarnings(): int
    {
        return $this->data['warnings'] ?? 0;
    }

    public function getInfo(): int
    {
        return $this->data['info'] ?? 0;
    }

    public function getQueryCount(): int
    {
        return $this->data['query_count'] ?? 0;
    }

    public function getSlowestQueries(): array
    {
        return $this->data['slowest_queries'] ?? [];
    }

    public function getIssues(): array
    {
        return $this->data['issues'] ?? ['critical' => [], 'warnings' => [], 'info' => [], 'all' => []];
    }
}
