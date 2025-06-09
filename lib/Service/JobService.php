<?php

/**
 * JobService
 * 
 * Service class for handling job execution logic in the OpenConnector application.
 * This service manages job retrieval, validation, execution, and logging.
 *
 * @category Service
 * @package  OCA\OpenConnector\Service
 * @author   OpenConnector Development Team
 * @license  AGPL-3.0-or-later
 * @link     https://github.com/ConductionNL/openconnector
 * @version  1.0.0
 */

namespace OCA\OpenConnector\Service;

use OCA\OpenConnector\Db\Job;
use OCA\OpenConnector\Db\JobMapper;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCA\OpenConnector\Db\JobLog;
use OCA\OpenConnector\Db\JobLogMapper;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use DateTime;
use Exception;
use OCP\BackgroundJob\IJob;

/**
 * Service class for handling job execution and management
 *
 * This service provides methods for executing jobs, managing job states,
 * and handling job logging. It encapsulates the complex business logic
 * that was previously in the JobTask cron job.
 *
 * @psalm-api
 * @phpstan-type JobArgument array{jobId?: int, forceRun?: bool}
 * @phpstan-type JobResult array{level?: string, message?: string, stackTrace?: array<string>, nextRun?: int}
 */
class JobService
{
    /**
     * Job list manager for background job operations
     */
    private readonly IJobList $jobList;

    /**
     * Job mapper for database operations
     */
    private readonly JobMapper $jobMapper;

    /**
     * Database connection for direct queries
     */
    private readonly IDBConnection $connection;

    /**
     * Job log mapper for logging operations
     */
    private readonly JobLogMapper $jobLogMapper;

    /**
     * Container interface for dependency injection
     */
    private readonly ContainerInterface $containerInterface;

    /**
     * User session manager
     */
    private readonly IUserSession $userSession;

    /**
     * User manager for user operations
     */
    private readonly IUserManager $userManager;

    /**
     * JobService constructor
     *
     * Initializes the job service with required dependencies for job execution
     * and management operations.
     *
     * @param IJobList $jobList The job list manager for background jobs
     * @param JobMapper $jobMapper The job mapper for database operations
     * @param IDBConnection $connection Database connection for direct queries
     * @param JobLogMapper $jobLogMapper The job log mapper for logging
     * @param ContainerInterface $containerInterface Container for dependency injection
     * @param IUserSession $userSession User session manager
     * @param IUserManager $userManager User manager for user operations
     *
     * @psalm-param IJobList $jobList
     * @psalm-param JobMapper $jobMapper
     * @psalm-param IDBConnection $connection
     * @psalm-param JobLogMapper $jobLogMapper
     * @psalm-param ContainerInterface $containerInterface
     * @psalm-param IUserSession $userSession
     * @psalm-param IUserManager $userManager
     */
    public function __construct(
        IJobList $jobList,
        JobMapper $jobMapper,
        IDBConnection $connection,
        JobLogMapper $jobLogMapper,
        ContainerInterface $containerInterface,
        IUserSession $userSession,
        IUserManager $userManager
    ) {
        $this->jobList = $jobList;
        $this->jobMapper = $jobMapper;
        $this->connection = $connection;
        $this->jobLogMapper = $jobLogMapper;
        $this->containerInterface = $containerInterface;
        $this->userSession = $userSession;
        $this->userManager = $userManager;
    }

    /**
     * Schedule a job for execution
     *
     * This method handles the scheduling of jobs in the background job list.
     * It checks if the job should be enabled/disabled and schedules it accordingly.
     *
     * @param Job $job The job entity to schedule
     *
     * @return Job The updated job entity
     *
     * @psalm-param Job $job
     * @psalm-return Job
     * @phpstan-param Job $job
     * @phpstan-return Job
     */
    public function scheduleJob(Job $job): Job
    {
        // Let's first check if the job should be disabled
        if ($job->getIsEnabled() === false || $job->getJobListId()) {
            // @todo fix this (call to protected method)
            //$this->jobList->removeById($job->getJobListId());
            //$job->setJobListId(null);
            return $this->jobMapper->update($job);
        }

        // Let's not update the job if it's already scheduled @todo we should
        if ($job->getJobListId()) {
            return $job;
        }

        // Oke this is a new job let's schedule it
        $arguments = $job->getArguments();
        $arguments['jobId'] = $job->getId();

        // Schedule the job using the new JobTask class
        if (!$job->getScheduleAfter()) {
            $this->jobList->add(\OCA\OpenConnector\Cron\JobTask::class, $arguments);
        } else {
            $runAfter = $job->getScheduleAfter()->getTimestamp();
            $this->jobList->scheduleAfter(\OCA\OpenConnector\Cron\JobTask::class, $runAfter, $arguments);
        }

        // Set the job list id
        $job->setJobListId($this->getJobListId(\OCA\OpenConnector\Cron\JobTask::class));
        // Save the job to the database
        return $this->jobMapper->update($job);
    }

    /**
     * Get the job list ID of the last job in the list
     *
     * This function retrieves the database ID of the most recently added job
     * of a specific class from the background job list. This is needed because
     * the Nextcloud job list doesn't provide a better way to get the last job ID.
     *
     * @see https://github.com/nextcloud/server/blob/master/lib/private/BackgroundJob/JobList.php#L134
     *
     * @param class-string<IJob>|IJob $job The job class or instance to find the ID for
     *
     * @return int|null The job list ID if found, null otherwise
     *
     * @psalm-param class-string<IJob>|IJob $job
     * @psalm-return int|null
     * @phpstan-param class-string<IJob>|IJob $job
     * @phpstan-return int|null
     */
    public function getJobListId(IJob|string $job): int|null
    {
        // Extract the class name from either string or object
        $class = ($job instanceof IJob) ? get_class($job) : $job;

        // Build query to find the most recent job of this class
        $query = $this->connection->getQueryBuilder();
        $query->select('id')
            ->from('jobs')
            ->where($query->expr()->eq('class', $query->createNamedParameter($class)))
            ->orderBy('id', 'DESC')
            ->setMaxResults(1);

        // Execute query and fetch result
        $result = $query->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        return $row['id'] ?? null;
    }

