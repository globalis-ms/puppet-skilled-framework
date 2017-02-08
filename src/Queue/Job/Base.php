<?php

namespace Globalis\PuppetSkilled\Queue\Job;

use DateTime;
use Carbon\Carbon;

abstract class Base
{
    /**
     * The job handler instance.
     *
     * @var mixed
     */
    protected $instance;

    /**
     * The name of the queue the job belongs to.
     *
     * @var string
     */
    protected $queue;

    /**
     * Indicates if the job has been deleted.
     *
     * @var bool
     */
    protected $deleted = false;

    /**
     * Indicates if the job has been released.
     *
     * @var bool
     */
    protected $released = false;

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    abstract public function attempts();

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    abstract public function getRawBody();

    /**
     * Fire the job.
     *
     * @return void
     */
    public function fire()
    {
        $payload = $this->payload();

        list($class, $method) = $this->parseJob($payload['job']);

        $this->instance = $this->resolve($class);

        $this->instance->{$method}($this, $payload['data']);
    }

    /**
     * Resolve the given job handler.
     *
     * @param  string  $class
     * @return mixed
     */
    protected function resolve($class)
    {
        return new $class;
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        $this->deleted = true;
    }

    /**
     * Determine if the job has been deleted.
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * Release the job back into the queue.
     *
     * @param  int   $delay
     * @return void
     */
    public function release($delay = 0)
    {
        $this->released = true;
    }

    /**
     * Determine if the job was released back into the queue.
     *
     * @return bool
     */
    public function isReleased()
    {
        return $this->released;
    }

    /**
     * Determine if the job has been deleted or released.
     *
     * @return bool
     */
    public function isDeletedOrReleased()
    {
        return $this->isDeleted() || $this->isReleased();
    }

    /**
     * Call the failed method on the job instance.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function failed($e)
    {
        $payload = $this->payload();

        list($class, $method) = $this->parseJob($payload['job']);

        $this->instance = $this->resolve($class);

        if (method_exists($this->instance, 'failed')) {
            $this->instance->failed($payload['data'], $e);
        }
    }

    /**
     * Parse the job declaration into class and method.
     *
     * @param  string  $job
     * @return array
     */
    protected function parseJob($job)
    {
        $segments = explode('@', $job);

        return count($segments) > 1 ? $segments : [$segments[0], 'fire'];
    }

    /**
     * Calculate the number of seconds with the given delay.
     *
     * @param  \DateTime|int  $delay
     * @return int
     */
    protected function getSeconds($delay)
    {
        if ($delay instanceof DateTime) {
            return max(0, $delay->getTimestamp() - $this->getTime());
        }

        return (int) $delay;
    }

    /**
     * Get the current system time.
     *
     * @return int
     */
    protected function getTime()
    {
        return Carbon::now()->getTimestamp();
    }

    /**
     * Get the name of the queued job class.
     *
     * @return string
     */
    public function getName()
    {
        return $this->payload()['job'];
    }

    /**
     * Get the resolved name of the queued job class.
     *
     * @return string
     */
    public function resolveName()
    {
        $name = $this->getName();

        $payload = $this->payload();

        if ($name === 'Globalis\PuppetSkilled\Queue\CallQueuedHandler@call') {
            return array_get($payload, 'data.commandName', $name);
        }

        if ($name === 'Globalis\PuppetSkilled\Events\CallQueuedHandler@call') {
            return $payload['data']['class'].'@'.$payload['data']['method'];
        }

        return $name;
    }

    /**
     * Get the decoded body of the job.
     *
     * @return array
     */
    public function payload()
    {
        return json_decode($this->getRawBody(), true);
    }

    /**
     * Get the name of the queue the job belongs to.
     *
     * @return string
     */
    public function getQueue()
    {
        return $this->queue;
    }
}
