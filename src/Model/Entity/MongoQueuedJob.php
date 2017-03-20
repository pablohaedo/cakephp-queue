<?php
namespace Queue\Model\Entity;

use \ArrayAccess;

class MongoQueuedJob implements ArrayAccess {
	public $_id = null;
	public $job_type;
	public $data = null;
	public $job_group = null;
	public $reference = null;
	public $created = null;
	public $notbefore = null;
	public $fetched = null;
	public $completed = null;
	public $progress = null;
	public $failed;
	public $failure_message = null;
	public $workerkey = null;
	public $status = null;
	public $priority;

	public function __construct($job_type, $data, $priority) {
		$this->job_type = $job_type;
		$this->data = $data;
		$this->priority = $priority;
		$this->failed = 0;
	}

	public static function fromArray(Array $a) {
		$queuedJob = new MongoQueuedJob($a['job_type'], $a['data'], 
										(array_key_exists('priority', $a) ? $a['priority'] : 5));


		if (array_key_exists('_id', $a)) {
			$queuedJob->setId($a['_id']);
		}
		if (array_key_exists('data', $a)) {
			$queuedJob->setData($a['data']);
		}
		if (array_key_exists('job_group', $a)) {
			$queuedJob->setJobGroup($a['job_group']);
		}
		if (array_key_exists('reference', $a)) {
			$queuedJob->setReference($a['reference']);
		}

		if (array_key_exists('created', $a)) {
			$queuedJob->setCreated($a['created']);
		} else {
			$queuedJob->setCreated(time());
		}

		if (array_key_exists('notbefore', $a)) {
			$queuedJob->setNotBefore($a['notbefore']);
		}
		if (array_key_exists('fetched', $a)) {
			$queuedJob->setFetched($a['fetched']);
		}
		if (array_key_exists('completed', $a)) {
			$queuedJob->setCompleted($a['completed']);
		}
		if (array_key_exists('progress', $a)) {
			$queuedJob->setProgress($a['progress']);
		}
		if (array_key_exists('failure_message', $a)) {
			$queuedJob->setFailureMessage($a['failure_message']);
		}
		if (array_key_exists('worker_key', $a)) {
			$queuedJob->setWorkerKey($a['worker_key']);
		}
		if (array_key_exists('status', $a)) {
			$queuedJob->setStatus($a['status']);
		}

		return $queuedJob;
	}

	public function getId() {
		return $this->_id;
	}
	public function setId($_id) {
		$this->_id = $_id;
	}
	public function unsetIdMongo() {
		unset($this->_id);
	}

	public function getJobType() {
		return $this->job_type;
	}
	public function setJobType($job_type) {
		$this->job_type = $job_type;
	}

	public function getData() {
		return $this->data;
	}
	public function setData($data) {
		$this->data = $data;
	}

	public function getJobGroup() {
		return $this->job_group;
	}
	public function setJobGroup($job_group) {
		$this->job_group = $job_group;
	}

	public function getReference() {
		return $this->reference;
	}
	public function setReference($reference) {
		$this->reference = $reference;
	}

	public function getCreated() {
		return $this->created;
	}
	public function setCreated($created) {
		$this->created = $created;
	}

	public function getNotBefore() {
		return $this->notbefore;
	}
	public function setNotBefore($notbefore) {
		$this->notbefore = $notbefore;
	}

	public function getFetched() {
		return $this->fetched;
	}
	public function setFetched($fetched) {
		$this->fetched = $fetched;
	}

	public function getCompleted() {
		return $this->completed;
	}
	public function setCompleted($completed) {
		$this->completed = $completed;
	}

	public function getProgress() {
		return $this->progress;
	}
	public function setProgress($progress) {
		$this->progress = $progress;
	}

	public function getFailed() {
		return $this->failed;
	}
	public function setFailed($failed) {
		$this->failed = $failed;
	}
	public function increaseFailed() {
		$this->failed++;
	}

	public function getFailureMessage() {
		return $this->failure_message;
	}
	public function setFailureMessage($failure_message) {
		$this->failure_message = $failure_message;
	}

	public function getWorkerKey() {
		return $this->workerkey;
	}
	public function setWorkerKey($workerkey) {
		$this->workerkey = $workerkey;
	}

	public function getStatus() {
		return $this->status;
	}
	public function setStatus($status) {
		$this->status = $status;
	}

	public function getPriority() {
		return $this->priority;
	}
	public function setPriority($priority) {
		$this->priority = $priority;
	}

	public function errors() {
		return false;
	}

	/* ArrayAccess methods */
	public function offsetExists($offset) {
		if ($offset === 'id')
			$offset = '_id';

		return property_exists($this, $offset);
	}
	public function offsetGet($offset) {
		if ($offset === 'id')
			$offset = '_id';

		return $this->{$offset};
	}
	public function offsetSet($offset, $value) {
		if ($offset === 'id')
			$offset = '_id';

		$this->{$offset} = $value;
	}
	public function offsetUnset($offset) {
		if ($offset === 'id')
			$offset = '_id';

		unset($this->{$offset});
	}
}

