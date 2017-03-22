<?php
namespace Queue\Model\MongoCollection;

use \RegexIterator;
use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;

use CakeMonga\MongoCollection\BaseCollection;

use Cake\I18n\Time;
use Cake\Core\Configure;
use Cake\Log\LogTrait;

use Queue\Model\Entity\MongoQueuedJob;

class QueuedJobsCollection extends BaseCollection {
	use LogTrait;

	/**
	 * @var string|null
	 */
	protected $_key = null;

	/**
	 * Add a new Job to the Queue.
	 *
	 *
	 * Config
	 * - priority: 1-10, defaults to 5
	 * - notBefore: Optional date which must not be preceded
	 * - group: Used to group similar QueuedJobs
	 * - reference: An optional reference string
	 *
	 * @param string $jobName Job name
	 * @param array|null $data Array of data
	 * @param array $config Config to save along with the job
	 * @return \App\Model\MongoQueuedJob Saved job entity
	 * @throws \Exception
	 */
	public function createJob($jobName, array $data = null, array $config = []) {
		$queuedJob = [
			'job_type' => $jobName,
			'data' => is_array($data) ? json_encode($data) : null,
			'job_group' => !empty($config['group']) ? $config['group'] : null,
			'notbefore' => !empty($config['notBefore']) ? (new Time($config['notBefore']))->toUnixString() : null,
		] + $config;

		$queuedJob = MongoQueuedJob::fromArray($queuedJob);

		if ($queuedJob->errors()) {
			throw new Exception('Invalid entity data');
		}

		$queuedJob->unsetIdMongo();
		$_id = $this->collection->insert((array)$queuedJob);

		$queuedJob->setId($_id);

		return $queuedJob;
	}

	/**
	 * Returns the number of items in the queue.
	 * Either returns the number of ALL pending jobs, or the number of pending jobs of the passed type.
	 *
	 * @param string|null $type Job type to Count
	 * @return int
	 */
	public function getLength($type = null) {
		$conditions = ['completed' => null];
		if (!is_null($type)) {
			$conditions['job_type'] = $type;
		}

		return $this->count($conditions);
	}

	/**
	 * Return a list of all job types in the Queue.
	 *
	 * @param boolean $only_queued Wether to list types for all jobs or only those not complete
	 * @return array
	 */
	public function getTypes($only_queued = false) {
		$query = $only_queued ? ['completed' => null] : [];

		return $this->distinct('job_type', $query);
	}

	/**
	 * Return a list of all priorities in the Queue.
	 *
	 * @param boolean $only_queued Wether to list priorities for all jobs or only those not complete
	 * @return array
	 */
	public function getPriorities($only_queued = false) {
		$query = $only_queued ? ['completed' => null] : [];

		return $this->distinct('priority', $query);
	}

	/**
	 * Return some statistics about finished jobs still in the Database.
	 * TODO: Implement
	 *
	 * @return array
	 */
	public function getStats() {
		//$options = [
		//	'fields' => function ($query) {
		//		return [
		//			'job_type',
		//			'num' => $query->func()->count('*'),
		//			'alltime' => $query->func()->avg('UNIX_TIMESTAMP(completed) - UNIX_TIMESTAMP(created)'),
		//			'runtime' => $query->func()->avg('UNIX_TIMESTAMP(completed) - UNIX_TIMESTAMP(fetched)'),
		//			'fetchdelay' => $query->func()->avg('UNIX_TIMESTAMP(fetched) - IF(notbefore is NULL, UNIX_TIMESTAMP(created), UNIX_TIMESTAMP(notbefore))'),
		//		];
		//	},
		//	'conditions' => [
		//		'completed IS NOT' => null,
		//	],
		//	'group' => [
		//		'job_type',
		//	],
		//];
		//return $this->find('all', $options);
	}

