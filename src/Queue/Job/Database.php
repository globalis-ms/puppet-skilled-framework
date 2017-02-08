<?php

namespace Globalis\PuppetSkilled\Queue\Job;

use Globalis\PuppetSkilled\Queue\DatabaseQueue;

class Database extends Base
{
    /**
     * The database queue instance.
     *
     * @var Globalis\PuppetSkilled\Queue\DatabaseQueue
     */
    protected $database;

    /**
     * The database job payload.
     *
     * @var \StdClass
     */
    protected $job;

    /**
     * Create a new job instance.
     *
     * @param  Globalis\PuppetSkilled\Queue\DatabaseQueue  $database
     * @param  \StdClass  $job
     * @param  string  $queue
     * @return void
     */
    public function __construct(DatabaseQueue $database, $job, $queue)
    {
        $this->job = $job;
        $this->queue = $queue;
        $this->database = $database;
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        $this->database->deleteReserved($this->queue, $this->job->id);
    }

    /**
     * Release the job back into the queue.
     *
     * @param  int  $delay
     * @return void
     */
    public function release($delay = 0)
    {
        parent::release($delay);

        $this->delete();

        $this->database->release($this->queue, $this->job, $delay);
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return (int) $this->job->attempts;
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->job->id;
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->job->payload;
    }

    /**
     * Get the underlying queue driver instance.
     *
     * @return Globalis\PuppetSkilled\Queue\DatabaseQueue
     */
    public function getDatabaseQueue()
    {
        return $this->database;
    }

    /**
     * Get the underlying database job.
     *
     * @return \StdClass
     */
    public function getDatabaseJob()
    {
        return $this->job;
    }
}
