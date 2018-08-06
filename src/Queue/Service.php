<?php
namespace Globalis\PuppetSkilled\Queue;

use Exception;
use Throwable;

class Service
{
    /**
     * Queue instance
     *
     * @var Globalis\PuppetSkilled\Queue\Queue
     */
    protected $connection;

    /**
     * Create a new queue service instance
     *
     * @param \Globalis\PuppetSkilled\Queue\Queue $queue
     */
    public function __construct(Queue $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Dispatch a job behind a queue.
     *
     * @param  \Globalis\PuppetSkilled\Queue\Queueable  $job
     * @return mixed
     */
    public function dispatch(Queueable $job)
    {
        if (isset($job->queue, $job->delay)) {
            return $this->connection->laterOn($job->queue, $job->delay, $job);
        }
        if (isset($job->queue)) {
            return $this->connection->pushOn($job->queue, $job);
        }
        if (isset($job->delay)) {
            return $this->connection->later($job->delay, $job);
        }

        return $this->connection->push($job);
    }

    /**
     * Process the next job on the queue.
     *
     * @param  string  $queue
     * @param  \Globalis\PuppetSkilled\Queue\WorkerOptions  $options
     * @return void
     */
    public function runNextJob($queue, WorkerOptions $options)
    {
        try {
            $job = $this->getNextJob($queue);
            if ($job) {
                return $this->process(
                    $job,
                    $options
                );
            }
            return false;
        } catch (Exception $e) {
            $this->reportExceptions($e);
        }
    }


    /** Get the next job from the queue connection.
     *
     * @param  \Globalis\PuppetSkilled\Queue\Queue  $connection
     * @param  string  $queue
     * @return \Globalis\PuppetSkilled\Queue\Job\Base|null
     */
    protected function getNextJob($queue)
    {
        return $this->connection->pop($queue);
    }

    /**
    * Process a given job from the queue.
    *
    * @param  string  $connectionName
    * @param  \Globalis\PuppetSkilled\Contracts\Queue\Job\Base  $job
    * @param  \Globalis\PuppetSkilled\Queue\WorkerOptions  $options
    * @return void
    *
    * @throws \Throwable
    */
    public function process($job, WorkerOptions $options)
    {

        try {
            $this->markJobAsFailedIfAlreadyExceedsMaxAttempts(
                $job,
                (int) $options->maxTries
            );
            $job->fire();
        } catch (Exception $e) {
            $this->handleJobException($job, $options, $e);
        }
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * This will likely be because the job previously exceeded a timeout.
     *
     * @param  \Globalis\PuppetSkilled\Contracts\Queue\Job\Base  $job
     * @param  int  $maxTries
     * @return void
     */
    protected function markJobAsFailedIfAlreadyExceedsMaxAttempts($job, $maxTries)
    {
        if ($maxTries === 0 || $job->attempts() <= $maxTries) {
            return;
        }
        $e = new Exception(
            'A queued job has been attempted too many times. The job may have previously timed out.'
        );
        $this->failJob($job, $e);
        throw $e;
    }

    /**
     * Mark the given job as failed and raise the relevant event.
     *
     * @param  \Globlias\PuppetSkilled\Queue\Job\Base  $job
     * @param  \Exception  $e
     * @return void
     */
    protected function failJob($job, $e)
    {
        if ($job->isDeleted()) {
            return;
        }
        $job->delete();
        $job->failed($e);
    }

    protected function handleJobException($job, WorkerOptions $options, $e)
    {
        throw $e;
    }

    protected function reportExceptions($e)
    {
        throw $e;
    }
}