	/**
	 * Look for a new job that can be processed with the current abilities and
	 * from the specified group (or any if null).
	 *
	 * @param array $capabilities Available QueueWorkerTasks.
	 * @param string|null $group Request a job from this group, (from any group if null)
	 * @return \Queue\Model\Entity\QueuedJob|null
	 */
	public function requestJob(array $capabilities, $group = null) {
		$now = new Time();
		$mongoNow = $now->toUnixString();

		// Pick and sort the priorities
		$priorities = $this->getPriorities(true); 

		$conditions = ['completed' => null];
		if (!is_null($group)) {
			$conditions['job_group'] = $group;
		}

		if (count($capabilities)) {
			$conditions['job_type'] = ['$in' => []];
			foreach ($capabilities as $task) {
				list($plugin, $name) = pluginSplit($task['name']);
				$conditions['job_type']['$in'][] = $task['name'];
			}
		}

		$conditions['notbefore'] = ['$lt' => $mongoNow];
		$jobs_nb = iterator_to_array($this->find($conditions)->sort(['priority' => 1, 'notbefore' => 1]));

		$conditions['notbefore'] = null;
		$jobs_nb_null = iterator_to_array($this->find($conditions)->sort(['priority' => 1]));
		$jobs = array_merge($jobs_nb, $jobs_nb_null);
		if (count($jobs) == 0) {
			return null;
		}

		// TODO migrate this into the task specific conditions
		//if (array_key_exists('rate', $task) && $tmp['job_type'] && array_key_exists($tmp['job_type'], $this->rateHistory)) {
		//	$tmp['UNIX_TIMESTAMP() >='] = $this->rateHistory[$tmp['job_type']] + $task['rate'];
		//}
	
		if (count($capabilities)) {
			// Filter jobs by task specific conditions.

			// $jobs is an array of [mongoId => document], so if I wish to iterate over it with a 
			// sequential index, I need to do that over an array of the keys
			$i = 0;
			$keys = array_keys($jobs);
			$job = $jobs[$keys[$i]];
			$nextJob = (count($jobs) > $i + 1) ? $jobs[$keys[$i + 1]] : null;

			do {
				foreach ($capabilities as $task) {
					list($plugin, $name) = pluginSplit($task['name']);

					// We know the job type is one of these, they were filtered before.
					if ($job['job_type'] == $name) {
						$timeoutAt = $now->copy();
						$timeDiffMongo = $timeoutAt->subSeconds($task['timeout'])->toUnixString();

						if (array_key_exists('fetched', $job) && $job['fetched'] < $timeDiffMongo &&
							array_key_exists('failed', $job) && $job['failed'] < ($task['retries'] + 1))
						{
							// The job isn't timed out, and hasn't failed too many times. Exit the while loop
							$nextJob = $job;
						} else {
							$i++;
							$job = $nextJob;
							$nextJob = (count($jobs) > $i + 1) ? $jobs[$keys[$i + 1]] : null;
						}
					}
				}
			} while($job != null && $job['_id'] != $nextJob['_id']);
		} else {
			$keys = array_keys($jobs);
			$job = $jobs[$keys[0]];
		}

		if ($job) {
			$job = MongoQueuedJob::fromArray($job);

			$key = $this->key();
			$job->setWorkerKey($this->key());
			$job->setFetched($mongoNow);

			$this->save($job);
		}

		return $job;
	}

	/**
	 * @param string $_id ID of job
	 * @param float $progress Value from 0 to 1
	 * @return bool Success
	 */
	public function updateProgress($id, $progress) {
		if (!$id) {
			return false;
		}
		return (bool)$this->update(['_id' => $_id], ['progress' => round($progress, 2)]);
	}

	/**
	 * Mark a job as Completed, removing it from the queue.
	 *
	 * @param \Queue\Model\Entity\MongoQueuedJob $job Job
	 * @return bool Success
	 */
	public function markJobDone(MongoQueuedJob $job) {
		$job->setCompleted(time());

		return (bool)$this->save($job);
	}

