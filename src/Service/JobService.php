/**
 * Get job logs with pagination
 *
 * @param int $jobId The ID of the job
 * @param int $page The page number (1-based)
 * @param int $limit The number of items per page
 * @return array{data: array<int, array<string, mixed>>, total: int, pages: int} The paginated job logs
 */
public function getJobLogs(int $jobId, int $page = 1, int $limit = 10): array
{
    // Validate job exists
    $job = $this->jobMapper->find($jobId);
    if (!$job) {
        throw new \InvalidArgumentException('Job not found');
    }

    // Calculate offset
    $offset = ($page - 1) * $limit;

    // Get total count
    $total = $this->jobLogMapper->countByJobId($jobId);

    // Get paginated logs
    $logs = $this->jobLogMapper->findByJobId($jobId, $limit, $offset);

    // Calculate total pages
    $pages = (int) ceil($total / $limit);

    return [
        'data' => $logs,
        'total' => $total,
        'pages' => $pages
    ];
} 