    /**
     * Execute a job based on the provided arguments
     *
     * This method handles the complete job execution process including:
     * - Job validation and retrieval
     * - User session management
     * - Job execution timing
     * - Result processing and logging
     * - Next run scheduling
     *
     * @param array $argument The job execution arguments containing jobId and optional forceRun flag
     *
     * @return JobLog The job log entry created for this execution
     *
     * @throws \OCP\DB\Exception Database operation exceptions
     * @throws ContainerExceptionInterface Container operation exceptions
     * @throws NotFoundExceptionInterface When required services are not found
     *
     * @psalm-param JobArgument $argument
     * @psalm-return JobLog
     * @phpstan-param array{jobId?: int, forceRun?: bool} $argument
     * @phpstan-return JobLog
     */
    public function executeJob(array $argument): JobLog
    {
        // Validate that we have a valid job ID in the arguments
        if (isset($argument['jobId']) === false || is_int($argument['jobId']) === false) {
            return $this->jobLogMapper->createFromArray([
                'jobId'         => 'null',
                'level'			=> 'ERROR',
                'message'		=> "Couldn't find a jobId in the action argument"
            ]);
        }

        // Attempt to retrieve the job from the database
        try {
            $job = $this->jobMapper->find($argument['jobId']);
        } catch (Exception $e) {
            return $this->jobLogMapper->createFromArray([
                'jobId'         => $argument['jobId'],
                'level'			=> 'ERROR',
                'message'		=> "Couldn't find a Job with this jobId, message: ".$e->getMessage()
            ]);
        }

        // Initialize force run flag and stack trace for logging
        $forceRun = false;
        $stackTrace = [];
        if (isset($argument['forceRun']) === true && $argument['forceRun'] === true) {
            $forceRun = true;
            $stackTrace[] = 'Doing a force run for this job, ignoring "enabled" & "nextRun" check...';
        }

        // Check if the job is enabled (unless force run is requested)
        if ($forceRun === false && $job->getIsEnabled() === false) {
            return $this->jobLogMapper->createForJob($job, [
                'level'			=> 'WARNING',
                'message'		=> 'This job is disabled'
            ]);
        }

        // Check if the job is scheduled to run (unless force run is requested)
        if ($forceRun === false && $job->getNextRun() !== null && $job->getNextRun() > new DateTime()) {
            // Do not log, just skip execution
            return null;
        }

        // Set user session if job has a specific user configured
        if (empty($job->getUserId()) === false && $this->userSession->getUser() === null) {
            $user = $this->userManager->get($job->getUserId());
            $this->userSession->setUser($user);
        }

        // Record execution start time for performance tracking
        $time_start = microtime(true);

        // Get the job action class from the container and execute it
        $action = $this->containerInterface->get($job->getJobClass());
        $arguments = $job->getArguments();
        if (is_array($arguments) === false) {
            $arguments = [];
        }
        $result = $action->run($arguments);

        // Calculate execution time in milliseconds
        $time_end = microtime(true);
        $executionTime = ($time_end - $time_start) * 1000;

        // Handle single run jobs by disabling them after execution
        if ($forceRun === false && $job->isSingleRun() === true) {
            $job->setIsEnabled(false);
        }

        // Update job with last run time and calculate next run time
        $job->setLastRun(new DateTime());
        if ($forceRun === false) {
            $nextRun = new DateTime('now + ' . $job->getInterval() . ' seconds');
            
            // Handle rate limiting if specified in result
            if (isset($result['nextRun']) === true) {
                $nextRunRateLimit = DateTime::createFromFormat('U', $result['nextRun'], $nextRun->getTimezone());
                // Check if the current seconds part is not zero, and if so, round up to the next minute
                if ($nextRunRateLimit->format('s') !== '00') {
                    $nextRunRateLimit->modify('next minute');
                }
                if ($nextRunRateLimit > $nextRun) {
                    $nextRun = $nextRunRateLimit;
                }
            }
            
            // Set time to the current hour and minute (remove seconds)
            $nextRun->setTime(hour: $nextRun->format('H'), minute: $nextRun->format('i'));
            $job->setNextRun($nextRun);
        }
        
        // Persist job updates to database
        $this->jobMapper->update($job);

        // Create initial job log entry with success status
        $jobLog = $this->jobLogMapper->createForJob($job, [
            'level'			=> 'INFO',
            'message'		=> 'Success',
            'executionTime' => $executionTime
        ]);

        // Process job execution result and update log accordingly
        if (is_array($result) === true) {
            if (isset($result['level']) === true) {
                $jobLog->setLevel($result['level']);
            }
            if (isset($result['message']) === true) {
                $jobLog->setMessage($result['message']);
            }
            if (isset($result['stackTrace']) === true) {
                $stackTrace = array_merge($stackTrace, $result['stackTrace']);
            }
        }

        // Set final stack trace and persist log entry
        $jobLog->setStackTrace($stackTrace);
        $this->jobLogMapper->update(entity: $jobLog);

        return $jobLog;
    }
}