	/**
	 * Mark a job as Failed, incrementing the failed-counter and Requeueing it.
	 *
	 * @param \Queue\Model\Entity\QueuedJob $job Job
	 * @param string|null $failureMessage Optional message to append to the failure_message field.
	 * @return bool Success
	 */
	public function markJobFailed(MongoQueuedJob $job, $failureMessage = null) {
		$job->increaseFailed();

		if (!is_null($failureMessage)) {
			$job->setFailureMessage($failureMessage);
		}

		return (bool)$this->save($job);
	}

	/**
	 * Reset current jobs
	 *
	 * @return bool Success
	 */
	public function reset() {
		$data = [
			'completed' => null,
			'fetched' => null,
			'progress' => 0,
			'failed' => 0,
			'workerkey' => null,
			'failure_message' => null,
		];
		$criteria = ['completed' => null];
		return $this->update($criteria, $data);
	}

	/**
	 * Return some statistics about unfinished jobs still in the Database.
	 * TODO: Implement
	 *
	 * @return \Cake\ORM\Query
	 */
	public function getPendingStats() {
		// $findCond = [
		// 	'fields' => [
		// 		'job_type',
		// 		'created',
		// 		'status',
		// 		'fetched',
		// 		'progress',
		// 		'reference',
		// 		'failed',
		// 		'failure_message',
		// 	],
		// 	'conditions' => [
		// 		'completed IS' => null,
		// 	],
		// ];
		// return $this->find('all', $findCond);
	}

	/**
	 * Cleanup/Delete Completed Jobs.
	 *
	 * @return void
	 */
	public function cleanOldJobs() {
		$this->remove(['completed' => ['$lt' => time() - Configure::read('Queue.cleanuptimeout')]]);
		
		$pidFilePath = Configure::read('Queue.pidfilepath');
		if (!$pidFilePath) {
			return;
		}
		// Remove all old pid files left over
		$timeout = time() - 2 * Configure::read('Queue.cleanuptimeout');
		$Iterator = new RegexIterator(
			new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pidFilePath)),
			'/^.+\_.+\.(pid)$/i',
			RegexIterator::MATCH
		);
		foreach ($Iterator as $file) {
			if ($file->isFile()) {
				$file = $file->getPathname();
				$lastModified = filemtime($file);
				if ($timeout > $lastModified) {
					unlink($file);
				}
			}
		}
	}

	/**
	 * Generates a unique Identifier for the current worker thread.
	 *
	 * Useful to identify the currently running processes for this thread.
	 *
	 * @return string Identifier
	 */
	public function key() {
		if ($this->_key !== null) {
			return $this->_key;
		}
		$this->_key = sha1(microtime());
		return $this->_key;
	}

	/**
	 * Resets worker Identifier
	 *
	 * @return void
	 */
	public function clearKey() {
		$this->_key = null;
	}

	/**
	 * @return array
	 */
	public function getProcesses() {
		if (!($pidFilePath = Configure::read('Queue.pidfilepath'))) {
			return [];
		}

		$processes = [];
		foreach (glob($pidFilePath . 'queue_*.pid') as $filename) {
			$time = filemtime($filename);
			preg_match('/\bqueue_(\d+)\.pid$/', $filename, $matches);
			$processes[$matches[1]] = $time;
		}

		return $processes;
	}

	/**
	 * @param int $pid
	 * @param int $sig Signal (defaults to graceful SIGTERM = 15)
	 * @return void
	 */
	public function terminateProcess($pid, $sig = SIGTERM) {
		$pidFilePath = Configure::read('Queue.pidfilepath');
		if (!$pidFilePath || !$pid) {
			return;
		}

		posix_kill($pid, $sig);
		sleep(1);
		$file = $pidFilePath . 'queue_' . $pid . '.pid';
		if (file_exists($file)) {
			unlink($file);
		}
	}
}
